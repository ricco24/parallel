<?php

namespace Parallel\Output;

use Symfony\Component\Console\Output\OutputInterface;

interface Output
{
    public function startMessage(OutputInterface $output): void;

    public function print(OutputInterface $output, array $data): void;

    public function finishMessage(OutputInterface $output, array $data, float $duration): void;
}
