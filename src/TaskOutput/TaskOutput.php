<?php

namespace Parallel\TaskOutput;

interface TaskOutput
{
    public function format(int $count, int $current, float $duration): string;

    public function parse(string $input): bool;

    public function getCount(): int;

    public function getCurrent(): int;

    public function getDuration(): float;
}
