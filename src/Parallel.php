<?php

namespace Parallel;

use Parallel\Command\AnalyzeGraphCommand;
use Parallel\Command\RunCommand;
use Parallel\Helper\StringHelper;
use Parallel\Logging\TaskLogger\NullTaskLoggerFactory;
use Parallel\Logging\TaskLogger\TaskLogger;
use Parallel\Logging\TaskLogger\TaskLoggerFactory;
use Parallel\Output\Output;
use Parallel\Output\TableOutput;
use Parallel\Task\MultipleTask;
use Parallel\TaskStack\StackedTask;
use Parallel\TaskStack\TaskStack;
use Parallel\TaskStack\TaskStackFactory;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use Parallel\Exception\TaskStackFactoryException;
use Exception;

class Parallel
{
    use LoggerAwareTrait;

    private const MICROSECONDS_IN_SECOND = 1000000;

    /** @var string */
    private $binDirPath;

    /** @var string */
    private $fileName;

    /** @var int */
    private $concurrent;

    /** @var TaskLoggerFactory */
    private $taskLoggerFactory;

    /** @var array */
    private $tasksData = [];

    /** @var TaskStackFactory */
    private $taskStackFactory;

    /** @var TaskStack */
    private $taskStack;

    /** @var Output */
    private $output;

    /** @var Application */
    private $app;

    /** @var array */
    private $data = [];

    /** @var TaskLogger */
    private $globalTaskLogger;

    /** @var float */
    private $sleep;

    /** @var array  */
    private $multipleTasks = [];

    /**
     * @param string $binDirPath      Path to directory with parallel binary
     * @param string $fileName        Parallel binary filename
     * @param int $concurrent         Max count of concurrent tasks
     * @param float $secondsSleep     Sleep time between tasks
     */
    public function __construct(
        string $binDirPath,
        string $fileName,
        int $concurrent = 3,
        float $secondsSleep = 1.0
    ) {
        $this->binDirPath = $binDirPath;
        $this->fileName = $fileName;
        $this->concurrent = $concurrent;
        $this->sleep = $secondsSleep;
        $this->logger = new NullLogger();
        $this->taskLoggerFactory = new NullTaskLoggerFactory();
        $this->app = new Application();
        $this->app->add(new RunCommand($this));
        $this->app->add(new AnalyzeGraphCommand($this));

        // Defaults
        $this->taskStackFactory = new TaskStackFactory();
        $this->output = new TableOutput();
    }

    /**
     * @param TaskLoggerFactory $taskLoggerFactory
     * @return Parallel
     */
    public function setTaskLoggerFactory(TaskLoggerFactory $taskLoggerFactory): Parallel
    {
        $this->taskLoggerFactory = $taskLoggerFactory;
        return $this;
    }

    /**
     * @param string $analyzeDir
     * @return Parallel
     */
    public function setAnalyzeDir(string $analyzeDir): Parallel
    {
        $this->app->get('analyze:graph')->setAnalyzeDir($analyzeDir);
        return $this;
    }

    /**
     * @return TaskStack
     * @throws TaskStackFactoryException
     */
    public function getTaskStack(): TaskStack
    {
        $this->initializeTaskStack();
        return $this->taskStack;
    }

    /**
     * @param Output $output
     * @return Parallel
     */
    public function setOutput(Output $output): Parallel
    {
        $this->output = $output;
        return $this;
    }

    /**
     * @param Task $task
     * @param array $runAfter
     * @param int|null $maxConcurrentTasksCount
     * @return Parallel
     */
    public function addTask(Task $task, $runAfter = [], ?int $maxConcurrentTasksCount = null): Parallel
    {
        $task->setLogger($this->logger);
        $task->setTaskLoggerFactory($this->taskLoggerFactory);
        $this->tasksData[] = [
            'task' => $task,
            'runAfter' => function() use ($runAfter): array {
                foreach ($this->multipleTasks as $name => $tasks) {
                    $offset = array_search($name, $runAfter, true);
                    if ($offset !== false) {
                        array_splice($runAfter, (int)$offset, 1, $tasks);
                    }
                }
                return $runAfter;
            },
            'maxConcurrentTasksCount' => $maxConcurrentTasksCount
        ];
        $this->app->add($task);
        return $this;
    }

    /**
     * @param array|int $tasks  - count of tasks or array of identifiers
     * @param Task&MultipleTask $task
     * @param array $runAfter
     * @return $this
     * @throws Exception
     */
    public function addMultiTask($tasks, Task $task, array $runAfter = []): Parallel
    {
        if (!$task instanceof MultipleTask) {
            throw new \Exception(get_class($task) . ' must implement ' . MultipleTask::class . ' interface.');
        }

        $taskName = $task->getName();
        if (is_numeric($tasks)) {
            $tasks = range(1, $tasks);
        }
        $count = count($tasks);

        foreach ($tasks as $taskId) {
            $newTaskName = strpos($taskName, '%') === false ? "$taskName:$taskId" : str_replace('%', $taskId, $taskName);
            $newTask = (clone $task)
                ->setName($newTaskName)
                ->setTaskIdentifier($taskId)
                ->setTaskCount($count);
            $this->addTask($newTask, $runAfter);
            if (!isset($this->multipleTasks[$taskName])) {
                $this->multipleTasks[$taskName] = []; // $this->multipleTasks[$taskName] ??= [];
            }
            $this->multipleTasks[$taskName][] = $newTask->getName();
        }

        return $this;
    }

    public function setSleep(float $seconds): Parallel
    {
        $this->sleep = $seconds;
        return $this;
    }

    /**
     * Start parallel
     * @throws Exception
     */
    public function runConsoleApp(): void
    {
        $this->app->run();
    }

    /**
     * @param array $subnets
     * @throws TaskStackFactoryException
     */
    private function initializeTaskStack(array $subnets = []): void
    {
        if ($this->taskStack !== null) {
            return;
        }

        $this->taskStack = $this->taskStackFactory->create($this->tasksData, $subnets);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    public function execute(InputInterface $input, OutputInterface $output): void
    {
        try {
            $this->initializeTaskStack($input->getOption('subnet'));
        } catch (TaskStackFactoryException $e) {
            $this->output->errorMessage($output, $e->getMessage());
            return;
        }

        $this->globalTaskLogger = $this->taskLoggerFactory->create('global');
        $this->globalTaskLogger->prepareGlobal(count($this->taskStack->getStackedTasks()), $input->getOption('subnet'));

        $start = microtime(true);
        $this->output->startMessage($output);

        // Add all tasks to tasks data array
        foreach ($this->taskStack->getStackedTasks() as $stashedTask) {
            $this->buildTaskData($stashedTask);
        }

        $processes = [];
        $this->taskStack->prepare();
        while (!$this->taskStack->isEmpty()) {
            foreach ($processes as $runningProcessKey => $runningProcess) {
                if (!$runningProcess['process']->isRunning()) {
                    /** @var StackedTask $doneStackedTask */
                    $doneStackedTask = $runningProcess['stackedTask'];

                    $this->taskStack->markDone($doneStackedTask->getTask()->getName());
                    $this->moveTaskDataToBottom($doneStackedTask);
                    unset($processes[$runningProcessKey]);

                    foreach ($processes as $process) {
                        /** @var StackedTask $runningStackedTask */
                        $runningStackedTask = $process['stackedTask'];

                        $doneStackedTask->runningWithStop($runningStackedTask);
                        $runningStackedTask->runningWithStop($doneStackedTask);
                    }

                    // Redraw output when task finished
                    $this->output->printToOutput($output, $this->data, microtime(true) - $start);
                    $this->logDoneStackedTask($doneStackedTask);
                }
            }

            if (count($processes) >= $this->concurrent) {
                usleep(floor($this->sleep * self::MICROSECONDS_IN_SECOND));
                continue;
            }

            foreach ($this->taskStack->getRunnableTasks($this->concurrent - count($processes), count($processes)) as $stackedTask) {
                foreach ($processes as $process) {
                    /** @var StackedTask $runningStackedTask */
                    $runningStackedTask = $process['stackedTask'];
                    $stackedTask->runningWithStart($runningStackedTask);
                    $runningStackedTask->runningWithStart($stackedTask);
                }

                $processes[] = $process = [
                    'process' => new Process(['php', $this->fileName, $stackedTask->getTask()->getName()], $this->binDirPath, null, null, null),
                    'stackedTask' => $stackedTask
                ];

                $process['process']->start(function ($type, $buffer) use ($process, $input, $output, $stackedTask, $start) {
                    // We can get multiple task line results in one buffer
                    $lines = explode("\n", trim($buffer));

                    if ($type === Process::ERR) {
                        $this->buildTaskData($stackedTask, [
                            'code_errors_count' => count($lines)
                        ]);

                        foreach ($lines as $errorLine) {
                            $this->logger->error($stackedTask->getTask()->getSanitizedName() . ': ' . StringHelper::sanitize($errorLine));
                        }

                        $this->output->printToOutput($output, $this->data, microtime(true) - $start);
                        return;
                    }

                    // We process only last (newest) line with progress data
                    $lastLine = end($lines);

                    $data = [];
                    foreach (explode(';', $lastLine) as $statement) {
                        $explodedStatement = explode(':', $statement, 2);
                        if (count($explodedStatement) != 2) {
                            $this->logger->error($stackedTask->getTask()->getSanitizedName() . ': Cannot parse statement: ' . $statement);
                            $this->buildTaskData($stackedTask, [
                                'code_errors_count' => 1
                            ]);
                            return;
                        }
                        $data[$explodedStatement[0]] = $explodedStatement[1];
                    }

                    $this->buildTaskData($stackedTask, $data);
                    $this->output->printToOutput($output, $this->data, microtime(true) - $start);
                });
                usleep(floor($this->sleep * self::MICROSECONDS_IN_SECOND));
            }
        }

        $this->globalTaskLogger->processGlobal();
        $this->output->printToOutput($output, $this->data, microtime(true) - $start);
        $this->output->finishMessage($output, $this->data, microtime(true) - $start);
    }

    /**
     * @param StackedTask $stackedTask
     * @param array $data
     */
    private function buildTaskData(StackedTask $stackedTask, array $data = []): void
    {
        if (!isset($this->data[$stackedTask->getTask()->getName()])) {
            $this->data[$stackedTask->getTask()->getName()] = new TaskData($stackedTask);
        }

        $this->data[$stackedTask->getTask()->getName()]->fill($data);
    }

    /**
     * Move given taskData to the bottom of data stack
     * Used for place finished task in order they finished
     * @param StackedTask $stackedTask
     */
    private function moveTaskDataToBottom(StackedTask $stackedTask)
    {
        $taskName = $stackedTask->getTask()->getName();
        if (!isset($this->data[$taskName])) {
            return;
        }

        $taskData = $this->data[$taskName];
        unset($this->data[$taskName]);
        $this->data[$taskName] = $taskData;
    }

    /**
     * @param StackedTask $stackedTask
     */
    private function logDoneStackedTask(StackedTask $stackedTask): void
    {
        $withTasks = [];
        if (count($stackedTask->getRunningWith())) {
            foreach ($stackedTask->getRunningWith() as $name => $value) {
                $withTasks[$name] = [
                    'from' => $value['from']->format('c'),
                    'to' => $value['to']->format('c')
                ];
            }
        }

        /** @var TaskData $taskData */
        $taskData = $this->data[$stackedTask->getTask()->getName()];

        $this->globalTaskLogger->processDoneTaskData(
            $stackedTask->getTask()->getName(),
            $stackedTask->getStartAt(),
            $stackedTask->getFinishedAt(),
            $taskData->getMemoryPeak(),
            $withTasks,
            $taskData->getAllExtra()
        );
    }
}
