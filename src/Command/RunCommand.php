<?php

namespace Parallel\Command;

use Parallel\Parallel;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RunCommand extends Command
{
    /** @var Parallel */
    private $parallel;

    /**
     * @param Parallel $parallel
     */
    public function __construct(Parallel $parallel)
    {
        parent::__construct();
        $this->parallel = $parallel;
    }

    /**
     * Configure command
     */
    protected function configure(): void
    {
        $this->setName('parallel:run');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): ?int
    {
        $this->parallel->execute($input, $output);
        return 0;
    }
}
