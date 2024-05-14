<?php

namespace Parallel\TaskGenerator;

use Parallel\Task;

interface GeneratedTask
{
    public function getTask(): Task;

    public function getRunAfter(): array;

    public function getMaxConcurrentTasksCount(): ?int;
}
