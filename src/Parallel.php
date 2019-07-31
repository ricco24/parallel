<?php

namespace Parallel;

use Parallel\Command\AnalyzeGraphCommand;
use Parallel\Command\RunCommand;
use Parallel\Helper\StringHelper;
use Parallel\Output\Output;
use Parallel\Output\TableOutput;
use Parallel\TaskStack\StackedTask;
use Parallel\TaskStack\TaskStack;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class Parallel
{
    use LoggerAwareTrait;

    /** @var string */
    private $binDirPath;

    /** @var string */
    private $fileName;

    /** @var int */
    private $concurrent;

    /** @var string */
    private $logDir;

    /** @var TaskStack */
    private $taskStack;

    /** @var Output */
    private $output;

    /** @var Application */
    private $app;

    /** @var array */
    private $data = [];

    /**
     * @param string $binDirPath
     * @param string $fileName
     * @param int $concurrent
     * @param string $logDir
     */
    public function __construct(string $binDirPath, string $fileName, int $concurrent = 3, string $logDir = '')
    {
        $this->binDirPath = $binDirPath;
        $this->fileName = $fileName;
        $this->concurrent = $concurrent;
        $this->logDir = rtrim($logDir, '/');
        $this->logger = new NullLogger();
        $this->app = new Application();
        $this->app->add(new RunCommand($this));
        $this->app->add(new AnalyzeGraphCommand($this));

        // Defaults
        $this->taskStack = new TaskStack();
        $this->output = new TableOutput();
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
     */
    public function getTaskStack(): TaskStack
    {
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
        $this->taskStack->addTask($task, $runAfter, $maxConcurrentTasksCount);
        $this->app->add($task);
        return $this;
    }

    /**
     * Start parallel
     */
    public function runConsoleApp(): void
    {
        $this->app->run();
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    public function execute(InputInterface $input, OutputInterface $output): void
    {
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
                    $this->printTaskStatsToFile($doneStackedTask);
                }
            }

            if (count($processes) >= $this->concurrent) {
                sleep(1);
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
                    'process' => new Process('php ' . $this->fileName . ' ' . $stackedTask->getTask()->getName(), $this->binDirPath, null, null, null),
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
                sleep(1);
            }
        }

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
    private function printTaskStatsToFile(StackedTask $stackedTask): void
    {
        if (empty($this->logDir)) {
            return;
        }

        $text = sprintf("***************************************************\nTask: %s\n***************************************************\n", $stackedTask->getTask()->getName());
        $text .= sprintf("Start at: %s\n", $stackedTask->getStartAt()->format('H:i:s'));
        $text .= sprintf("Finished at: %s\n", $stackedTask->getFinishedAt()->format('H:i:s'));

        if (isset($this->data[$stackedTask->getTask()->getName()])) {
            /** @var TaskData $taskData */
            $taskData = $this->data[$stackedTask->getTask()->getName()];
            $text .= "\nResults:\n";
            $text .= sprintf(" - total: %d\n - success: %d\n - skipped: %d\n - errors: %d\n",
                $taskData->getCount(),
                $taskData->getExtra('success', 0),
                $taskData->getExtra('skip', 0),
                $taskData->getExtra('error', 0)
            );
        }

        if (count($stackedTask->getRunningWith())) {
            $text .= "\nTasks ran along:\n";
            foreach ($stackedTask->getRunningWith() as $name => $value) {
                $text .= sprintf(" - %s (%s - %s)\n", $name, $value['from']->format('H:i:s'), $value['to']->format('H:i:s'));
            }
        }

        $text .= "\n";

        file_put_contents($this->logDir . '/task-stats.log', $text, FILE_APPEND);
    }
}
