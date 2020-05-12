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
        $processedItems = 0;
        try {
            $this->launchStartup();
        } catch (Throwable $e) {
            $this->error = 1;
            $this->sendNotify(0, 0, ['message' => 'Startup script failed']);
            return new ErrorResult($e->getMessage(), $e);
        }

        try {
            $itemsCount = $this->itemsCount();
            $this->sendNotify($itemsCount, $processedItems);
        } catch (Throwable $e) {
            $this->error = 1;
            $this->sendNotify(0, 0, ['message' => 'Error while counting items']);
            return new ErrorResult($e->getMessage(), $e);
        }

        try {
            $items = $this->items(0);
        } catch (Throwable $e) {
            $this->error = 1;
            $this->sendNotify(0, 0, ['message' => 'Error while fetching items']);
            return new ErrorResult($e->getMessage(), $e);
        }

        while (count($items)) {
            $itemsToProcess = [];
            foreach ($items as $item) {
                try {
                    $taskResult = $this->processItem($item);
                } catch (Throwable $e) {
                    $taskResult = new ErrorResult($e->getMessage(), $e);
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
                $this->logger->error($e->getMessage(), array_merge($this->getLogContext(), [
                    'exception' => $e
                ]));
                $this->error += count($itemsToProcess);
            }

            $processedItems += count($itemsToProcess);
            $this->sendNotify($itemsCount, $processedItems);

            // Infinity loop protection
            if ($processedItems == $itemsCount) {
                break;
            }

            try {
                $items = $this->items($processedItems);
            } catch (Throwable $e) {
                $this->error = 1;
                $this->sendNotify(0, 0, ['message' => 'Error while fetching items']);
                return new ErrorResult($e->getMessage(), $e);
            }
        }

        try {
            $this->launchShutdown($itemsCount, $processedItems);
        } catch (Throwable $e) {
            $this->error = $this->error === 0 ? 1 : $this->error;
            $this->sendNotify($itemsCount, $processedItems, ['message' => 'Shutdown script failed']);
            return new ErrorResult($e->getMessage(), $e);
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
     * Send message with zero values
     * @TODO: store values in process and send real values in any time
     * @param $message
     */
    protected function touchNotify($message)
    {
        $this->sendNotify(0, 0, ['message' => $message]);
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
