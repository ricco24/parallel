<?php

namespace Parallel\TaskStack;

class TaskStackFactory
{
    public function create(array $tasks, array $subnets)
    {
        $taskStack = new TaskStack();

        $taskNames = [];
        foreach ($tasks as $task) {
            $taskNames[$task['task']->getName()] = true;
        }

        foreach ($tasks as $task) {
            foreach ($task['runAfter'] as $runAfterTask) {
                if (!array_key_exists($runAfterTask, $taskNames)) {
                    // @TODO: task doesnt exists
                }
            }
        }

        if (empty($subnets)) {
            foreach ($tasks as $task) {
                $taskStack->addTask($task['task'], $task['runAfter'], $task['maxConcurrentTasksCount']);
            }
            return $taskStack;
        }

        foreach ($tasks as $task) {
            foreach ($subnets as $subnet) {
                if (preg_match($subnet, $task['task']->getName())) {
                    
                }
            }
        }
    }
}
