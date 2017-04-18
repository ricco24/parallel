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
    /** @var int */
    private $concurrent;

    /** @var Output */
    private $output;

    /** @var Application */
    private $app;

    /** @var Tasks */
    private $tasks;

    /** @var array */
    private $data = [];

    /**
     * @param int $concurrent
     */
    public function __construct(int $concurrent = 3)
    {
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

    public function runConsoleApp()
    {
        $this->app->run();
    }

    public function execute(InputInterface $input, OutputInterface $output)
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
                $processes[] = $process = [
                    'process' => new Process('php parallel ' . $task->getName(), '/var/www/Parallel/bin'),
                    'task' => $task
                ];

                $process['process']->start(function ($type, $buffer) use ($process, $input, $output) {
                    if (Process::ERR === $type) {
                        $output->writeln('ERR > ' . $buffer);
                    } else {
                        $data = [];
                        foreach (explode(';', $buffer) as $statement) {
                            list($var, $value) = explode(':', $statement);
                            $data[$var] = $value;
                        }

                        $this->data[$process['task']->getName()] = [
                            'count' => trim(preg_replace('/\s+/', ' ', $data['count'])), // removing newlines
                            'current' => trim(preg_replace('/\s+/', ' ', $data['current'])),
                            'duration' => isset($data['duration']) ? trim(preg_replace('/\s+/', ' ', $data['duration'])) : null,
                            'estimated' => isset($data['estimated']) ? trim(preg_replace('/\s+/', ' ', $data['estimated'])) : null,
                            'progress' => number_format((int) $data['current'] / (int) $data['count'] * 100)
                        ];
                        $this->output->print($output, $this->data);
                    }
                });
                sleep(1);
            }
        }

        $this->output->finishMessage($output, $this->data, microtime(true) - $start);
    }
}
