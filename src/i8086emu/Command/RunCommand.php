<?php

namespace TheFox\i8086emu\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class RunCommand extends Command
{
    protected function configure()
    {
        parent::configure();

        $this->setName('run');

        $this->addOption('floppy','f',InputOption::VALUE_REQUIRED,'Path to floppy disk.');
        $this->addOption('harddisk','d',InputOption::VALUE_REQUIRED,'Path to harddisk.');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function setup(InputInterface $input, OutputInterface $output)
    {

    }
}
