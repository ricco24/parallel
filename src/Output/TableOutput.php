<?php

namespace Parallel\Output;

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
    public function print(OutputInterface $output, array $data): void
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
                number_format($row['count']),
                isset($row['other']['success']) ? number_format($row['other']['success']) : '?',
                isset($row['other']['skip']) ? number_format($row['other']['skip']) : '?',
                isset($row['other']['error']) ? number_format($row['other']['error']) : '?',
                isset($row['code_errors_count']) ? number_format($row['code_errors_count']) : 0,
                isset($row['progress']) ? $this->progress($row['progress']) : '?',
                isset($row['duration']) ? $this->formatTime($row['duration']) : '?',
                isset($row['estimated']) ? $this->formatTime($row['estimated']): '?'
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
        return $result . ' ' . $progress. '%';
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
     * @param array $row
     * @return string
     */
    private function formatTitle(string $rowTitle, array $row): string
    {
        if (!isset($row['progress']) || (isset($row['progress']) && $row['progress'] != 100)) {
            return "\xF0\x9F\x95\x92 " . $rowTitle;
        }

        if (isset($row['other']['success']) && $row['other']['success'] == $row['count']) {
            return $this->tag('green', "\xF0\x9F\x97\xB8 " . $rowTitle);
        }

        if (isset($row['other']['error']) && $row['other']['error'] != 0) {
            return $this->tag('red', "\xF0\x9F\x97\xB4 " . $rowTitle);
        }

        return $rowTitle;
    }

    /**
     * @param int $seconds
     * @return string
     */
    private function formatTime(int $seconds): string
    {
        $hours = 0;
        $minutes = 0;

        if ($seconds > 3600) {
            $hours = floor($seconds / 3600);
            $seconds -= $hours * 3600;
        }

        if ($seconds > 60) {
            $minutes = floor($seconds / 60);
            $seconds -= $minutes * 60;
        }

        if ($hours > 0) {
            return $hours . 'h ' . $minutes . 'm ' . $seconds . 's';
        }

        if ($minutes > 0) {
            return $minutes . 'm ' . $seconds . 's';
        }

        return $seconds . 's';
    }
}
