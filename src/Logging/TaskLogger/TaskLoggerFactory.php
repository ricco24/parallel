<?php

namespace Parallel\Logging\TaskLogger;

interface TaskLoggerFactory
{
    public function create(string $taskName): TaskLogger;
}
