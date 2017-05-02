<?php

namespace Parallel;

use Parallel\TaskOutput\BaseTaskOutput;
use Parallel\TaskOutput\TaskOutput;
use Parallel\TaskResult\ErrorResult;
use Parallel\TaskResult\SkipResult;
use Parallel\TaskResult\TaskResult;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use LogicException;
use Throwable;

abstract class Task extends Command
{
    /** @var float */
    private $startTime = 0.0;

    /** @var TaskOutput */
    private $taskOutput;

    /** @var OutputInterface */
    private $output;

    /** @var string */
    private $logDir = '';

    /**
     * Base task configuration
     */
    public function configure()
    {
        $this->addOption('log_dir', null, InputOption::VALUE_REQUIRED, 'Log dir path');
    }

    /**
     * @return string
     */
    public function getSanitizedName(): string
    {
        return str_replace(':', '_', $this->getName());
    }

    /**
     * Start task timer
     */
    protected function start(): void
    {
        $this->startTime = microtime(true);
    }

    /**
     * Get current task duration
     * @return float
     */
    protected function duration(): float
    {
        return round(microtime(true) - $this->startTime);
    }

    /**
     * Get task estimated duration
     * @param int $count
     * @param int $current
     * @return float
     */
    protected function estimated(int $count, int $current): float
    {
        return $current * $count ? round((microtime(true) - $this->startTime) / $current * $count) : 0.0;
    }

    /**
     * @param int $count
     * @param int $current
     * @param array $data
     */
    protected function notify(int $count, int $current, array $data = []): void
    {
        $this->taskOutput->write($this->output, array_merge($data, [
            'count' => $count,
            'current' => $current,
            'duration' => $this->duration(),
            'estimated' => $this->estimated($count, $current)
        ]));
    }

    /**
     * No progress task start
     */
    protected function notifyStart(): void
    {
        $this->notify(1, 0);
    }

    /**
     * No progress start end
     * @param array $data
     */
    protected function notifyEnd(array $data = []): void
    {
        $this->notify(1, 1, $data);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null
     */
    protected function execute(InputInterface $input, OutputInterface $output): ?int
    {
        $this->output = $output;
        $this->taskOutput = new BaseTaskOutput();
        $this->logDir = $input->getOption('log_dir') ? rtrim($input->getOption('log_dir'), '/') : '';
        $this->start();

        try {
            $taskResult = $this->process($input, $output);
        } catch (Throwable $e) {
            $taskResult = new ErrorResult($e->getMessage());
        }

        $this->logTaskResultToFile($taskResult);
        return $taskResult->getCode();
    }

    /**
     * @param TaskResult $taskResult
     */
    protected function logTaskResultToFile(TaskResult $taskResult): void
    {
        if ($taskResult instanceof SkipResult) {
            $this->logToFile($taskResult->getMessage(), 'skip');
        } else if ($taskResult instanceof ErrorResult) {
            $this->logToFile($taskResult->getMessage(), 'error');
        }
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

        file_put_contents($this->logDir . '/' . $this->getSanitizedName() . '_' . $type, "\n[" . date('d.m.Y H:i:s') . ']: ' . $line, FILE_APPEND);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return TaskResult
     */
    protected function process(InputInterface $input, OutputInterface $output): TaskResult
    {
        throw new LogicException('You must override the process() method in the concrete task class.');
    }
}
