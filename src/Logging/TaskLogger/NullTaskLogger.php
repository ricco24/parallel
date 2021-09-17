<?php

namespace Parallel\Logging\TaskLogger;

use DateTime;

class NullTaskLogger implements TaskLogger
{
    public function prepare(): void
    {
    }

    public function process(): void
    {
    }

    public function prepareGlobal(): void
    {
    }

    public function processGlobal(): void
    {
    }

    public function processDoneTaskData(string $name, DateTime $startAt, DateTime $endAt, int $memoryPeak, array $runWithTasks): void
    {
    }

    public function addLog(string $type, string $message, array $data = []): void
    {
    }
}
