<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TheFox\I8086emu\Machine\Machine;
use TheFox\I8086emu\Machine\TtyGraphic;

class RunCommand extends Command
{
    protected function configure()
    {
        $this->setName('run');

        $this->addOption('bios', 'b', InputOption::VALUE_REQUIRED, 'Path to bios.');
        $this->addOption('floppy', 'f', InputOption::VALUE_REQUIRED, 'Path to floppydisk-file.');
        $this->addOption('harddisk', 'd', InputOption::VALUE_REQUIRED, 'Path to harddisk-file.');
        $this->addOption('tty', 't', InputOption::VALUE_REQUIRED, 'Path to Screen TTY.');
        $this->addOption('socat', null, InputOption::VALUE_REQUIRED, 'Path to the socat binary.');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($input->hasOption('bios')) {
            $biosFilePath = $input->getOption('bios');
        }
        if ($input->hasOption('floppy')) {
            $floppyFilePath = $input->getOption('floppy');
        }
        //if ($input->hasOption('harddisk')) {
        //    $harddiskFilePath = $input->getOption('harddisk');
        //}
        if ($input->hasOption('tty')) {
            $ttyFilePath = $input->getOption('tty');
        }
        if ($input->hasOption('socat')) {
            $socatFilePath = $input->getOption('socat');
        }

        $machine = new Machine();
        $machine->setOutput($output);

        if (isset($biosFilePath)) {
            // $machine->setBiosFilePath($biosFilePath);

            $floppy=new Disk();
            $floppy->setSourceFilePath($biosFilePath);

            $machine->setBios($floppy);
        }
        if (isset($floppyFilePath)) {
            // $machine->setFloppyDiskFilePath($floppyFilePath);

            $floppy=new Disk();
            $floppy->setSourceFilePath($floppyFilePath);

            $machine->setFloppyDisk($floppy);
        }
        if (isset($harddiskFilePath)) {
            // $machine->setHardDiskFilePath($harddiskFilePath);

            $hardDisk=new Disk();
            $hardDisk->setSourceFilePath($harddiskFilePath);

            $machine->setFloppyDisk($hardDisk);
        }
        if (isset($ttyFilePath)) {
            $graphic = new TtyGraphic();
            $graphic->setTtyFilePath($ttyFilePath);
            if (isset($socatFilePath)&&$socatFilePath) {
                $graphic->setSocatFilePath($socatFilePath);
            }

            $machine->setGraphic($graphic);
        }

        $machine->run();

        return 0;
    }
}
