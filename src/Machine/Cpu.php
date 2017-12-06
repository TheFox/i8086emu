<?php

/**
 * This class holds all stuff that a CPU needs.
 * It's connected to the RAM.
 *
 * @link https://en.wikipedia.org/wiki/Processor_(computing)
 */

namespace TheFox\I8086emu\Machine;

use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use TheFox\I8086emu\Blueprint\CpuInterface;
use TheFox\I8086emu\Blueprint\FlagInterface;
use TheFox\I8086emu\Blueprint\OutputAwareInterface;
use TheFox\I8086emu\Blueprint\RamInterface;
use TheFox\I8086emu\Blueprint\RegisterInterface;

class Cpu implements CpuInterface, OutputAwareInterface
{
    public const SIZE_BYTE = 2;
    public const SIZE_BIT = 16;

    /**
     * Debug
     * @var OutputInterface
     */
    private $output;

    /**
     * @var RamInterface
     */
    private $ram;

    /**
     * @var RegisterInterface
     */
    private $ax;

    /**
     * @var RegisterInterface
     */
    private $cx;

    /**
     * @var RegisterInterface
     */
    private $dx;

    /**
     * @var RegisterInterface
     */
    private $bx;

    /**
     * @var RegisterInterface
     */
    private $sp;

    /**
     * @var RegisterInterface
     */
    private $bp;

    /**
     * @var RegisterInterface
     */
    private $ip;

    /**
     * @var RegisterInterface
     */
    private $si;

    /**
     * @var RegisterInterface
     */
    private $di;

    /**
     * @var RegisterInterface
     */
    private $es;

    /**
     * @var RegisterInterface
     */
    private $cs;

    /**
     * @var RegisterInterface
     */
    private $ss;

    /**
     * @var RegisterInterface
     */
    private $ds;

    /**
     * @var FlagInterface
     */
    private $flag;

    public function __construct()
    {
        $this->output = new NullOutput();

        $this->setupRegisters();
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
        $this->flag = new Flag();
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

    /**
     * Using Code Segment (CS) and Instruction Pointer (IP) to get the current OpCode.
     *
     * @return string
     */
    private function getOpcode(): string
    {
        $offset = $this->cs->toInt() * self::SIZE_BIT;
        $offset += $this->ip->toInt();

        $this->output->writeln(sprintf('Offset: %08x', $offset));

        $opcode = $this->ram->read($offset, self::SIZE_BYTE);
        $this->output->writeln(sprintf('OpCode Len: %d', strlen($opcode)));

        return $opcode;
    }

    public function run()
    {
        // Debug
        $this->output->writeln(sprintf('CS: %04x', $this->cs->toInt()));
        $this->output->writeln(sprintf('IP: %04x', $this->ip->toInt()));

        $opcode = $this->getOpcode();

        //throw new \RuntimeException('Not implemented');

        for ($cycle = 0; $cycle < 5 && "\x00\x00" !== ($opcode = $this->getOpcode()); ++$cycle) {
            $this->output->writeln(sprintf('[%s] run %d: %02x %02x', 'CPU', $cycle,ord($opcode[0]),ord($opcode[1])));
            $this->output->writeln('---');
        }
    }
}
