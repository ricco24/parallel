<?php

namespace Parallel\TaskResult;

class SuccessResult extends BaseTaskResult
{
    /** @var array */
    private $data = [];

    /**
     * @param array $data
     * @param string $message
     * @param array $info
     */
    public function __construct(array $data = [], string $message = '', array $info = [])
    {
        parent::__construct($message, $info);
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
