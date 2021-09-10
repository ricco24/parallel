<?php

namespace Parallel\TaskResult;

use Throwable;

class ErrorResult extends BaseTaskResult
{
    /** @var Throwable|null */
    private $throwable;

    /**
     * @param string $message
     * @param array $info
     * @param Throwable|null $throwable
     */
    public function __construct(string $message = '', array $info = [], Throwable $throwable = null)
    {
        parent::__construct($message, $info);
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
     * @return string
     */
    public function getShortName(): string
    {
        return 'error';
    }

    /**
     * @return null|Throwable
     */
    public function getThrowable(): ?Throwable
    {
        return $this->throwable;
    }
}
