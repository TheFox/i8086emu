<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TheFox\I8086emu\Machine\Machine;

class RunCommand extends Command
{
    protected function configure()
    {
        $this->setName('run');

        $this->addOption('bios', 'b', InputOption::VALUE_REQUIRED, 'Path to bios.');
        $this->addOption('floppy', 'f', InputOption::VALUE_REQUIRED, 'Path to floppydisk-file.');
        $this->addOption('harddisk', 'd', InputOption::VALUE_REQUIRED, 'Path to harddisk-file.');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output):int
    {
        if ($input->hasOption('bios')) {
            $biosFilePath = $input->getOption('bios');
        }
        if ($input->hasOption('floppy')) {
            $floppyFilePath = $input->getOption('floppy');
        }
        if ($input->hasOption('harddisk')) {
            $harddiskFilePath = $input->getOption('harddisk');
        }

        $machine = new Machine();

        if (isset($biosFilePath)) {
            $machine->setBiosFilePath($biosFilePath);
        }
        if (isset($floppyFilePath)) {
            $machine->setFloppyDiskFilePath($floppyFilePath);
        }
        if (isset($harddiskFilePath)) {
            $machine->setHardDiskFilePath($harddiskFilePath);
        }

        $machine->run();

        return 0;
    }
}
