<?php

namespace Parallel\TaskResult;

use Throwable;

class ErrorResult extends BaseTaskResult
{
    /** @var Throwable|null */
    private $throwable;

    /**
     * @param string $message
     * @param Throwable|null $throwable
     */
    public function __construct(string $message = '', Throwable $throwable = null)
    {
        parent::__construct($message);
        $this->throwable = $throwable;
    }

    /**
     * Result return code
     * @return int
     */
    public function getCode(): int
    {
        return 100;
    }

    /**
     * @return null|Throwable
     */
    public function getThrowable(): ?Throwable
    {
        return $this->throwable;
    }
}