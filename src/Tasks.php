<?php

namespace Parallel;

class Tasks
{
    /** @var array */
    private $tasksStack = [];

    /** @var Task[] */
    private $runnableTasks = [];

    private $doneTasks = [];

    private $tasksCount = 0;

    public function addTask(Task $task, $runAfter = [])
    {
        if (count($runAfter)) {
            $this->tasksStack[] = [
                'task' => $task,
                'runAfter' => $runAfter
            ];
        } else {
            $this->runnableTasks[] = $task;
        }
        $this->tasksCount++;
    }

    public function markDone($name, $output)
    {
        $this->doneTasks[] = $name;

        foreach ($this->tasksStack as $stackKey => $stackedTask) {
            foreach ($stackedTask['runAfter'] as $afterKey => $afterTask) {
                if ($afterTask === $name) {
                    unset($this->tasksStack[$stackKey]['runAfter'][$afterKey]);
                }
            }
            if (!count($this->tasksStack[$stackKey]['runAfter'])) {
                $this->runnableTasks[] = $this->tasksStack[$stackKey]['task'];
                unset($this->tasksStack[$stackKey]);
            }
        }
    }

    public function getRunnableTasks($count)
    {
        $result = array_slice($this->runnableTasks, 0, $count);
        $this->runnableTasks = array_slice($this->runnableTasks, $count);
        return $result;
    }

    public function isEmpty()
    {
        return count($this->doneTasks) === $this->tasksCount;
    }
}
