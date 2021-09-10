<?php

namespace Parallel\TaskResult;

abstract class BaseTaskResult implements TaskResult
{
    /** @var string */
    private $message;

    /** @var array */
    private $info = [];

    /**
     * @param string $message
     * @param array $info
     */
    public function __construct(string $message = '', array $info = [])
    {
        $this->message = $message;
        $this->info = $info;
    }

    /**
     * @return string
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    public function getInfo(): array
    {
        return $this->info;
    }
}
