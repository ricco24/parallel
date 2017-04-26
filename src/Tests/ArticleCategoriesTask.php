<?php

namespace Parallel;

use Parallel\Task\SimpleTask;
use Parallel\TaskResult\SuccessResult;
use Parallel\TaskResult\TaskResult;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ArticleCategoriesTask extends SimpleTask
{
    protected function processTask(InputInterface $input, OutputInterface $output): TaskResult
    {
        sleep(5);
        return new SuccessResult();
    }
}
