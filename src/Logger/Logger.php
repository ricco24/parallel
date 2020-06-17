<?php

namespace Parallel\Logger;

interface Logger
{
    const LEVEL_INFO = 'info';
    const LEVEL_ERROR = 'error';

    public function info($message, array $context = [], bool $flush = false): void;

    public function error($message, array $context = [], bool $flush = false): void;

    public function log($level, $message, array $context = [], bool $flush = false): void;

    public function flush(): void;
}
