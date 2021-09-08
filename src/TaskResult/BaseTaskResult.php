<?php

namespace Parallel\TaskResult;

abstract class BaseTaskResult implements TaskResult
{
    /** @var string */
    private $message;

    /** @var mixed|null */
    private $key;

    /**
     * @param string $message
     * @param mixed $key
     */
    public function __construct(string $message = '', $key = null)
    {
        $this->message = $message;
        $this->key = $key;
    }

    /**
     * @return string
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * @return mixed
     */
    public function getKey()
    {
        return $this->key;
    }
}
