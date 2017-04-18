<?php

namespace Parallel\TaskOutput;

class BaseTaskOutput implements TaskOutput
{
    private $data = [];

    public function format(int $count, int $current, float $duration): string
    {
        return 'count:' . $count . ';current:' . $current . ';duration:' . $duration;
    }

    public function parse(string $string): bool
    {
        $this->data = [];
        foreach (explode(';', $string) as $statement) {
            list($var, $value) = explode(':', $statement);
            $this->data[$var] = $value;
        }
    }

    public function getCount(): int
    {
        return $this->data['count'] ?? 0;
    }

    public function getCurrent(): int
    {
        return $this->data['current'] ?? 0;
    }

    public function getDuration(): float
    {
        return $this->data['duration'] ?? 0.0;
    }
}
