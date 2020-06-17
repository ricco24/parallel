<?php

namespace Parallel\Logger;

class NullLogger extends AbstractLogger
{
    public function log($type, $key, $value, array $context = [], bool $flush = false): void
    {
    }

    public function flush(): void
    {
    }
}
