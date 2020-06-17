<?php

namespace Parallel\Logger;

abstract class AbstractLogger implements Logger
{
    public function info($message, array $context = [], bool $flush = false): void
    {
        $this->log(Logger::LEVEL_INFO, $message, $context, $flush);
    }

    public function error($message, array $context = [], bool $flush = false): void
    {
        $this->log(Logger::LEVEL_ERROR, $message, $context, $flush);
    }
}
