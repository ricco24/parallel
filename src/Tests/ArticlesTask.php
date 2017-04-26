<?php

namespace Parallel;

use Parallel\Task\ProgressTask;
use Parallel\TaskResult\ErrorResult;
use Parallel\TaskResult\TaskResult;

class ArticlesTask extends ProgressTask
{
    private $itemsCount = 12;

    private $processedItems = 1;

    private $fileName = '/var/www/libs/parallel/output/articles.txt';

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
        file_put_contents($this->fileName, "\n" . $this->processedItems++ . '-' . 'articles', FILE_APPEND);
        sleep(1);

        return new ErrorResult('lebo chcem ...');
    }
}
