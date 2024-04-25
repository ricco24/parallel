<?php

namespace Parallel\Output;

use Parallel\Helper\DataHelper;
use Parallel\Helper\TimeHelper;
use Parallel\TaskData;
use Parallel\TaskStack\StackedTask;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\Table as TableHelper;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\ConsoleSectionOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class TableOutput implements Output
{
    private float $lastOverwrite = 0;

    private OutputInterface $output;
    private BufferedOutput $buffer;
    private SymfonyStyle $io;
    private ?ConsoleSectionOutput $section = null;

    private TableHelper $stackedTable;
    private TableHelper $mainTable;

    public function __construct(?int $doneTasksRows = null)
    {
        $this->doneTasksRows = $doneTasksRows;
    }

    public function setOutput(OutputInterface $output): void
    {
        $output->getFormatter()->setStyle('red', new OutputFormatterStyle('red'));
        $output->getFormatter()->setStyle('green', new OutputFormatterStyle('green'));
        $output->getFormatter()->setStyle('yellow', new OutputFormatterStyle('yellow'));

        $this->output = $output;
        $this->buffer = new BufferedOutput($output->getVerbosity(), $output->isDecorated(), $output->getFormatter());
        $this->io = new SymfonyStyle(new StringInput(''), $output);
        if ($output instanceof ConsoleOutputInterface) {
            $this->section = $output->section();
        }


        $this->mainTable = new TableHelper($this->buffer);
        $this->mainTable->setStyle('box-double');
        $this->mainTable->getStyle()->setCellHeaderFormat('<options=bold>%s</>');
        $this->mainTable->setHeaders([
            'Task',
            'Progress',
            'Count',
            '<yellow>Skip</>',
            '<fg=red;options=bold>Err</>',
            '<fg=yellow;options=bold>Wrn</>',
            'Time',
            'Memory'
        ])->setColumnWidths([1, 1, 1, 1, 1, 1, 1]);


        $this->stackedTable = new TableHelper($this->buffer);
        $this->stackedTable
            ->setHeaders(['Title', 'Waiting for']);
    }

    public function startMessage(): void
    {
    }

    public function errorMessage(string $error): void
    {
        $this->io->error($error);
    }

    public function printToOutput(array $data, float $elapsedTime): void
    {
        if (microtime(true) - $this->lastOverwrite < 0.1) {
            return;
        }

        list($stacked, $running, $done) = $this->filterTasks($data);
        if ($this->output->isDebug()) {
            $this->renderStackedTable($stacked, $running);
        }
        $this->renderMainTable($data, $running, $done, $elapsedTime);

        if ($this->section !== null) {
            $this->section->overwrite($this->buffer->fetch() . "\n");
        } else {
            $this->output->writeln(["\033[2J\033[;H", $this->buffer->fetch()]);
        }
        $this->lastOverwrite = microtime(true);
    }

    public function finishMessage(array $data, float $duration): void
    {
        $this->lastOverwrite = 0;
        $this->printToOutput($data, $duration);

        $this->io->success(['Finished in ' . TimeHelper::formatTime($duration)]);
    }

    /**
     * @param TaskData[] $stacked
     * @param TaskData[] $running
     */
    private function renderStackedTable(array $stacked, array $running): void
    {
        if (!count($stacked)) {
            return;
        }

        $table = $this->stackedTable;
        $table->setRows([]);

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
                $rowTitle,
                implode(' <yellow>|</yellow> ', $waitingFor)
            ]);
        }

        $table->render();
    }

    /**
     * @param TaskData[] $all
     * @param TaskData[] $running
     * @param TaskData[] $done
     */
    private function renderMainTable(array $all, array $running, array $done, float $elapsedTime): void
    {
        $table = $this->mainTable;
        $table->setRows([]);

        $total = [
            'count' => 0,
            'success' => 0,
            'skip' => 0,
            'error' => 0,
            'code_errors' => 0,
            'duration' => 0,
            'memory' => 0,
            'progress' => 0,
        ];

        $avgMemoryUsage = $this->getAvgMemoryUsage(array_merge($running, $done));
        if (count($done) === count($all)) {
            $this->renderDoneTasks($table, $done, $avgMemoryUsage, $total);
        } else {
            $this->renderRunningTasks($table, $running, $avgMemoryUsage, $total);
        }

        $table->addRow(new TableSeparator());
        $cDone = count($done) + $total['progress'];
        $table->addRow([
            'Total',
            $this->progress(100 * $cDone / count($all)),
            number_format(count($done)) . '/' . number_format(count($all)),
            number_format($total['skip']),
            number_format($total['error']),
            number_format($total['code_errors']),
            TimeHelper::formatTime($elapsedTime),
            DataHelper::convertBytes($total['memory']),
        ]);

        $table->render();
    }

    /**
     * Filter tasks array
     * @param TaskData[] $data
     * @return TaskData[]
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
            $count = $row->getExtra('success', 0) . '/' . $row->getCount();
            if ($count === '0/0') {
                $count = str_repeat(' ', 13);
            } else {
                $count = str_pad($count, max(13, strlen("{$row->getCount()}") * 2 + 1), ' ', STR_PAD_LEFT);
            }

            $table->addRow([
                "<options=bold>" . $rowTitle . "</>",
                $this->progress($row->getProgress()),
                $count,
                number_format($row->getExtra('skip', 0)),
                number_format($row->getExtra('error', 0)),
                number_format($row->getCodeErrorsCount()),
                TimeHelper::formatTime($row->getDuration()),
                $this->formatMemory($row, $avgMemoryUsage)
            ]);

            $total['count'] += $row->getCount();
            $total['progress'] += $row->getProgress() / 100;
            $total['success'] += $row->getExtra('success', 0);
            $total['skip'] += $row->getExtra('skip', 0);
            $total['error'] += $row->getExtra('error', 0);
            $total['code_errors'] += $row->getCodeErrorsCount();
            $total['duration'] += $row->getDuration();
            $total['memory'] += $row->getMemoryUsage();
        }
    }

    /**
     * @param TableHelper $table
     * @param TaskData[] $rows
     */
    private function renderDoneTasks(Table $table, array $rows, int $avgMemoryUsage, array &$total): void
    {
        $count = count($rows);

        $errorRows = [];
        foreach ($rows as $rowTitle => $row) {
            $trow = [
                "<options=bold>$rowTitle</>",
                $this->progress($row->getProgress()),
                $row->getExtra('success', 0) . '/' . $row->getCount(),
                number_format($row->getExtra('skip', 0)),
                number_format($row->getExtra('error', 0)),
                number_format($row->getCodeErrorsCount()),
                str_pad(TimeHelper::formatTime($row->getDuration()),4) . ' │ ' . $row->getStackedTask()->getFinishedAt()->format('H:i:s'),
                $this->formatMemory($row, $avgMemoryUsage)
            ];

            $rowMessage = '';
            if ($row->getExtra('error', 0) && $row->getExtra('message', '')) {
                $trow[0] = "<fg=red;options=bold>$rowTitle</>";
                $rowMessage = $row->getExtra('message', '');
                $errorRows[] = new TableSeparator();
                $errorRows[] = $trow;
                $errorRows[] = [new TableCell("<red>$rowMessage</>", ['colspan' => 8])];
            } else {
                $table->addRow($trow);
            }

            $total['count'] += $row->getCount();
            $total['success'] += $row->getExtra('success', 0);
            $total['skip'] += $row->getExtra('skip', 0);
            $total['error'] += $row->getExtra('error', 0);
            $total['code_errors'] += $row->getCodeErrorsCount();
            $total['duration'] += $row->getDuration();
        }
        if ($errorRows !== []) {
            $table->addRows($errorRows);
        }
    }


    private function progress(float $percent): string
    {
        $width = 20;
        $percent = min($percent, 100);
        $fullBlocks = floor($percent / 100 * $width);
        $partialBlock = fmod($percent / 100 * $width, 1);

        $chars = [' ', '▏', '▎', '▍', '▌', '▋', '▊', '▉', '█'];
        return "<fg=green>" .
            str_repeat($chars[8], $fullBlocks) .
            ($partialBlock > 0 ? $chars[round($partialBlock * 8)] : '') .
            '</>' .
            str_repeat('·', $width - $fullBlocks - ($partialBlock > 0 ? 1 : 0)) .
            str_pad(number_format($percent), 5, ' ', STR_PAD_LEFT) . '%';
    }

    /**
     * @param TaskData $taskData
     */
    private function formatMemory(TaskData $taskData, int $maxMemory): string
    {
        $memoryIndex = $taskData->getMemoryPeak() / $maxMemory;
        $text = DataHelper::convertBytes($taskData->getMemoryUsage()) . '/' . DataHelper::convertBytes($taskData->getMemoryPeak());

        if ($memoryIndex > 3) {
            return "<red>$text</>";
        }
        if ($memoryIndex > 2) {
            return "<yellow>$text</>";
        }

        return "$text";
    }

    /**
     * @param TaskData[] $data
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

        return floor($memory / $count);
    }
}
