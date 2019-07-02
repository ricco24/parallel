<?php

namespace Parallel\Task;

use Parallel\TaskResult\ErrorResult;
use Parallel\TaskResult\SkipResult;
use Parallel\TaskResult\SuccessResult;
use Parallel\TaskResult\TaskResult;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Parallel\Task as BaseTask;
use Throwable;

abstract class ProgressTask extends BaseTask
{
    /** @var int */
    private $success = 0;

    /** @var int */
    private $skip = 0;

    /** @var int */
    private $error = 0;

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return TaskResult
     */
    protected function process(InputInterface $input, OutputInterface $output): TaskResult
    {
        $items = $this->items();
        $itemsCount = $this->itemsCount();
        $i = 1;

        foreach ($items as $item) {
            try {
                $taskResult = $this->processItem($item);
            } catch (Throwable $e) {
                $taskResult = new ErrorResult($e->getMessage(), $e);
            }

            $this->processResult($taskResult);
            $this->logTaskResultToFile($taskResult);

            $this->notify($itemsCount, $i++, [
                'success' => $this->success,
                'skip' => $this->skip,
                'error' => $this->error
            ]);
        }

        return new SuccessResult();
    }

    /**
     * @param TaskResult $taskResult
     */
    private function processResult(TaskResult $taskResult): void
    {
        if ($taskResult instanceof SuccessResult) {
            $this->success++;
        } elseif ($taskResult instanceof SkipResult) {
            $this->skip++;
        } elseif ($taskResult instanceof ErrorResult) {
            $this->error++;
        }
    }

    /**
     * Return all items to process
     * @return iterable
     */
    abstract protected function items(): iterable;

    /**
     * Return all items count
     * @return int
     */
    abstract protected function itemsCount(): int;

    /**
     * Process one item
     * @param mixed $item
     * @return TaskResult
     */
    abstract protected function processItem($item): TaskResult;
}
