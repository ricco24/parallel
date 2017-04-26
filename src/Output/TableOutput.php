<?php

namespace Parallel\Output;

use Parallel\Helper\TimeHelper;
use Parallel\TaskData;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
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
        $output->writeln('Starting import ...');
    }

    /**
     * @param OutputInterface $output
     * @param array $data
     */
    public function printToOutput(OutputInterface $output, array $data): void
    {
        $this->clearScreen($output);
        $this->printTable($output, $data);
    }

    /**
     * @param OutputInterface $output
     * @param array $data
     * @param float $duration
     */
    public function finishMessage(OutputInterface $output, array $data, float $duration): void
    {
        $output->writeln("\nDuration => " . number_format($duration, 2) . " sec");
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
     * @param array $data
     */
    private function printTable(OutputInterface $output, array $data): void
    {
        $output->getFormatter()->setStyle('red', new OutputFormatterStyle('red'));
        $output->getFormatter()->setStyle('green', new OutputFormatterStyle('green'));

        $headers = ['Title', 'Total', 'Success', 'Skipped', 'Error', 'Warnings', 'Progress', 'Duration', 'Estimated'];

        $table = new TableHelper($output);
        $table
            ->setHeaders($headers);
        foreach ($data as $rowTitle => $row) {
            $table->addRow([
                $this->formatTitle($rowTitle, $row),
                number_format($row->getCount()),
                number_format($row->getExtra('success', 0)),
                number_format($row->getExtra('skip', 0)),
                number_format($row->getExtra('error', 0)),
                number_format($row->getCodeErrorsCount()),
                $this->progress($row->getProgress()),
                TimeHelper::formatTime($row->getDuration()),
                TimeHelper::formatTime($row->getEstimated())
            ]);
        }
        $table->render();
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
        if ($row->getProgress() != 100) {
            return "\xF0\x9F\x95\x92 " . $rowTitle;
        }

        if ($row->getExtra('success', 0) == $row->getCount()) {
            return $this->tag('green', "\xF0\x9F\x97\xB8 " . $rowTitle);
        }

        if ($row->getExtra('error', 0) != 0) {
            return $this->tag('red', "\xF0\x9F\x97\xB4 " . $rowTitle);
        }

        return $rowTitle;
    }
}
