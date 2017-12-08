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
     * @var Ram
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

    /**
     * @var array
     */
    private $biosDataTables;// @todo move this to class

    public function __construct()
    {
        $this->output = new NullOutput();
        $this->biosDataTables = [];

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

    /**
     * @param RamInterface $ram
     */
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

    private function getInstructionOffset():int
    {
        $offset = $this->cs->toInt() * self::SIZE_BIT;
        $offset += $this->ip->toInt();

        return $offset;
    }

    /**
     * Using Code Segment (CS) and Instruction Pointer (IP) to get the current OpCode.
     *
     * @return string
     */
    private function getOpcode(): string
    {
        $offset = $this->getInstructionOffset();

        //$this->output->writeln(sprintf(' -> Offset: %08x', $offset));

        $opcode = $this->ram->read($offset, 1);
        //$this->output->writeln(sprintf(' -> OpCode Len: %d', strlen($opcode)));

        return $opcode;
    }

    private function setupBiosDataTables()
    {
        $this->output->writeln('setup bios data tables');
        $tables = [];

        for ($i = 0; $i < 20; $i++) {
            $offset = 0xF0000 + (0x81 + $i) * self::SIZE_BYTE;

            for ($j = 0; $j < 256; $j++) {
                $tableAddr = $this->ram->readAddress($offset, self::SIZE_BYTE);
                $valueAddr = 0xF0000 + $tableAddr->toInt() + $j;
                $v = $this->ram->read($valueAddr, 1);

                $tables[$i][$j] = ord($v);
                //$this->output->writeln(sprintf('%02x %02x  %02x %02x', $i, $j, 0x81 + $i, ord($v)));
            }
        }

        $this->biosDataTables = $tables;
        $this->output->writeln('bios data tables done');
    }

    public function run()
    {
        $this->setupBiosDataTables();

        // Debug
        $this->output->writeln(sprintf('CS: %04x', $this->cs->toInt()));
        $this->output->writeln(sprintf('IP: %04x', $this->ip->toInt()));

        //throw new \RuntimeException('Not implemented');

        $cycle = 0;
        while ("\x00" !== ($opcodeRaw = $this->getOpcode())) {
            $this->output->writeln(sprintf('[%s] run %d @%04x:%04x -> %02x',
                'CPU',
                $cycle,
                $this->cs->toInt(), $this->ip->toInt(),
                ord($opcodeRaw[0])));

            $opcodeInt = ord($opcodeRaw);

            // Decode
            $xlatId = $this->biosDataTables[8][$opcodeInt];
            $extra = $this->biosDataTables[9][$opcodeInt];
            $iModeSize = $this->biosDataTables[14][$opcodeInt];
            $setFlagsType = $this->biosDataTables[10][$opcodeInt];

            $iReg4bit = $opcodeInt & 7;
            $iw = $iReg4bit & 1;
            $id = $iReg4bit / 2 & 1;

            $offset = $this->getInstructionOffset();
            $iData1=$this->ram->read($offset+1, self::SIZE_BYTE);
            $iData2=$this->ram->read($offset+1+2, self::SIZE_BYTE);
            $iData3=$this->ram->read($offset+1+2+2, self::SIZE_BYTE);

            $l = $this->ip->getLow();
            $o = ord($l);
            $this->ip->setLow(chr($o + self::SIZE_BYTE));

            $cycle++;
            if ($cycle > 5000) {
                break;
            }
        }
    }
}
