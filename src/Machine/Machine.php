<?php

/**
 * Machine create RAM, CPU, etc. and connects the components.
 */

namespace TheFox\I8086emu\Machine;

use TheFox\I8086emu\Blueprint\CpuInterface;
use TheFox\I8086emu\Blueprint\RamInterface;
use TheFox\I8086emu\Exception\NoBiosException;
use TheFox\I8086emu\Exception\NoCpuException;
use TheFox\I8086emu\Exception\NoRamException;

class Machine
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

    public function __construct()
    {
        $this->ram = new Ram();
        $this->cpu = new Cpu();
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

        // Load BIOS into RAM.
        $biosPos = 0;// @todo
        $this->ram->loadFile($this->biosFilePath, $biosPos);

        // Setup CPU.
        $this->cpu->setRam($this->ram);

        // Run the CPU.
        $this->cpu->run();
    }

    /**
     * @return string|null
     */
    public function getBiosFilePath(): ?string
    {
        return $this->biosFilePath;
    }

    /**
     * @param string $biosFilePath
     */
    public function setBiosFilePath(string $biosFilePath)
    {
        $this->biosFilePath = $biosFilePath;
    }

    /**
     * @return string
     */
    public function getFloppyDiskFilePath(): string
    {
        return $this->floppyDiskFilePath;
    }

    /**
     * @param string $floppyDiskFilePath
     */
    public function setFloppyDiskFilePath(string $floppyDiskFilePath)
    {
        $this->floppyDiskFilePath = $floppyDiskFilePath;
    }

    /**
     * @return string
     */
    public function getHardDiskFilePath(): string
    {
        return $this->hardDiskFilePath;
    }

    /**
     * @param string $hardDiskFilePath
     */
    public function setHardDiskFilePath(string $hardDiskFilePath)
    {
        $this->hardDiskFilePath = $hardDiskFilePath;
    }
}
