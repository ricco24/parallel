<?php

namespace Parallel\Command;

use Parallel\Parallel;
use Parallel\TaskStack\StackedTask;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AnalyzeGraphCommand extends Command
{
    /** @var Parallel */
    private $parallel;

    /** @var string */
    private $analyzeDirectory;

    /**
     * @param Parallel $parallel
     */
    public function __construct(Parallel $parallel)
    {
        parent::__construct();
        $this->parallel = $parallel;
    }

    /**
     * @param string $directory
     */
    public function setAnalyzeDir(string $directory)
    {
        $this->analyzeDirectory = rtrim($directory, '/');
    }

    /**
     * Configure command
     */
    protected function configure(): void
    {
        $this->setName('analyze:graph');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): ?int
    {
        if (!$this->analyzeDirectory) {
            $output->writeln('<error>Analyze directory is not set</error>');
            return 1;
        }

        /** @var StackedTask[] $stackedTasks */
        $stackedTasks = $this->parallel->getTaskStack()->getStackedTasks();

        $tasks = '';
        foreach ($stackedTasks as $stackedTask) {
            foreach ($stackedTask->getRunAfter() as $runAfter) {
                $tasks .= "['" . $runAfter . "', '" . $stackedTask->getTask()->getName() . "', 1], ";
            }
        }

        $graphHtml = file_get_contents(__DIR__ . '/../Analyze/Template/graph.html');
        if ($graphHtml === false) {
            $output->writeln('<error>Cannot read graph template file</error>');
            return 1;
        }

        $graphHtml = str_replace('{{PLACEHOLDER}}', $tasks, $graphHtml);

        if (file_put_contents($this->analyzeDirectory . '/graph.html', $graphHtml) === false) {
            $output->writeln('<error>Cannot write graph to file</error>');
            return 1;
        }

        $output->writeln('Graph generated');
        return 0;
    }
}
