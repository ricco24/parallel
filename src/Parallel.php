<?php

namespace Parallel;

use Parallel\Command\RunCommand;
use Parallel\Helper\StringHelper;
use Parallel\Output\Output;
use Parallel\Output\TableOutput;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class Parallel
{
    /** @var string */
    private $binDirPath;

    /** @var string */
    private $fileName;

    /** @var int */
    private $concurrent;

    /** @var string */
    private $logDir;

    /** @var Output */
    private $output;

    /** @var Application */
    private $app;

    /** @var Tasks */
    private $tasks;

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
        $this->app = new Application();
        $this->app->add(new RunCommand($this));

        // Defaults
        $this->tasks = new Tasks();
        $this->output = new TableOutput();
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
     * @param string $logDir
     * @return Parallel
     */
    public function setLogDir(string $logDir): Parallel
    {
        $this->logDir = $logDir;
        return $this;
    }

    /**
     * @param Task $task
     * @param array $runAfter
     * @return Parallel
     */
    public function addTask(Task $task, $runAfter = []): Parallel
    {
        $this->tasks->addTask($task, $runAfter);
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

        $processes = [];
        while (!$this->tasks->isEmpty()) {
            foreach ($processes as $runningProcessKey => $runningProcess) {
                if (!$runningProcess['process']->isRunning()) {
                    $this->tasks->markDone($runningProcess['task']->getName(), $output);
                    unset($processes[$runningProcessKey]);
                }
            }

            if (count($processes) >= $this->concurrent) {
                sleep(1);
                continue;
            }

            $runnableTasks = $this->tasks->getRunnableTasks($this->concurrent - count($processes));

            foreach ($runnableTasks as $task) {
                $arguments = null;
                if ($this->logDir) {
                    $arguments = '--log_dir=' . $this->logDir;
                }

                $processes[] = $process = [
                    'process' => new Process('php ' . $this->fileName . ' ' . $task->getName() . ' ' . $arguments, $this->binDirPath, null, null, null),
                    'task' => $task
                ];

                $process['process']->start(function ($type, $buffer) use ($process, $input, $output, $task, $start) {
                    $taskName = $process['task']->getName();

                    // We can get multiple task line results in one buffer
                    $lines = explode("\n", trim($buffer));

                    if ($type === Process::ERR) {
                        $this->buildTaskData($taskName, [
                            'code_errors_count' => count($lines)
                        ]);

                        foreach ($lines as $errorLine) {
                            $this->logToFile($task->getSanitizedName() . ': ' . StringHelper::sanitize($errorLine), 'error');
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
                            $this->logToFile($task->getSanitizedName() . ': Cannot parse statement: ' . $statement, 'error');
                            $this->buildTaskData($taskName, [
                                'code_errors_count' => 1
                            ]);
                            return;
                        }
                        $data[$explodedStatement[0]] = $explodedStatement[1];
                    }

                    $this->buildTaskData($taskName, $data);
                    $this->output->printToOutput($output, $this->data, microtime(true) - $start);
                });
                sleep(1);
            }
        }

        $this->output->finishMessage($output, $this->data, microtime(true) - $start);
    }

    /**
     * @param string $taskName
     * @param array $data
     */
    private function buildTaskData(string $taskName, array $data): void
    {
        if (!isset($this->data[$taskName])) {
            $this->data[$taskName] = new TaskData();
        }

        $this->data[$taskName]->fill($data);
    }

    /**
     * @param string $line
     * @param string $type
     */
    protected function logToFile(string $line, string $type): void
    {
        if (!$this->logDir) {
            return;
        }

        file_put_contents($this->logDir . '/parallel_' . $type, "\n[" . date('d.m.Y H:i:s') . ']: ' . $line, FILE_APPEND);
    }
}
