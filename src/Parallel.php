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
     */
    public function __construct(string $binDirPath, string $fileName, int $concurrent = 3)
    {
        $this->binDirPath = $binDirPath;
        $this->fileName = $fileName;
        $this->concurrent = $concurrent;
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
     * @return Parallel
     */
    public function addTask(Task $task, $runAfter = []): Parallel
    {
        $this->taskStack->addTask($task, $runAfter);
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
                    $this->taskStack->markDone($runningProcess['stackedTask']->getTask()->getName());
                    $this->moveTaskDataToBottom($runningProcess['stackedTask']);
                    unset($processes[$runningProcessKey]);

                    // Redraw output when task finished
                    $this->output->printToOutput($output, $this->data, microtime(true) - $start);
                }
            }

            if (count($processes) >= $this->concurrent) {
                sleep(1);
                continue;
            }

            foreach ($this->taskStack->getRunnableTasks($this->concurrent - count($processes)) as $stackedTask) {
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
}
