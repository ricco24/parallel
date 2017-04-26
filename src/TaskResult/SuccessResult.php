<?php

namespace Parallel\TaskResult;

class SuccessResult extends BaseTaskResult
{
    /** @var array */
    private $data = [];

    /**
     * SuccessResult constructor.
     * @param array $data
     * @param string $message
     */
    public function __construct(array $data = [], string $message = '')
    {
        parent::__construct($message);
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
}