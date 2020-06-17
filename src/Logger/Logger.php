<?php

namespace Parallel\Logger;

interface Logger
{
    public function log($type, $key, $value, array $context = [], bool $flush = false): void;

    public function flush(): void;
}
