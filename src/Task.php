<?php

namespace Parallel;

use Symfony\Component\Console\Command\Command;

abstract class Task extends Command
{
    /** @var float */
    private $startTime = 0.0;

    /**
     * Start task timer
     */
    public function start(): void
    {
        $this->startTime = microtime(true);
    }

    /**
     * Get task duration
     * @return float
     */
    public function duration(): float
    {
        return round(microtime(true) - $this->startTime);
    }
}
