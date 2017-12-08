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

    /**
     * @var Register
     */
    private $sp;

    /**
     * @var Register
     */
    private $bp;

    /**
     * @var Register
     */
    private $ip;

    /**
     * @var Register
     */
    private $si;

    /**
     * @var Register
     */
    private $di;

    /**
     * @var Register
     */
    private $es;

    /**
     * @var Register
     */
    private $cs;

    /**
     * @var Register
     */
    private $ss;

    /**
     * @var Register
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
        $this->ax = new Register(); // AX: Accumulator
        $this->cx = new Register(); // CX: Count
        $this->dx = new Register(); // DX: Data
        $this->bx = new Register(); // BX: Base

        // Pointer
        $this->sp = new Register(); // Stack Pointer
        $this->bp = new Register(); // Base Pointer

        // Index
        $this->si = new Register(); // Source Index
        $this->di = new Register(); // Destination Index

        // Segment
        $this->ds = new Register(); // Data Segment
        $this->ss = new Register(); // Stack Segment
        $this->es = new Register(); // Extra Segment

        // Set CS:IP to F000:0100
        $this->cs = new Register(new Address(0xF000)); // Code Segment
        $this->ip = new Register(new Address(0x0100)); // Instruction Pointer

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

    private function getInstructionOffset(): int
    {
        $offset = $this->cs->toInt() * self::SIZE_BIT;
        $offset += $this->ip->toInt();

        return $offset;
    }

    /**
     * Using Code Segment (CS) and Instruction Pointer (IP) to get the current OpCode.
     *
     * @return int
     */
    private function getOpcode(): int
    {
        $offset = $this->getInstructionOffset();

        /** @var int[] $opcodes */
        $opcodes = $this->ram->read($offset, 1);
        $opcode = $opcodes[0];

        return $opcode;
    }

    private function setupBiosDataTables()
    {
        $this->output->writeln('setup bios data tables');

        $tables = array_fill(0, 20, 0);
        $tables = array_map(function ($c) {
            return array_fill(0, 256, $c);
        }, $tables);

        for ($i = 0; $i < 20; $i++) {
            $offset = 0xF0000 + (0x81 + $i) * self::SIZE_BYTE;

            for ($j = 0; $j < 256; $j++) {
                $tableAddr = $this->ram->readAddress($offset, self::SIZE_BYTE);
                $valueAddr = 0xF0000 + $tableAddr->toInt() + $j;
                $v = $this->ram->read($valueAddr, 1);

                $tables[$i][$j] = $v[0];
                //$this->output->writeln(sprintf('%02x %02x  %02x %02x', $i, $j, 0x81 + $i, $tables[$i][$j]));
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
        while ($opcodeRaw = $this->getOpcode()) {
            //$opcodeInt = $opcodeRaw[0];

            $this->output->writeln(sprintf('[%s] run %d @%04x:%04x -> %02x',
                'CPU',
                $cycle,
                $this->cs->toInt(), $this->ip->toInt(),
                $opcodeRaw));

            // Decode
            $xlatId = $this->biosDataTables[8][$opcodeRaw];
            $extra = $this->biosDataTables[9][$opcodeRaw];
            $iModeSize = $this->biosDataTables[14][$opcodeRaw];
            $setFlagsType = $this->biosDataTables[10][$opcodeRaw];

            $iReg4bit = $opcodeRaw & 7;
            $iw = $iReg4bit & 1;
            $id = $iReg4bit / 2 & 1;

            $offset = $this->getInstructionOffset();

            $data = $this->ram->read($offset + 1, self::SIZE_BYTE);
            $iData0 = new Address($data);

            $data = $this->ram->read($offset + 1 + 2, self::SIZE_BYTE);
            $iData1 = new Address($data);

            $data = $this->ram->read($offset + 1 + 2 + 2, self::SIZE_BYTE);
            $iData2 = new Address($data);

            // @todo seg overwrite here

            // i_mod_size > 0 indicates that opcode uses i_mod/i_rm/i_reg, so decode them
            if ($iModeSize) {
                $iMod = $iData0->getLow() >> 6;
                $iRm = $iData0->getLow() & 7;
                $iReg = $iData0->toInt() / 8;
                $iReg = $iReg & 7;

                if (!$iMod && 6 === $iRm || 2 === $iMod) {
                    $data = $this->ram->read($offset + 1 + 2 + 2+2, self::SIZE_BYTE);
                    $iData2 = new Address($data);
                }
                elseif (1!==$iMod){
                    $iData2=$iData1;
                }
                else{
                    $data=$iData1->getLow();
                    $iData1 = new Address($data);
                }
            } else {
                $iMod = 0;
                $iRm = 0;
                $iReg = 0;
            }

            switch ($xlatId) {
                case 14: // JMP | CALL short/near
                    $this->ip->add(3 - $id);
                    if (!$iw) {
                        if ($id) {
                            // JMP far
                            $this->cs->setData($iData2);
                            $this->ip->setData(0);
                        } else {
                            // CALL
                            //@todo
                        }
                    }

                    if ($id && $iw) {
                        $add = $iData0->getLow();
                    } else {
                        $add = $iData0->toInt();
                    }

                    $this->output->writeln(sprintf('IP: %04x', $this->ip->toInt()));
                    $this->ip->add($add);
                    $this->output->writeln(sprintf('IP: %04x', $this->ip->toInt()));

                    break;
            }

            $instSize = $this->biosDataTables[12][$opcodeRaw];
            $iwSize = $this->biosDataTables[13] * ($iw + 1);

            // Increment instruction pointer by computed instruction length.
            // Tables in the BIOS binary help us here.
            $add =
                $iMod
                + $instSize + $iwSize;
            $this->output->writeln(sprintf('IP+ %04x', $add));
            $this->ip->add($add);

            // Debug
            //$l = $this->ip->getLow();
            //$o = ord($l);
            //$this->ip->setLow(chr($o + self::SIZE_BYTE));

            $cycle++;
            if ($cycle > 5000) {
                break;
            }
        }
    }
}
