<?php

namespace Parallel\TaskStack;

use Parallel\Task;
use Exception;

class TaskStack
{
    /** @var bool */
    private $prepared = false;

    /** @var int */
    private $tasksCount = 0;

    /** @var StackedTask[] */
    private $stackedTasks = [];

    /** @var StackedTask[] */
    private $runnableTasks = [];

    /** @var StackedTask[] */
    private $runningTasks = [];

    /** @var array */
    private $doneTasks = [];

    /**
     * @param Task $task
     * @param array $runAfter
     * @return TaskStack
     */
    public function addTask(Task $task, $runAfter = []): TaskStack
    {
        $this->stackedTasks[$task->getName()] = new StackedTask($task, $runAfter);
        $this->tasksCount++;
        return $this;
    }

    /**
     * Prepare task to run
     */
    public function prepare(): void
    {
        $this->moveFromStackToRunnable();
        $this->prepared = true;
    }

    /**
     * @param $name
     * @throws Exception
     */
    public function markDone(string $name): void
    {
        if (!isset($this->runningTasks[$name])) {
            throw new Exception('Task ' . $name . ' is not running');
        }

        foreach ($this->stackedTasks as $stackedTask) {
            $stackedTask->taskDone($name);
        }

        // Move from running to done tasks
        $this->doneTasks[$name] = $this->runningTasks[$name];
        $this->doneTasks[$name]->setStatus(StackedTask::STATUS_DONE);
        unset($this->runningTasks[$name]);

        $this->moveFromStackToRunnable();
    }

    /**
     * Get desired number of runnable tasks
     * @param int $count
     * @param int $currentTasksRunningCount
     * @return StackedTask[]
     */
    public function getRunnableTasks(int $count = 1, int $currentTasksRunningCount): array
    {
        $runnableTasks = [];
        $selected = 0;

        /** @var StackedTask[] $runnableTasks */
        foreach ($this->runnableTasks as $key => $task) {
            if ($selected === $count) {
                break;
            }

            // Check if any running task reach max concurrent task count
            foreach ($this->runningTasks as $runningTask) {
                if ($runningTask->getMaxConcurrentTasksCount() !== null && ($runningTask->getMaxConcurrentTasksCount() <= $currentTasksRunningCount + count($runnableTasks))) {
                    break 2;
                }
            }

            // Check if selected runnable task reach max concurrent task count
            if ($task->getMaxConcurrentTasksCount() !== null && ($task->getMaxConcurrentTasksCount() > $currentTasksRunningCount + count($runnableTasks))) {
                continue;
            }

            $runnableTasks[] = $task;
            unset($this->runnableTasks[$key]);
            $selected++;
        }

        foreach ($runnableTasks as $runnableTask) {
            $this->runningTasks[$runnableTask->getTask()->getName()] = $runnableTask;
            $runnableTask->setStatus(StackedTask::STATUS_RUNNING);
        }

        return $runnableTasks;
    }

    /**
     * Check if stack is empty
     * @return bool
     */
    public function isEmpty(): bool
    {
        return count($this->doneTasks) == $this->tasksCount;
    }

    /**
     * @return array
     */
    public function getStackedTasks(): array
    {
        return $this->stackedTasks;
    }

    /**
     * Move runnable tasks to queue
     */
    private function moveFromStackToRunnable()
    {
        foreach ($this->stackedTasks as $stackedTaskName => $stackedTask) {
            if ($stackedTask->isRunnable()) {
                $this->runnableTasks[] = $stackedTask;
                unset($this->stackedTasks[$stackedTaskName]);
            }
        }
    }
}
