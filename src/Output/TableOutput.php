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
use Symfony\Component\Console\Helper\TableCellStyle;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\ConsoleSectionOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class TableOutput implements Output
{
    private const PROGRESS_WIDTH = 16;
    private const DEFAULT_HEIGHT = 20;

    private int $minHeight;
    private float $lastOverwrite = 0;

    private OutputInterface $output;

    private BufferedOutput $buffer;

    private SymfonyStyle $io;

    private ?ConsoleSectionOutput $section = null;

    private TableHelper $stackedTable;

    private TableHelper $mainTable;

    public function __construct(?int $minHeight = null)
    {
        $this->minHeight = max(self::DEFAULT_HEIGHT, $minHeight ?? 0);
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
            '<fg=green;options=bold>OK</>',
            'All',
            '<yellow>Skip</>',
            '<fg=red;options=bold>Err</>',
            '<fg=yellow;options=bold>Wrn</>',
            'Time',
            'Memory',
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
                implode(' <yellow>|</yellow> ', $waitingFor),
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
        $taskWidth = max(array_map('strlen', array_keys($all))) + 2;
        $table->setColumnWidth(0, $taskWidth);

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
            $this->renderTasks($table, $done, $avgMemoryUsage, $total, false);
        } else {
            $this->renderTasks($table, $running, $avgMemoryUsage, $total, true);
        }

        $table->addRow(new TableSeparator());
        $cDone = count($done) + $total['progress'];
        $table->addRow([
            'Total',
            $this->progress(100 * $cDone / count($all)),
            $this->numCell(count($done)),
            $this->numCell(count($all), 'left'),
            $this->numCell($total['skip']),
            $this->numCell($total['error']),
            $this->numCell($total['code_errors']),
            TimeHelper::formatTime($elapsedTime),
            DataHelper::convertBytes($total['memory']),
        ]);

        $table->render();
    }

    /**
     * Filter tasks array
     * @param TaskData[] $data
     * @return array{TaskData[], TaskData[], TaskData[]}
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
    private function renderTasks(Table $table, array $rows, int $avgMemoryUsage, array &$total, bool $running): void
    {
        $errorRows = [];
        foreach ($rows as $rowTitle => $row) {
            $time = TimeHelper::formatTime($row->getDuration());
            if (!$running) {
                $time = str_pad($time, 5, ' ', STR_PAD_LEFT);
                $time .= ' │ ' . $row->getStackedTask()->getFinishedAt()->format('H:i:s');
            }

            $tRow = [
                $this->taskTitle($rowTitle, $row),
                $this->progress($row->getProgress()),
                $this->numCell($row->getExtra('success', 0)),
                $this->numCell($row->getCount(), 'left'),
                $this->numCell($row->getExtra('skip', 0)),
                $this->numCell($row->getExtra('error', 0)),
                $this->numCell($row->getCodeErrorsCount()),
                $time,
                $this->formatMemory($row, $avgMemoryUsage, !$running),
            ];

            if ($row->getExtra('error', 0) && $row->getExtra('message', '')) {
                $rowMessage = $row->getExtra('message', '');
                $errorRows[] = new TableSeparator();
                $errorRows[] = $tRow;
                $errorRows[] = [new TableCell("<red>$rowMessage</>", ['colspan' => 8])];
            } else {
                $table->addRow($tRow);
            }

            $total['count'] += $row->getCount();
            $total['progress'] += $row->getProgress() / 100;
            $total['success'] += $row->getExtra('success', 0);
            $total['skip'] += $row->getExtra('skip', 0);
            $total['error'] += $row->getExtra('error', 0);
            $total['code_errors'] += $row->getCodeErrorsCount();
            $total['duration'] += $row->getDuration();
            $total['memory'] += $row->getMemoryUsage();
        }

        if ($errorRows !== []) {
            $table->addRows($errorRows);
        }
        for ($i = count($rows); $i < $this->minHeight; $i++) {
            $table->addRow(['', '', '', '', '', '', '', '', '']);
        }
    }

    private function progress(float $percent): string
    {
        $percent = min($percent, 100);
        $fullBlocks = floor($percent / 100 * self::PROGRESS_WIDTH);
        $partialBlock = fmod($percent / 100 * self::PROGRESS_WIDTH, 1);

        $chars = [' ', '▏', '▎', '▍', '▌', '▋', '▊', '▉', '█'];
        return '<fg=green;bg=gray>' .
            str_repeat($chars[8], $fullBlocks) .
            ($partialBlock > 0 ? $chars[round($partialBlock * 8)] : '') .
            str_repeat(' ', self::PROGRESS_WIDTH - $fullBlocks - ($partialBlock > 0 ? 1 : 0)) .
            '</>' .
            str_pad($this->numf($percent), 5, ' ', STR_PAD_LEFT) . '%';
    }

    private function numf(float $num): string
    {
        return number_format($num, 0, '.', ' ');
    }

    private function numCell(float $num, string $align = 'right'): TableCell
    {
        return new TableCell($this->numf($num),
            ['style' => new TableCellStyle(['align' => $align, 'options' => 'bold'])]);
    }

    public function taskTitle(string $title, TaskData $row): string
    {
        if ($row->getExtra('error', 0) > 0) {
            return "<fg=red;options=bold>× $title</>";
        } elseif ($row->getExtra('skip', 0) > 0 || $row->getCodeErrorsCount() > 0) {
            return "<fg=yellow;options=bold>⚠ $title</>";
        } elseif ($row->getCount() <= 0) {
            return "<fg=gray;options=bold>  $title</>";
        } elseif ($row->getCurrent() === $row->getCount()) {
            return "<fg=green;options=bold>✓ $title</>";
        }

        return "<options=bold>  $title</>";
    }

    private function formatMemory(TaskData $taskData, int $avgMemory, bool $full = false): string
    {
        $memoryIndex = $taskData->getMemoryPeak() / $avgMemory;
        $text = DataHelper::convertBytes($taskData->getMemoryPeak());
        if ($full) {
            $text = DataHelper::convertBytes($taskData->getMemoryUsage()) . '/' . $text;
        }

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
