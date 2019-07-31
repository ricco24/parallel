<?php

namespace Parallel\TaskStack;

use Parallel\Task;
use DateTime;

class StackedTask
{
    const STATUS_STACKED = 'stacked';
    const STATUS_RUNNING = 'running';
    const STATUS_DONE = 'done';

    /** @var Task */
    private $task;

    /** @var string */
    private $status;

    /** @var DateTime|null */
    private $finishedAt;

    /** @var array */
    private $runAfter = [];

    /** @var int|null */
    private $maxConcurrentTasksCount;

    /** @var array */
    private $currentRunAfter = [];

    /**
     * StackedTask constructor.
     * @param Task $task
     * @param array $runAfter
     * @param int|null $maxConcurrentTasksCount
     */
    public function __construct(Task $task, array $runAfter = [], ?int $maxConcurrentTasksCount = null)
    {
        $this->task = $task;
        $this->runAfter = $this->currentRunAfter = array_combine($runAfter, $runAfter);
        $this->maxConcurrentTasksCount = $maxConcurrentTasksCount;
        $this->status = self::STATUS_STACKED;
    }

    /**
     * @param string $status
     * @return StackedTask
     */
    public function setStatus(string $status): StackedTask
    {
        $this->status = $status;
        if ($this->status === self::STATUS_DONE) {
            $this->finishedAt = new DateTime();
        }

        return $this;
    }

    /**
     * @return Task
     */
    public function getTask(): Task
    {
        return $this->task;
    }

    /**
     * @return array
     */
    public function getRunAfter(): array
    {
        return $this->runAfter;
    }

    /**
     * @return array
     */
    public function getCurrentRunAfter(): array
    {
        return $this->currentRunAfter;
    }

    /**
     * @return DateTime|null
     */
    public function getFinishedAt(): ?DateTime
    {
        return $this->finishedAt;
    }

    /**
     * @return int|null
     */
    public function getMaxConcurrentTasksCount(): ?int
    {
        return $this->maxConcurrentTasksCount;
    }

    /**
     * Some task is done, so remove from run after
     * @param string $taskName
     */
    public function taskDone(string $taskName): void
    {
        if (isset($this->currentRunAfter[$taskName])) {
            unset($this->currentRunAfter[$taskName]);
        }
    }

    /**
     * Check if task is in some status
     * @param string $status
     * @return bool
     */
    public function isInStatus(string $status): bool
    {
        return $this->status == $status;
    }

    /**
     * @return bool
     */
    public function isRunnable(): bool
    {
        return ! ((bool) count($this->currentRunAfter));
    }
}
