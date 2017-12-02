<?php

/**
 * @link https://en.wikipedia.org/wiki/Processor_(computing)
 */

namespace TheFox\I8086emu\Machine;

use TheFox\I8086emu\Blueprint\CpuInterface;
use TheFox\I8086emu\Blueprint\RamInterface;

class Cpu implements CpuInterface
{
    //public const REGISTER_BASE = 0xF0000;

    /**
     * @var Ram
     */
    private $ram;

    /**
     * @var array
     */
    private $registers;

    /**
     * @var Register
     */
    private $ax;

    public function __construct()
    {
        $this->setupRegisters();
    }

    private function setupRegisters()
    {
        $this->ax = new Register();

        $this->registers = [
            $this->ax,
        ];
    }

    public function setRam(RamInterface $ram)
    {
        $this->ram = $ram;
    }

    public function run()
    {
        $this->setupRegisters();

        throw new \RuntimeException('no implemented');
    }
}
