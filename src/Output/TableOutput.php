<?php

namespace Parallel\Output;

use Parallel\Helper\DataHelper;
use Parallel\Helper\TimeHelper;
use Parallel\TaskData;
use Parallel\TaskStack\StackedTask;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table as TableHelper;
use Symfony\Component\Process\Process;

class TableOutput implements Output
{
    /**
     * @param OutputInterface $output
     */
    public function startMessage(OutputInterface $output): void
    {
        $output->writeln('Starting parallel task processing ...');
    }

    /**
     * @param OutputInterface $output
     * @param array $data
     * @param float $elapsedTime
     */
    public function printToOutput(OutputInterface $output, array $data, float $elapsedTime): void
    {
        $output->getFormatter()->setStyle('red', new OutputFormatterStyle('red'));
        $output->getFormatter()->setStyle('green', new OutputFormatterStyle('green'));
        $output->getFormatter()->setStyle('yellow', new OutputFormatterStyle('yellow'));

        $this->clearScreen($output);

        list($stacked, $running, $done) = $this->filterTasks($data);
        $this->renderStackedTable($output, $stacked, $running);
        $this->renderMainTable($output, $data, $running, $done, $elapsedTime);
    }

    /**
     * @param OutputInterface $output
     * @param array $data
     * @param float $duration
     */
    public function finishMessage(OutputInterface $output, array $data, float $duration): void
    {
        $output->writeln('');
    }

    /**
     * @param OutputInterface $output
     */
    private function clearScreen(OutputInterface $output): void
    {
        $process = new Process('clear');
        $process->run();
        $output->writeln($process->getOutput());
    }

    /**
     * @param OutputInterface $output
     * @param TaskData[] $stacked
     * @param TaskData[] $running
     */
    private function renderStackedTable(OutputInterface $output, array $stacked, array $running): void
    {
        if (!$output->isDebug()) {
            return;
        }

        if (!count($stacked)) {
            return;
        }

        $headers = ['Title', 'Waiting for'];
        $table = new TableHelper($output);
        $table
            ->setHeaders($headers);

        foreach ($stacked as $rowTitle => $row) {
            // Mark currently running tasks
            $waitingFor = [];
            foreach ($row->getStackedTask()->getCurrentRunAfter() as $runAfter) {
                if (in_array($runAfter, array_keys($running))) {
                    $waitingFor[] = '<green>' . $runAfter . '</green>';
                    continue;
                }
                $waitingFor[] = $runAfter;
            }

            $table->addRow([
                $this->formatTitle($rowTitle, $row),
                implode(' <yellow>|</yellow> ', $waitingFor)
            ]);
        }

        $table->render();
    }

    /**
     * @param OutputInterface $output
     * @param TaskData[] $all
     * @param TaskData[] $running
     * @param TaskData[] $done
     * @param float $elapsedTime
     */
    private function renderMainTable(OutputInterface $output, array $all, array $running, array $done, float $elapsedTime): void
    {

        $headers = ['Title', 'Total', 'Success', 'Skipped', 'Error', 'Warnings', 'Progress', 'Time', 'Memory', 'Message'];
        $table = new TableHelper($output);
        $table
            ->setHeaders($headers);

        $total = [
            'count' => 0,
            'success' => 0,
            'skip' => 0,
            'error' => 0,
            'code_errors' => 0,
            'duration' => 0
        ];

        $avgMemoryUsage = $this->getAvgMemoryUsage(array_merge($running, $done));
        $this->renderDoneTasks($table, $done, $avgMemoryUsage, $total);
        $this->renderRunningTasks($table, $running, $avgMemoryUsage, $total);

        $table->addRow([
            'Total (' . count($done) . '/' . count($all) . ')',
            number_format($total['count']),
            number_format($total['success']),
            number_format($total['skip']),
            number_format($total['error']),
            number_format($total['code_errors']),
            'Saved time: ' . TimeHelper::formatTime(max($total['duration'] - (int) $elapsedTime, 0)),
            TimeHelper::formatTime($elapsedTime),
            '',
            ''
        ]);

        $table->render();
    }

    /**
     * Filter tasks array
     * @param array $data
     * @return array
     */
    private function filterTasks(array $data): array
    {
        $done = $stacked = $running = [];
        foreach ($data as $taskTitle => $taskData) {
            if ($taskData->getStackedTask()->isInStatus(StackedTask::STATUS_DONE)) {
                $done[$taskTitle] = $taskData;
            } elseif ($taskData->getStackedTask()->isInStatus(StackedTask::STATUS_STACKED)) {
                $stacked[$taskTitle] = $taskData;
            } elseif ($taskData->getStackedTask()->isInStatus(StackedTask::STATUS_RUNNING)) {
                $running[$taskTitle] = $taskData;
            }
        }

        return [$stacked, $running, $done];
    }

    /**
     * @param TableHelper $table
     * @param TaskData[] $rows
     * @param int $avgMemoryUsage
     * @param array $total
     */
    private function renderRunningTasks(Table $table, array $rows, int $avgMemoryUsage, array &$total): void
    {
        foreach ($rows as $rowTitle => $row) {
            $table->addRow([
                $this->formatTitle($rowTitle, $row),
                number_format($row->getCount()),
                number_format($row->getExtra('success', 0)),
                number_format($row->getExtra('skip', 0)),
                number_format($row->getExtra('error', 0)),
                number_format($row->getCodeErrorsCount()),
                $this->progress($row->getProgress()),
                TimeHelper::formatTime($row->getDuration()) . ' / ' . TimeHelper::formatTime($row->getEstimated()),
                $this->formatMemory($row, $avgMemoryUsage),
                $row->getExtra('message', '')
            ]);

            $total['count'] += $row->getCount();
            $total['success'] += $row->getExtra('success', 0);
            $total['skip'] += $row->getExtra('skip', 0);
            $total['error'] += $row->getExtra('error', 0);
            $total['code_errors'] += $row->getCodeErrorsCount();
            $total['duration'] += $row->getDuration();
        }

        if (count($rows)) {
            $table->addRow(new TableSeparator());
        }
    }

    /**
     * @param TableHelper $table
     * @param TaskData[] $rows
     * @param int $avgMemoryUsage
     * @param array $total
     */
    private function renderDoneTasks(Table $table, array $rows, int $avgMemoryUsage, array &$total): void
    {
        foreach ($rows as $rowTitle => $row) {
            $table->addRow([
                $this->formatTitle($rowTitle, $row),
                number_format($row->getCount()),
                number_format($row->getExtra('success', 0)),
                number_format($row->getExtra('skip', 0)),
                number_format($row->getExtra('error', 0)),
                number_format($row->getCodeErrorsCount()),
                $this->progress($row->getProgress()),
                TimeHelper::formatTime($row->getDuration()),
                $this->formatMemory($row, $avgMemoryUsage),
                $row->getStackedTask()->getFinishedAt() ? 'Finished at: ' . $row->getStackedTask()->getFinishedAt()->format('H:i:s') : ''
            ]);

            $total['count'] += $row->getCount();
            $total['success'] += $row->getExtra('success', 0);
            $total['skip'] += $row->getExtra('skip', 0);
            $total['error'] += $row->getExtra('error', 0);
            $total['code_errors'] += $row->getCodeErrorsCount();
            $total['duration'] += $row->getDuration();
        }

        if (count($rows)) {
            $table->addRow(new TableSeparator());
        }
    }

    /**
     * @param float $progress
     * @param int $maxSteps
     * @return string
     */
    private function progress(float $progress, int $maxSteps = 20): string
    {
        $result = '';
        $roundedProgress = round($progress / (100 / $maxSteps));
        for ($i = 1; $i <= $maxSteps; $i++) {
            if ($roundedProgress > $i || $roundedProgress == $maxSteps) {
                $result .= '=';
            } elseif ($roundedProgress == $i) {
                $result .= '>';
            } else {
                $result .= '-';
            }
        }
        return $result . ' ' . number_format($progress) . '%';
    }

    /**
     * @param string $tag
     * @param string $data
     * @return string
     */
    private function tag(string $tag, string $data): string
    {
        return '<' . $tag . '>' . $data . '</' . $tag . '>';
    }

    /**
     * @param string $rowTitle
     * @param TaskData $row
     * @return string
     */
    private function formatTitle(string $rowTitle, TaskData $row): string
    {
        if (!$row->getStackedTask()->isInStatus(StackedTask::STATUS_DONE)) {
            return $rowTitle;
        }

        if ($row->getExtra('error', 0) != 0) {
            return $this->tag('red', "\xE2\x9C\x96 " . $rowTitle);
        }

        if ($row->getCodeErrorsCount() != 0) {
            return $this->tag('yellow', "\xE2\x9C\x96 " . $rowTitle);
        }

        if ($row->getExtra('success', 0) + $row->getExtra('skip', 0) == $row->getCount()) {
            return $this->tag('green', "\xE2\x9C\x94 " . $rowTitle);
        }

        return $rowTitle;
    }

    /**
     * @param TaskData $taskData
     * @param int $maxMemory
     * @return string
     */
    private function formatMemory(TaskData $taskData, int $maxMemory): string
    {
        $memoryIndex = $taskData->getMemoryPeak()/$maxMemory;
        $text = DataHelper::convertBytes($taskData->getMemoryUsage()) . ' (' . DataHelper::convertBytes($taskData->getMemoryPeak()) . ')';

        if ($memoryIndex > 3) {
            return "<red>$text</red>";
        } elseif ($memoryIndex > 2) {
            return "<yellow>$text</yellow>";
        }

        return "$text";
    }

    /**
     * @param TaskData[] $data
     * @return int
     */
    private function getAvgMemoryUsage(array $data): int
    {
        $memory = 0;
        $count = 0;
        foreach ($data as $taskData) {
            $memory += $taskData->getMemoryPeak();
            $count++;
        }

        if ($count === 0) {
            return 0;
        }

        return (int) $memory/$count;
    }
}
