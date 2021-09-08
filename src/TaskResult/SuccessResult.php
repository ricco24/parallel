<?php

namespace Parallel\TaskResult;

class SuccessResult extends BaseTaskResult
{
    /** @var array */
    private $data = [];

    /**
     * SuccessResult constructor.
     * @param array $data
     * @param mixed $key
     * @param string $message
     */
    public function __construct(array $data = [], $key = null, string $message = '')
    {
        parent::__construct($message, $key);
        $this->data = $data;
    }

    /**
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Result return code
     * @return int
     */
    public function getCode(): int
    {
        return 0;
    }

    /**
     * @return string
     */
    public function getShortName(): string
    {
        return 'success';
    }
}
