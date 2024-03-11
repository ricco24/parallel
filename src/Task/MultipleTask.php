<?php

namespace Parallel\Task;

interface MultipleTask
{
    public function setTaskNumber(int $counter): self;

    public function setTaskCount(int $count): self;
}