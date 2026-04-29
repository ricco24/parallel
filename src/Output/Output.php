<?php

namespace Parallel\Output;

use Symfony\Component\Console\Output\OutputInterface;

interface Output
{
    public function setOutput(OutputInterface $output): void;

    public function startMessage(): void;

    public function errorMessage(string $error): void;

    public function printToOutput(array $data, float $elapsedTime): void;

    public function finishMessage(array $data, float $duration): void;
}
