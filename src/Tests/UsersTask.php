<?php

namespace Parallel;

use Parallel\Task\BatchProgressTask;
use Parallel\TaskResult\SuccessResult;
use Parallel\TaskResult\TaskResult;

class UsersTask extends BatchProgressTask
{
    private $itemsCount = 50293;

    private $processedItems = 1;

    private $fileName = '/var/www/libs/parallel/output/users.txt';

    private $batch = 3;

    protected function items(int $processed): iterable
    {
        if ($processed >= $this->itemsCount) {
            return [];
        }

        return range($processed + 1, $processed + $this->batch);
    }

    protected function itemsCount(): int
    {
        return $this->itemsCount;
    }

    protected function processItem($item): TaskResult
    {
        $pi = $this->processedItems;
        file_put_contents($this->fileName, "\n" . $this->processedItems++ . '-' . 'users', FILE_APPEND);
        sleep(1);

        return new SuccessResult(['a', 'b', $pi]);
    }

    protected function batch(array $items): void
    {
        // everything is ok
    }
}
