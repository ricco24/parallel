<?php

namespace Parallel\TaskGenerator;

use Parallel\Task;

class BaseGeneratedTask implements GeneratedTask
{
    /** @var Task */
    private $task;

    /** @var array */
    private $runAfter;

    /** @var int|null */
    private $maxConcurrentTasksCount;

    public function __construct(Task $task, $runAfter = [], ?int $maxConcurrentTasksCount = null)
    {
        $this->task = $task;
        $this->runAfter = $runAfter;
        $this->maxConcurrentTasksCount = $maxConcurrentTasksCount;
    }

    public function getTask(): Task
    {
        return $this->task;
    }

    public function getRunAfter(): array
    {
        return $this->runAfter;
    }

    public function getMaxConcurrentTasksCount(): ?int
    {
        return $this->maxConcurrentTasksCount;
    }
}
