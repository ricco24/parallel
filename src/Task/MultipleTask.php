<?php

namespace Parallel\Task;

interface MultipleTask
{
    public function setName(string $name);

    public function getName(): string;

    public function setTaskNumber(int $number): self;

    public function setTaskCount(int $count): self;
}
