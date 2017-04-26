<?php

namespace Parallel\TaskOutput;

use Symfony\Component\Console\Output\OutputInterface;

interface TaskOutput
{
    public function write(OutputInterface $output, array $data): void;
}
