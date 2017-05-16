<?php

namespace Parallel\TaskStack;

use Parallel\Task;

class StackedTask
{
    /** @var Task */
    private $task;

    /** @var array */
    private $runAfter = [];

    /** @var array */
    private $currentRunAfter = [];

    /**
     * @param Task $task
     * @param array $runAfter
     */
    public function __construct(Task $task, array $runAfter = [])
    {
        $this->task = $task;
        $this->runAfter = $this->currentRunAfter = array_combine($runAfter, $runAfter);
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
     * @return bool
     */
    public function isRunnable(): bool
    {
        return ! ((bool) count($this->currentRunAfter));
    }
}
