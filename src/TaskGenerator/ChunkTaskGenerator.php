<?php

namespace Parallel\TaskGenerator;

use Parallel\Task;

class ChunkTaskGenerator implements TaskGenerator
{
    /** @var string */
    private $name;

    /** @var Task */
    private $task;

    /** @var array */
    private $runAfter = [];

    /** @var ?int */
    private $maxConcurrentTasksCount;

    /** @var int */
    private $chunkSize;

    public function __construct(string $name, Task $task, int $chunksCount, array $runAfter = [], ?int $maxConcurrentTasksCount = null)
    {
        $this->name = $name;
        $this->task = $task;
        $this->chunkSize = $chunksCount;
        $this->runAfter = $runAfter;
        $this->maxConcurrentTasksCount = $maxConcurrentTasksCount;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function generateTasks(): array
    {
        $tasks = [];
        for ($i = 1; $i <= $this->chunkSize; $i++) {
            $tasks[] = new BaseGeneratedTask($this->task, $this->runAfter, $this->maxConcurrentTasksCount);
        }
        return $tasks;
    }
}
