<?php

namespace Parallel;

use Parallel\TaskOutput\BaseTaskOutput;
use Parallel\TaskOutput\TaskOutput;
use Parallel\TaskResult\BaseTaskResult;
use Parallel\TaskResult\ErrorResult;
use Parallel\TaskResult\SkipResult;
use Parallel\TaskResult\TaskResult;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use LogicException;
use Throwable;

abstract class Task extends Command
{
    use LoggerAwareTrait;

    /** @var float */
    private $startTime = 0.0;

    /** @var TaskOutput */
    private $taskOutput;

    /** @var OutputInterface */
    private $output;

    /**
     * @param string|null $name
     */
    public function __construct(string $name = null)
    {
        parent::__construct($name);
        $this->logger = new NullLogger();
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
            'estimated' => $this->estimated($count, $current),
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true)
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
        if (($taskResult instanceof SkipResult) && !empty($taskResult->getMessage())) {
            $this->logger->info($taskResult->getMessage(), $this->getLogContext($taskResult));
        } else if ($taskResult instanceof ErrorResult) {
            $this->logger->error($taskResult->getMessage(), $this->getLogContext($taskResult));
        }
    }

    /**
     * @param BaseTaskResult|null $result
     * @return array
     */
    protected function getLogContext(BaseTaskResult $result = null): array
    {
        $result = [
            'task' => $this->getName(),
            'result' => $result ? get_class($result) : null
        ];

        if ($result instanceof ErrorResult) {
            $result['exception'] = $result->getThrowable();
        }

        return $result;
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
