<?php

/**
 * Machine create RAM, CPU, etc. and connects the components.
 */

namespace TheFox\I8086emu\Machine;

use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use TheFox\I8086emu\Blueprint\CpuInterface;
use TheFox\I8086emu\Blueprint\MachineInterface;
use TheFox\I8086emu\Blueprint\OutputAwareInterface;
use TheFox\I8086emu\Blueprint\RamInterface;
use TheFox\I8086emu\Exception\NoBiosException;
use TheFox\I8086emu\Exception\NoCpuException;
use TheFox\I8086emu\Exception\NoRamException;

class Machine implements MachineInterface, OutputAwareInterface
{
    /**
     * @var string
     */
    private $biosFilePath;

    /**
     * @var string
     */
    private $floppyDiskFilePath;

    /**
     * @var string
     */
    private $hardDiskFilePath;

    /**
     * @var Ram
     */
    private $ram;

    /**
     * @var Cpu
     */
    private $cpu;

    /**
     * @var OutputInterface
     */
    private $output;

    public function __construct()
    {
        $this->ram = new Ram(0x00100000); // 1 MB
        $this->cpu = new Cpu();
        $this->output = new NullOutput();
    }

    public function run()
    {
        if (!$this->ram || !$this->ram instanceof RamInterface) {
            throw new NoRamException();
        }

        if (!$this->cpu || !$this->cpu instanceof CpuInterface) {
            throw new NoCpuException();
        }

        if (!$this->biosFilePath) {
            throw new NoBiosException();
        }
        if ($this->hardDiskFilePath) {
            throw new \RuntimeException('HDD file not implemented');
        }

        // Load BIOS into RAM.
        $biosPos = (0xF000 << 4) + 0x0100; // @todo
        $biosLen = 0xFF00;
        $this->ram->loadFromFile($this->biosFilePath, $biosPos, $biosLen);

        printf("bios start %08x\n", $biosPos);
        printf("bios end   %08x\n", $biosPos + $biosLen);

        // Setup CPU.
        $this->cpu->setRam($this->ram);

        // Run the CPU.
        $this->cpu->run();
    }

    /**
     * @param string $biosFilePath
     */
    public function setBiosFilePath(string $biosFilePath)
    {
        $this->biosFilePath = $biosFilePath;
    }

    /**
     * @param string $floppyDiskFilePath
     */
    public function setFloppyDiskFilePath(string $floppyDiskFilePath)
    {
        $this->floppyDiskFilePath = $floppyDiskFilePath;
    }

    /**
     * @param OutputInterface $output
     */
    public function setOutput(OutputInterface $output)
    {
        $this->output = $output;

        $this->cpu->setOutput($this->output);
    }
}
