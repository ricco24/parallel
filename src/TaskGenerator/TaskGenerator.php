<?php

namespace Parallel\TaskGenerator;

use Parallel\Task;

interface TaskGenerator
{
    public function getName(): string;

    /**
     * @return GeneratedTask[]
     */
    public function generateTasks(): array;
}
