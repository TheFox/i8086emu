<?php

/**
 * Machine create RAM, CPU, etc. and connects the components.
 */

namespace TheFox\I8086emu\Machine;

use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use TheFox\I8086emu\Blueprint\CpuInterface;
use TheFox\I8086emu\Blueprint\DebugAwareInterface;
use TheFox\I8086emu\Blueprint\DiskInterface;
use TheFox\I8086emu\Blueprint\MachineInterface;
use TheFox\I8086emu\Blueprint\OutputDeviceInterface;
use TheFox\I8086emu\Blueprint\RamInterface;
use TheFox\I8086emu\Exception\NoBiosException;
use TheFox\I8086emu\Exception\NoCpuException;
use TheFox\I8086emu\Exception\NoRamException;

final class Machine implements MachineInterface, DebugAwareInterface
{
    /**
     * @var string
     */
    private $biosFilePath;

    /**
     * @var Disk
     */
    private $bios;

    /**
     * @var string
     */
    private $floppyDiskFilePath;

    /**
     * @var Disk
     */
    private $floppyDisk;

    /**
     * @var string
     */
    private $hardDiskFilePath;

    /**
     * @var Disk
     */
    private $hardDisk;

    /**
     * @var Ram
     */
    private $ram;

    /**
     * @var Cpu
     */
    private $cpu;

    /**
     * @var NullOutputDevice|TtyOutputDevice
     */
    private $tty;

    /**
     * @var OutputInterface
     */
    private $output;

    public function __construct()
    {
        $this->ram = new Ram(0x00100000); // 1 MB
        $this->cpu = new Cpu($this);
        $this->tty = new NullOutputDevice();

        $this->output = new NullOutput();
    }

    public function run(): void
    {
        if (!$this->ram || !$this->ram instanceof RamInterface) {
            throw new NoRamException();
        }

        if (!$this->cpu || !$this->cpu instanceof CpuInterface) {
            throw new NoCpuException();
        }

        if (!$this->bios) {
            throw new NoBiosException();
        }

        // Load BIOS into RAM.
        $biosOffset = (0xF000 << 4) + 0x0100;
        $biosLen = 0xFF00;
        $biosEnd = $biosOffset + $biosLen;
        $this->output->writeln(sprintf('[MACHINE] bios start %08x', $biosOffset));
        $this->output->writeln(sprintf('[MACHINE] bios end   %08x', $biosEnd));
        $data = $this->bios->getContent($biosLen);
        $this->ram->write($data, $biosOffset, $biosLen);

        // Setup CPU.
        $this->output->writeln('[MACHINE] set ram');
        $this->cpu->setRam($this->ram);

        // Setup TTY.
        $this->output->writeln('[MACHINE] set TTY');
        $this->cpu->setTty($this->tty);

        // Run the CPU.
        $this->cpu->run();
    }

    /**
     * @param Disk $bios
     */
    public function setBios(Disk $bios): void
    {
        $this->bios = $bios;
    }

    /**
     * @param Disk $floppyDisk
     */
    public function setFloppyDisk(Disk $floppyDisk): void
    {
        $this->floppyDisk = $floppyDisk;
    }

    /**
     * @param Disk $hardDisk
     */
    public function setHardDisk(Disk $hardDisk): void
    {
        $this->hardDisk = $hardDisk;
    }

    public function setTty(OutputDeviceInterface $tty): void
    {
        $this->tty = $tty;
    }

    /**
     * @param OutputInterface $output
     */
    public function setOutput(OutputInterface $output): void
    {
        $this->output = $output;

        $this->cpu->setOutput($this->output);
    }

    public function getDiskByNum(int $diskId): DiskInterface
    {
        switch ($diskId) {
            case 2:
                return $this->hardDisk;
            case 1:
                return $this->floppyDisk;
            case 0:
                return $this->bios;
        }
    }
}
