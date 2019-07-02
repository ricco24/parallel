<?php

namespace Parallel\Bridge\Monolog;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;
use Parallel\TaskResult\ErrorResult;
use Parallel\TaskResult\SkipResult;
use Parallel\TaskResult\SuccessResult;

class ParallelFileHandler extends AbstractProcessingHandler
{
    private $dir;

    public function __construct(string $dir, int $level = Logger::DEBUG, bool $bubble = true)
    {
        parent::__construct($level, $bubble);
        $this->dir = $dir;
    }

    protected function write(array $record)
    {
        $sanitizedTaskName = str_replace(':', '_', $record['context']['task']);
        file_put_contents(sprintf("%s/task_%s.log", $this->dir, $sanitizedTaskName), $this->generateMessage($record), FILE_APPEND);
    }

    private function generateMessage(array $record)
    {
        return sprintf("\n%s [%s]", date('d.m.Y H:i:s'), $this->getResultType($record['context']['result']), $record['message']);
    }

    private function getResultType(string $result)
    {
        switch ($result) {
            case SuccessResult::class:
                return 'success';
            case SkipResult::class:
                return 'skip';
            case ErrorResult::class:
                return 'error';
            default:
                return 'unknown';
        }
    }
}
