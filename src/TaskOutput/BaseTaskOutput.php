<?php

namespace Parallel\TaskOutput;

use Symfony\Component\Console\Output\OutputInterface;

class BaseTaskOutput implements TaskOutput
{
    /**
     * @param OutputInterface $output
     * @param array $data
     */
    public function write(OutputInterface $output, array $data): void
    {
        $string = '';
        foreach ($data as $key => $value) {
            $string .= empty($string) ? "$key:$value" : ";$key:$value";
        }

        $output->writeln($string);
    }
}
