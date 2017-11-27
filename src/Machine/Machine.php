<?php

/**
 * Machine create RAM, CPU, etc. and connects the components.
 */

namespace TheFox\I8086emu\Machine;

use TheFox\I8086emu\Blueprint\CpuInterface;
use TheFox\I8086emu\Exception\NoBiosException;
use TheFox\I8086emu\Exception\NoCpuException;

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
     * @var Cpu
     */
    private $cpu;

    public function __construct()
    {
        $this->cpu = new Cpu();
    }

    public function run()
    {
        if (!$this->cpu || !$this->cpu instanceof CpuInterface) {
            throw new NoCpuException();
        }

        if (!$this->biosFilePath) {
            throw new NoBiosException();
        }

        // Run the CPU.
        $this->cpu->run();
    }

    /**
     * @return mixed
     */
    public function getBiosFilePath()
    {
        return $this->biosFilePath;
    }

    /**
     * @param mixed $biosFilePath
     */
    public function setBiosFilePath($biosFilePath)
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
