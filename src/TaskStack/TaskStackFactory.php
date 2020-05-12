<?php

namespace Parallel\TaskStack;

use Parallel\Exception\TaskStackFactoryException;

class TaskStackFactory
{
    /**
     * @param array $tasksData
     * @param array $subnets
     * @return TaskStack
     * @throws TaskStackFactoryException
     */
    public function create(array $tasksData, array $subnets)
    {
        $taskStack = new TaskStack();
        $taskNames = $this->getFlattenTaskNames($tasksData);

        // Check dependencies task names
        foreach ($tasksData as $taskData) {
            foreach ($taskData['runAfter'] as $runAfterTask) {
                if (!array_key_exists($runAfterTask, $taskNames)) {
                    throw new TaskStackFactoryException(sprintf("Task with name \"%s\" required by \"%s\" does not exist", $runAfterTask, $taskData['task']->getName()));
                }
            }
        }

        // Nothing needs to be filtered
        if (empty($subnets)) {
            foreach ($tasksData as $taskData) {
                $taskStack->addTask($taskData['task'], $taskData['runAfter'], $taskData['maxConcurrentTasksCount']);
            }
            return $taskStack;
        }

        // Filter out all tasks that does not match any subnet
        foreach ($tasksData as $taskData) {
            if (!$this->matchSomeSubnet($subnets, $taskData['task']->getName())) {
                continue;
            }

            $runAfter = [];
            foreach ($taskData['runAfter'] as $runAfterTask) {
                if ($this->matchSomeSubnet($subnets, $runAfterTask)) {
                    $runAfter[] = $runAfterTask;
                }
            }

            $taskStack->addTask($taskData['task'], $runAfter, $taskData['maxConcurrentTasksCount']);
        }
        return $taskStack;
    }

    private function getFlattenTaskNames(array $tasksData): array
    {
        $taskNames = [];
        foreach ($tasksData as $taskData) {
            $taskNames[$taskData['task']->getName()] = true;
        }
        return $taskNames;
    }

    private function matchSomeSubnet(array $subnets, string $taskName): bool
    {
        foreach ($subnets as $subnet) {
            if (preg_match('#' . $subnet . '#', $taskName)) {
                return true;
            }
        }
        return false;
    }
}
