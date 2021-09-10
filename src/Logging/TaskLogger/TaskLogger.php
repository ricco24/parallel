<?php

namespace Parallel\Logging\TaskLogger;

interface TaskLogger
{
    public function prepare(): void;

    public function process(): void;

    public function addLog(string $type, string $message, array $info = []): void;
}
