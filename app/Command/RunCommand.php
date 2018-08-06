<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TheFox\I8086emu\Machine\Disk;
use TheFox\I8086emu\Machine\Machine;
use TheFox\I8086emu\Machine\TtyOutputDevice;

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
            $biosDisk = new Disk('bios');
            $biosDisk->setFilePath($biosFilePath);

            $machine->setBios($biosDisk);
        }
        if (isset($floppyFilePath)) {
            $floppyDisk = new Disk('floppy');
            $floppyDisk->setFilePath($floppyFilePath);

            $machine->setFloppyDisk($floppyDisk);
        }
        if (isset($harddiskFilePath)) {
            $hardDisk = new Disk('hdd');
            $hardDisk->setFilePath($harddiskFilePath);

            $machine->setHardDisk($hardDisk);
        }
        if (isset($ttyFilePath)) {
            $tty = new TtyOutputDevice();
            $tty->setTtyFilePath($ttyFilePath);

            if (isset($socatFilePath) && $socatFilePath) {
                $tty->setSocatFilePath($socatFilePath);
            }

            $machine->setTty($tty);
        }

        $machine->run();

        return 0;
    }
}
