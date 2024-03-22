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

    /**
     * @param int|string $identifier
     * @return self
     */
    public function setTaskIdentifier($identifier): self;

    public function setTaskCount(int $count): self;
}
