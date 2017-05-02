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

abstract class BatchProgressTask extends BaseTask
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
        $this->launchStartup();

        $items = $this->items(0);
        $itemsCount = $this->itemsCount();
        $processedItems = 0;

        while (count($items)) {
            // Infinity loop protection - when items() function returns more items as itemsCount()
            if ($processedItems == $itemsCount) {
                $this->logToFile('Infinity loop protection. Function items() returns more items as function itemsCount().', 'warning');
                break;
            }

            $itemsToProcess = [];
            foreach ($items as $item) {
                try {
                    $taskResult = $this->processItem($item);
                } catch (Throwable $e) {
                    $taskResult = new ErrorResult($e->getMessage());
                }

                $this->logTaskResultToFile($taskResult);
                if ($taskResult instanceof SuccessResult) {
                    $itemsToProcess[] = $taskResult->getData();
                } else {
                    $this->processResult($taskResult);
                    $processedItems++;
                }
            }

            try {
                $this->batch($itemsToProcess);
                $this->success += count($itemsToProcess);
            } catch (Throwable $e) {
                $this->logToFile($e->getMessage(), 'error');
                $this->error += count($itemsToProcess);
            }

            $processedItems += count($itemsToProcess);
            $this->sendNotify($itemsCount, $processedItems);

            $items = $this->items($processedItems);
        }

        $this->launchShutdown($itemsCount, $processedItems);

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
     * Wrapper function for notification
     * @param int $itemsCount
     * @param int $processedItems
     * @param array $data
     */
    private function sendNotify(int $itemsCount, int $processedItems, array $data = []): void
    {
        $this->notify($itemsCount, $processedItems, array_merge([
            'success' => $this->success,
            'skip' => $this->skip,
            'error' => $this->error,
            'message' => ''
        ], $data));
    }

    /**
     * Startup wrapper
     */
    private function launchStartup(): void
    {
        $this->sendNotify(0, 0, ['message' => 'Running startup prepare']);
        $this->startup();
        $this->sendNotify(0, 0);
    }

    /**
     * Shutdown wrapper
     * @param int $itemsCount
     * @param int $processItems
     */
    private function launchShutdown(int $itemsCount, int $processItems): void
    {
        $this->sendNotify($itemsCount, $processItems, ['message' => 'Running shutdown cleanup']);
        $this->shutdown();
        $this->sendNotify($itemsCount, $processItems);
    }

    /**
     * Run task startup preparation jobs
     */
    protected function startup(): void
    {
        // Fill in child
    }

    /**
     * Run task shutdown cleanup jobs
     */
    protected function shutdown(): void
    {
        // Fill in child
    }

    /**
     * Return chunk of items to process
     * @param int $processed
     * @return iterable
     */
    abstract protected function items(int $processed): iterable;

    /**
     * Return all items count
     * @return int
     */
    abstract protected function itemsCount(): int;

    /**
     * Prepare/process one item
     * @param mixed $item
     * @return TaskResult
     */
    abstract protected function processItem($item): TaskResult;

    /**
     * Process all items in one function from items() function processed in processItem() function returned as SuccessResult
     * @param array $items
     */
    abstract protected function batch(array $items): void;
}
