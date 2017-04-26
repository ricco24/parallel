<?php

namespace Parallel;

use Parallel\Task\ProgressTask;
use Parallel\TaskResult\SuccessResult;
use Parallel\TaskResult\TaskResult;

class AdminsTask extends ProgressTask
{
    private $itemsCount = 7;

    private $processedItems = 1;

    private $fileName = '/var/www/libs/parallel/output/admins.txt';

    protected function items(): iterable
    {
        return range(1, $this->itemsCount);
    }

    protected function itemsCount(): int
    {
        return $this->itemsCount;
    }

    protected function processItem($item): TaskResult
    {
        file_put_contents($this->fileName, "\n" . $this->processedItems++ . '-' . 'admins', FILE_APPEND);
        sleep(1);

        return new SuccessResult();
    }
}
