<?php

namespace Parallel;

use Parallel\Helper\StringHelper;

class TaskData
{
    /** @var int */
    private $count = 0;

    /** @var int */
    private $current = 0;

    /** @var float */
    private $duration = 0.0;

    /** @var float */
    private $estimated = 0.0;

    /** @var int */
    private $codeErrorsCount = 0;

    /** @var array */
    private $extra = [];

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

        if (isset($data['code_errors_count'])) {
            $this->codeErrorsCount += $data['code_errors_count'];
            unset($data['code_errors_count']);
        }

        foreach ($data as $key => $value) {
            $this->extra[$key] = StringHelper::sanitize($value);
        }
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
     * @param string $key
     * @param null $default
     * @return mixed|null
     */
    public function getExtra(string $key, $default = null)
    {
        return $this->extra[$key] ?? $default;
    }
}
