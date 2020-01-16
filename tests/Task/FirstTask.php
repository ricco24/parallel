<?php

namespace Tests;

use Parallel\Task\SimpleTask;
use Parallel\TaskResult\SuccessResult;
use Parallel\TaskResult\TaskResult;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class FirstTask extends SimpleTask
{
    protected function processTask(InputInterface $input, OutputInterface $output): TaskResult
    {
        return new SuccessResult();
    }
}