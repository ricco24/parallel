<?php

namespace Parallel\Logging\TaskLogger;

class NullTaskLoggerFactory implements TaskLoggerFactory
{
    public function create(string $taskName): TaskLogger
    {
        return new NullTaskLogger();
    }
}
