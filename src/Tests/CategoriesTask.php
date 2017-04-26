<?php

namespace Parallel;

use Parallel\Task\ProgressTask;
use Parallel\TaskResult\SuccessResult;
use Parallel\TaskResult\TaskResult;

class CategoriesTask extends ProgressTask
{
    private $itemsCount = 10;

    private $processedItems = 1;

    private $fileName = '/var/www/libs/parallel/output/categories.txt';

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
        file_put_contents($this->fileName, "\n" . $this->processedItems++ . '-' . 'categories', FILE_APPEND);
        sleep(1);

        return new SuccessResult();
    }
}
