<?php

namespace Parallel;

use Parallel\Command\RunCommand;
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

                $process['process']->start(function ($type, $buffer) use ($process, $input, $output) {
                    var_dump($buffer);
                    $taskName = $process['task']->getName();
                    if ($type === Process::ERR) {
                        $this->data[$taskName] = $this->buildTaskData($taskName, [
                            'code_error' => $this->removeNewlines($buffer)
                        ]);
                    }

                    $data = [];
                    foreach (explode(';', $buffer) as $statement) {
                        list($var, $value) = explode(':', $statement);
                        $data[$var] = $value;
                    }

                    $this->data[$taskName] = $this->buildTaskData($taskName, $data);
                    $this->output->print($output, $this->data);
                });
                sleep(1);
            }
        }

        $this->output->finishMessage($output, $this->data, microtime(true) - $start);
    }

    /**
     * @param string $string
     * @return string
     */
    private function removeNewlines(string $string): string
    {
        return trim(preg_replace('/\s+/', ' ', $string));
    }

    /**
     * @param string $taskName
     * @param array $data
     * @return array
     */
    private function buildTaskData(string $taskName, array $data): array
    {
        $result = $this->data[$taskName];

        if (isset($data['current']) && isset($data['count'])) {
            $result['progress'] = number_format((int) $data['current'] / (int) $data['count'] * 100);
        }

        if (isset($data['count'])) {
            $result['count'] = (int) $data['count'];
            unset($data['count']);
        }

        if (isset($data['current'])) {
            $result['current'] = (int) $data['current'];
            unset($data['current']);
        }

        if (isset($data['duration'])) {
            $result['duration'] = (float) $data['duration'];
            unset($data['duration']);
        }

        if (isset($data['estimated'])) {
            $result['estimated'] = (float) $data['estimated'];
            unset($data['estimated']);
        }

        if (isset($data['code_error'])) {
            $result['code_errors'][] = $data['code_error'];
            unset($data['code_error']);
        }

        foreach ($data as $key => $value) {
            $result['other'][$key] = $this->removeNewlines($value);
        }

        return $result;
    }
}
