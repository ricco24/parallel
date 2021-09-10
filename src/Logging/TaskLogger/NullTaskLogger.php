<?php

namespace Parallel\Logging\TaskLogger;

class NullTaskLogger implements TaskLogger
{
    public function prepare(): void
    {
    }

    public function process(): void
    {
    }

    public function addLog(string $type, string $message, array $data = []): void
    {
    }
}
