<?php

namespace Parallel\TaskResult;

class ErrorResult extends BaseTaskResult
{
    /**
     * Result return code
     * @return int
     */
    public function getCode(): int
    {
        return 100;
    }
}