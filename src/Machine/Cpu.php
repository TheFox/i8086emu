<?php

/**
 * @link https://en.wikipedia.org/wiki/Processor_(computing)
 */

namespace TheFox\I8086emu\Machine;

use TheFox\I8086emu\Blueprint\CpuInterface;
use TheFox\I8086emu\Blueprint\RamInterface;

class Cpu implements CpuInterface
{
    public const REGISTER_BASE = 0xF0000;

    /**
     * @var Ram
     */
    private $ram;

    private $ax;

    public function setRam(RamInterface $ram)
    {
        $this->ram = $ram;
    }

    public function run()
    {
        throw new \RuntimeException('no implemented');
    }
}
