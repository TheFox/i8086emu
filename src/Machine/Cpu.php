<?php

/**
 * @link https://en.wikipedia.org/wiki/Processor_(computing)
 */

namespace TheFox\I8086emu\Machine;

use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use TheFox\I8086emu\Blueprint\CpuInterface;
use TheFox\I8086emu\Blueprint\OutputAwareInterface;
use TheFox\I8086emu\Blueprint\RamInterface;

class Cpu implements CpuInterface, OutputAwareInterface
{
    //public const REGISTER_BASE = 0xF0000;
    /**
     * @var Ram
     */
    private $ram;

    /**
     * @var Register
     */
    private $ax;

    /**
     * @var Register
     */
    private $cx;

    /**
     * @var Register
     */
    private $dx;

    /**
     * @var Register
     */
    private $bx;

    private $sp;

    private $bp;

    private $ip;

    private $si;

    private $di;

    private $es;

    private $cs;

    private $ss;

    private $ds;

    private $flags;

    /**
     * @var OutputInterface
     */
    private $output;

    public function __construct()
    {
        $this->setupRegisters();
        $this->output = new  NullOutput();
    }

    private function setupRegisters()
    {
        // Common register
        $this->ax = new Register('ax'); // AX: Accumulator
        $this->cx = new Register('cx'); // CX: Count
        $this->dx = new Register('dx'); // DX: Data
        $this->bx = new Register('bx'); // BX: Base

        // Pointer
        $this->sp = new Register('sp'); // Stack Pointer
        $this->bp = new Register('bp'); // Base Pointer

        // Index
        $this->si = new Register('si'); // Source Index
        $this->di = new Register('di'); // Destination Index

        // Segment
        $this->ds = new Register('ds'); // Data Segment
        $this->ss = new Register('ss'); // Stack Segment
        $this->es = new Register('es'); // Extra Segment

        // Set CS:IP to F000:0100
        $this->ip = new Register('ip', ["\x00", "\x01"]); // Instruction Pointer
        $this->cs = new Register('cs', ["\x00", "\xF0"]); // Code Segment

        // Flags
        //$this->flags = new Register('flags');
    }

    public function setRam(RamInterface $ram)
    {
        $this->ram = $ram;
    }

    /**
     * @param OutputInterface $output
     */
    public function setOutput(OutputInterface $output)
    {
        $this->output = $output;
    }

    public function run()
    {
        $cycle=0;
        while ($cycle++<100) {
            $this->output->writeln(sprintf('[%s] run %d', 'CPU',$cycle));

        }
    }
}
