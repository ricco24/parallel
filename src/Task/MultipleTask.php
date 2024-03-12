<?php

namespace Parallel\Task;

interface MultipleTask
{
    /**
     * @param string $name
     * @return self
     */
    public function setName(string $name);

    /**
     * @return string
     */
    public function getName();

    public function setTaskNumber(int $number): self;

    public function setTaskCount(int $count): self;
}
