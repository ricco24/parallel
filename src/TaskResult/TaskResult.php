<?php

namespace Parallel\TaskResult;

interface TaskResult
{
    /**
     * @return string
     */
    public function getMessage(): string;

    /**
     * Result return code
     * @return int
     */
    public function getCode(): int;
}