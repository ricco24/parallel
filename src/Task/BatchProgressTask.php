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

    /** @var int */
    private $itemsCount = 0;

    /** @var int */
    private $processedItems = 0;

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return TaskResult
     * @throws Throwable
     */
    protected function process(InputInterface $input, OutputInterface $output): TaskResult
    {
        try {
            $this->launchStartup();
        } catch (Throwable $e) {
            $this->error = 1;
            $this->sendNotify(['message' => 'Startup script failed']);
            throw $e;
        }

        try {
            $this->itemsCount = $this->itemsCount();
            $this->sendNotify();
        } catch (Throwable $e) {
            $this->error = 1;
            $this->sendNotify(['message' => 'Error while counting items']);
            throw $e;
        }

        try {
            $items = $this->items(0);
        } catch (Throwable $e) {
            $this->error = 1;
            $this->sendNotify(['message' => 'Error while fetching items']);
            throw $e;
        }

        while (count($items)) {
            $itemsToProcess = [];
            foreach ($items as $item) {
                try {
                    $taskResult = $this->processItem($item);
                } catch (Throwable $e) {
                    $taskResult = new ErrorResult($e->getMessage(), $e);
                }

                $this->logTaskResult($taskResult);
                if ($taskResult instanceof SuccessResult) {
                    $itemsToProcess[] = $taskResult->getData();
                } else {
                    $this->processResult($taskResult);
                    $this->processedItems++;
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

            $this->processedItems += count($itemsToProcess);
            $this->sendNotify();

            // Infinity loop protection
            if ($this->processedItems >= $this->itemsCount) {
                break;
            }

            try {
                $items = $this->items($this->processedItems);
            } catch (Throwable $e) {
                $this->error = 1;
                $this->sendNotify(['message' => 'Error while fetching items']);
                throw $e;
            }
        }

        try {
            $this->launchShutdown();
        } catch (Throwable $e) {
            $this->error = $this->error === 0 ? 1 : $this->error;
            $this->sendNotify(['message' => 'Shutdown script failed']);
            throw $e;
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
     * @param array $data
     */
    private function sendNotify(array $data = []): void
    {
        $this->notify($this->itemsCount, $this->processedItems, array_merge([
            'success' => $this->success,
            'skip' => $this->skip,
            'error' => $this->error,
            'message' => ''
        ], $data));
    }

    /**
     * Send message to output
     * @param $message
     */
    protected function sendMessage($message)
    {
        $this->sendNotify(['message' => $message]);
    }

    /**
     * Startup wrapper
     */
    private function launchStartup(): void
    {
        $this->sendNotify(['message' => 'Running startup prepare']);
        $this->startup();
        $this->sendNotify();
    }

    /**
     * Shutdown wrapper
     */
    private function launchShutdown(): void
    {
        $this->sendNotify(['message' => 'Running shutdown cleanup']);
        $this->shutdown();
        $this->sendNotify();
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
