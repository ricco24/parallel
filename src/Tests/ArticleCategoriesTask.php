<?php

namespace Parallel;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ArticleCategoriesTask extends Task
{
    private $loops = 10;

    private $identifier = 'articleCategory';

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $start = microtime(true);
        $fileName = '/var/www/Parallel/output/articleCategories.txt';

        for ($i = 1; $i <= $this->loops; $i++) {
            $estimated = round((microtime(true) - $start) / $i * $this->loops);

            $output->writeln('count:' . $this->loops . ';current:' . $i . ';duration:' . round(microtime(true) - $start) . ';estimated:' . $estimated);
            $content = file_exists($fileName) ? file_get_contents($fileName) : '';
            file_put_contents($fileName, $content . "\n" . $i . '-' . $this->identifier);
            sleep(1);
        }
    }
}
