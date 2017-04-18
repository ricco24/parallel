<?php

namespace Parallel\Output;

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
        $output->writeln('Import');
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
        $table = new TableHelper($output);
        $table
            ->setHeaders(['Title', 'Count', 'Current', 'Progress', 'Duration', 'Estimated']);
        foreach ($data as $rowTitle => $row) {
            $table->addRow([$rowTitle, $row['count'], $row['current'], $this->progress($row['progress']), $row['duration'] . ' sec', $row['estimated'] . ' sec']);
        }
        $table->render();
    }

    private function progress(float $progress, $maxSteps = 20)
    {
//        $row['progress'] == 100 ? 'Done' : $row['progress'] . '%'

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
        return $result . ' ' . $progress . '%';
    }
}
