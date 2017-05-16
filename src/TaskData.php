<?php

namespace Parallel;

use Parallel\Helper\StringHelper;
use Parallel\TaskStack\StackedTask;

class TaskData
{
    /** @var StackedTask */
    private $stackedTask;

    /** @var int */
    private $count = 0;

    /** @var int */
    private $current = 0;

    /** @var float */
    private $duration = 0.0;

    /** @var float */
    private $estimated = 0.0;

    /** @var int */
    private $memoryUsage = 0;

    /** @var int */
    private $memoryPeak = 0;

    /** @var int */
    private $codeErrorsCount = 0;

    /** @var array */
    private $extra = [];

    /**
     * TaskData constructor.
     * @param StackedTask $stackedTask
     */
    public function __construct(StackedTask $stackedTask)
    {
        $this->stackedTask = $stackedTask;
    }

    /**
     * @param array $data
     */
    public function fill(array $data): void
    {
        if (isset($data['count'])) {
            $this->count = (int) $data['count'];
            unset($data['count']);
        }

        if (isset($data['current'])) {
            $this->current = (int) $data['current'];
            unset($data['current']);
        }

        if (isset($data['duration'])) {
            $this->duration = (float) $data['duration'];
            unset($data['duration']);
        }

        if (isset($data['estimated'])) {
            $this->estimated = (float) $data['estimated'];
            unset($data['estimated']);
        }

        if (isset($data['memory_usage'])) {
            $this->memoryUsage = (int) $data['memory_usage'];
            unset($data['memory_usage']);
        }

        if (isset($data['memory_peak'])) {
            $this->memoryPeak = (int) $data['memory_peak'];
            unset($data['memory_peak']);
        }

        if (isset($data['code_errors_count'])) {
            $this->codeErrorsCount += $data['code_errors_count'];
            unset($data['code_errors_count']);
        }

        foreach ($data as $key => $value) {
            $this->extra[$key] = StringHelper::sanitize($value);
        }
    }

    /**
     * @return StackedTask
     */
    public function getStackedTask(): StackedTask
    {
        return $this->stackedTask;
    }

    /**
     * @return int
     */
    public function getCount(): int
    {
        return $this->count;
    }

    /**
     * @return int
     */
    public function getCurrent(): int
    {
        return $this->current;
    }

    /**
     * @return float
     */
    public function getDuration(): float
    {
        return $this->duration;
    }

    /**
     * @return float
     */
    public function getEstimated(): float
    {
        return $this->estimated;
    }

    /**
     * @return int
     */
    public function getCodeErrorsCount(): int
    {
        return $this->codeErrorsCount;
    }

    /**
     * @return float
     */
    public function getProgress(): float
    {
        return $this->count ? $this->current / $this->count * 100 : 0;
    }

    /**
     * @return int
     */
    public function getMemoryUsage(): int
    {
        return $this->memoryUsage;
    }

    /**
     * @return int
     */
    public function getMemoryPeak(): int
    {
        return $this->memoryPeak;
    }

    /**
     * @param string $key
     * @param null $default
     * @return mixed|null
     */
    public function getExtra(string $key, $default = null)
    {
        return $this->extra[$key] ?? $default;
    }
}
