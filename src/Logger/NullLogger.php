<?php

namespace Parallel\Logger;

class NullLogger extends AbstractLogger
{
    public function log($level, $message, array $context = [], bool $flush = false): void
    {
    }

    public function flush(): void
    {
    }
}
