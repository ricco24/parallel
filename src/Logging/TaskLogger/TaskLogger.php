<?php

namespace Parallel\Logging\TaskLogger;

use DateTime;

interface TaskLogger
{
    /** Runs at start of every task */
    public function prepare(): void;

    /** Runs at end of every task */
    public function process(): void;

    /** Runs at global execution start */
    public function prepareGlobal(int $allTasksCount, array $inputSubnets): void;

    /** Runs at global execution end */
    public function processGlobal(): void;

    /** Runs at end of task (after process() method) */
    public function processDoneTaskData(string $name, DateTime $startAt, DateTime $endAt, int $memoryPeak, array $runWithTasks, array $extra): void;

    public function addLog(string $type, string $message, array $info = []): void;
}
