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
use TheFox\I8086emu\Blueprint\AddressInterface;
use TheFox\I8086emu\Blueprint\CpuInterface;
use TheFox\I8086emu\Blueprint\OutputAwareInterface;
use TheFox\I8086emu\Blueprint\RamInterface;
use TheFox\I8086emu\Blueprint\RegisterInterface;
use TheFox\I8086emu\Exception\NotImplementedException;

class Cpu implements CpuInterface, OutputAwareInterface
{
    public const SIZE_BYTE = 2;
    public const SIZE_BIT = 16;
    public const KEYBOARD_TIMER_UPDATE_DELAY = 20000;
    public const GRAPHICS_UPDATE_DELAY = 360000;
    // Lookup tables in the BIOS binary.
    public const TABLE_XLAT_OPCODE = 8;
    public const TABLE_XLAT_SUBFUNCTION = 9;
    public const TABLE_STD_FLAGS = 10;
    public const TABLE_PARITY_FLAG = 11;
    public const TABLE_BASE_INST_SIZE = 12;
    public const TABLE_I_W_SIZE = 13;
    public const TABLE_I_MOD_SIZE = 14;
    public const TABLE_COND_JUMP_DECODE_A = 15;
    public const TABLE_COND_JUMP_DECODE_B = 16;
    public const TABLE_COND_JUMP_DECODE_C = 17;
    public const TABLE_COND_JUMP_DECODE_D = 18;
    public const TABLE_FLAGS_BITFIELDS = 19;

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
     * @var array
     */
    private $registers;

    /**
     * @var array
     */
    private $segmentRegisters;

    /**
     * @var Flags
     */
    private $flags;

    /**
     * @var array
     */
    private $biosDataTables;// @todo move this to class

    public function __construct()
    {
        $this->output = new NullOutput();
        $this->biosDataTables = [];

        $this->setupRegisters();
        $this->setupFlags();
    }

    /**
     * General-Purpose Registers (GPR) - 16-bit naming conventions
     */
    private function setupRegisters()
    {
        // Common register
        $this->ax = new Register('AX'); // AX: Accumulator
        $this->cx = new Register('CX'); // CX: Count
        $this->dx = new Register('DX'); // DX: Data
        $this->bx = new Register('BX'); // BX: Base

        // Pointer
        $this->sp = new Register('SP'); // Stack Pointer
        $this->bp = new Register('BP'); // Base Pointer

        // Index
        $this->si = new Register('SI'); // Source Index
        $this->di = new Register('DI'); // Destination Index

        // Segment
        $this->ds = new Register('DS'); // Data Segment
        $this->es = new Register('ES'); // Extra Segment
        $this->ss = new Register('SS'); // Stack Segment

        // Set CS:IP to F000:0100
        $this->cs = new Register('CS', new Address(0xF000)); // Code Segment
        $this->ip = new Register('IP', new Address(0x0100)); // Instruction Pointer

        $this->registers = [
            0 => $this->ax,
            1 => $this->cx,
            2 => $this->dx,
            3 => $this->bx,

            4 => $this->sp,
            5 => $this->bp,
            6 => $this->si,
            7 => $this->di,
        ];

        $this->segmentRegisters = [
            0 => $this->es,
            1 => $this->cs,
            2 => $this->ss,
            3 => $this->ds,
        ];
    }

    private function setupFlags()
    {
        // Flags
        $this->flags = new Flags();
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
            $tableAddr = $this->ram->readAddress($offset, self::SIZE_BYTE);
            $tableAddrInt = $tableAddr->toInt();

            $this->output->writeln(sprintf('table %d', $i));

            for ($j = 0; $j < 256; $j++) {
                $valueAddr = 0xF0000 + $tableAddrInt + $j;
                $v = $this->ram->read($valueAddr, 1);

                $tables[$i][$j] = $v[0];
                //$this->output->writeln(sprintf('%02x %02x  %02x %02x', $i, $j, 0x81 + $i, $tables[$i][$j]));
            }
        }

        $this->biosDataTables = $tables;
        $this->output->writeln('bios data tables done');
    }

    /**
     * Use this for development.
     */
    private function setupDevBiosDataTables()
    {
        $this->biosDataTables[8] = [9, 9, 9, 9, 7, 7, 25, 26, 9, 9, 9, 9, 7, 7, 25, 48, 9, 9, 9, 9, 7, 7, 25, 26, 9, 9, 9, 9, 7, 7, 25, 26, 9, 9, 9, 9, 7, 7, 27, 28, 9, 9, 9, 9, 7, 7, 27, 28, 9, 9, 9, 9, 7, 7, 27, 29, 9, 9, 9, 9, 7, 7, 27, 29, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 3, 3, 3, 3, 3, 3, 3, 3, 4, 4, 4, 4, 4, 4, 4, 4, 51, 54, 52, 52, 52, 52, 52, 52, 55, 55, 55, 55, 52, 52, 52, 52, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 8, 8, 8, 8, 15, 15, 24, 24, 9, 9, 9, 9, 10, 10, 10, 10, 16, 16, 16, 16, 16, 16, 16, 16, 30, 31, 32, 53, 33, 34, 35, 36, 11, 11, 11, 11, 17, 17, 18, 18, 47, 47, 17, 17, 17, 17, 18, 18, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 12, 12, 19, 19, 37, 37, 20, 20, 49, 50, 19, 19, 38, 39, 40, 19, 12, 12, 12, 12, 41, 42, 43, 44, 53, 53, 53, 53, 53, 53, 53, 53, 13, 13, 13, 13, 21, 21, 22, 22, 14, 14, 14, 14, 21, 21, 22, 22, 53, 0, 23, 23, 53, 45, 6, 6, 46, 46, 46, 46, 46, 46, 5, 5];

        $this->biosDataTables[9] = [0, 0, 0, 0, 0, 0, 8, 8, 1, 1, 1, 1, 1, 1, 9, 36, 2, 2, 2, 2, 2, 2, 10, 10, 3, 3, 3, 3, 3, 3, 11, 11, 4, 4, 4, 4, 4, 4, 8, 0, 5, 5, 5, 5, 5, 5, 9, 1, 6, 6, 6, 6, 6, 6, 10, 2, 7, 7, 7, 7, 7, 7, 11, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, 1, 1, 1, 1, 1, 1, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 21, 21, 21, 21, 21, 21, 0, 0, 0, 0, 21, 21, 21, 21, 21, 21, 21, 21, 21, 21, 21, 21, 21, 21, 21, 21, 21, 21, 21, 21, 0, 0, 0, 0, 0, 0, 0, 0, 8, 8, 8, 8, 12, 12, 12, 12, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 255, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, 1, 2, 2, 1, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, 1, 0, 0, 16, 22, 0, 0, 0, 0, 1, 1, 0, 255, 48, 2, 0, 0, 0, 0, 255, 255, 40, 11, 3, 3, 3, 3, 3, 3, 3, 3, 43, 43, 43, 43, 0, 0, 0, 0, 0, 0, 0, 0, 1, 1, 1, 1, 1, 21, 0, 0, 2, 40, 21, 21, 80, 81, 92, 93, 94, 95, 0, 0];

        $this->biosDataTables[10] = [3, 3, 3, 3, 3, 3, 0, 0, 5, 5, 5, 5, 5, 5, 0, 0, 1, 1, 1, 1, 1, 1, 0, 0, 1, 1, 1, 1, 1, 1, 0, 0, 5, 5, 5, 5, 5, 5, 0, 1, 3, 3, 3, 3, 3, 3, 0, 1, 5, 5, 5, 5, 5, 5, 0, 1, 3, 3, 3, 3, 3, 3, 0, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, 1, 1, 1, 5, 5, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 5, 5, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 5, 5, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0];

        $this->biosDataTables[11] = [1, 0, 0, 1, 0, 1, 1, 0, 0, 1, 1, 0, 1, 0, 0, 1, 0, 1, 1, 0, 1, 0, 0, 1, 1, 0, 0, 1, 0, 1, 1, 0, 0, 1, 1, 0, 1, 0, 0, 1, 1, 0, 0, 1, 0, 1, 1, 0, 1, 0, 0, 1, 0, 1, 1, 0, 0, 1, 1, 0, 1, 0, 0, 1, 0, 1, 1, 0, 1, 0, 0, 1, 1, 0, 0, 1, 0, 1, 1, 0, 1, 0, 0, 1, 0, 1, 1, 0, 0, 1, 1, 0, 1, 0, 0, 1, 1, 0, 0, 1, 0, 1, 1, 0, 0, 1, 1, 0, 1, 0, 0, 1, 0, 1, 1, 0, 1, 0, 0, 1, 1, 0, 0, 1, 0, 1, 1, 0, 0, 1, 1, 0, 1, 0, 0, 1, 1, 0, 0, 1, 0, 1, 1, 0, 1, 0, 0, 1, 0, 1, 1, 0, 0, 1, 1, 0, 1, 0, 0, 1, 1, 0, 0, 1, 0, 1, 1, 0, 0, 1, 1, 0, 1, 0, 0, 1, 0, 1, 1, 0, 1, 0, 0, 1, 1, 0, 0, 1, 0, 1, 1, 0, 1, 0, 0, 1, 0, 1, 1, 0, 0, 1, 1, 0, 1, 0, 0, 1, 0, 1, 1, 0, 1, 0, 0, 1, 1, 0, 0, 1, 0, 1, 1, 0, 0, 1, 1, 0, 1, 0, 0, 1, 1, 0, 0, 1, 0, 1, 1, 0, 1, 0, 0, 1, 0, 1, 1, 0, 0, 1, 1, 0, 1, 0, 0, 1];

        $this->biosDataTables[12] = [2, 2, 2, 2, 1, 1, 1, 1, 2, 2, 2, 2, 1, 1, 1, 2, 2, 2, 2, 2, 1, 1, 1, 1, 2, 2, 2, 2, 1, 1, 1, 1, 2, 2, 2, 2, 1, 1, 1, 1, 2, 2, 2, 2, 1, 1, 1, 1, 2, 2, 2, 2, 1, 1, 1, 1, 2, 2, 2, 2, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 0, 1, 1, 1, 1, 1, 3, 3, 3, 3, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 3, 3, 0, 0, 2, 2, 2, 2, 4, 1, 0, 0, 0, 0, 0, 0, 2, 2, 2, 2, 2, 2, 1, 1, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 0, 0, 0, 0, 1, 1, 1, 1, 1, 2, 1, 1, 1, 1, 2, 2, 1, 1, 1, 1, 1, 1, 2, 2];

        $this->biosDataTables[13] = [0, 0, 0, 0, 1, 1, 0, 0, 0, 0, 0, 0, 1, 1, 0, 0, 0, 0, 0, 0, 1, 1, 0, 0, 0, 0, 0, 0, 1, 1, 0, 0, 0, 0, 0, 0, 1, 1, 0, 0, 0, 0, 0, 0, 1, 1, 0, 0, 0, 0, 0, 0, 1, 1, 0, 0, 0, 0, 0, 0, 1, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, 1, 1, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, 1, 1, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, 1, 0, 0, 0, 0, 0, 0, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 0, 0, 0, 0, 0, 0, 1, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0];

        $this->biosDataTables[14] = [1, 1, 1, 1, 0, 0, 0, 0, 1, 1, 1, 1, 0, 0, 0, 0, 1, 1, 1, 1, 0, 0, 0, 0, 1, 1, 1, 1, 0, 0, 0, 0, 1, 1, 1, 1, 0, 0, 0, 0, 1, 1, 1, 1, 0, 0, 0, 0, 1, 1, 1, 1, 0, 0, 0, 0, 1, 1, 1, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, 1, 0, 0, 1, 1, 1, 1, 0, 0, 0, 0, 0, 0, 0, 0, 1, 1, 1, 1, 0, 0, 0, 0, 1, 1, 1, 1, 1, 1, 1, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, 1, 0, 0, 0, 0, 0, 0, 1, 1];
    }

    private function printXlatOpcodes()
    {
        $this->output->writeln('XLAT');

        $codes = $this->biosDataTables[self::TABLE_XLAT_OPCODE];

        $groupped = [];
        foreach ($codes as $opcode => $xlatId) {
            if (isset($groupped[$xlatId])) {
                $groupped[$xlatId][] = $opcode;
            } else {
                $groupped[$xlatId] = [$opcode];
            }
        }

        sort($groupped);
        foreach ($groupped as $xlatId => $opcodes) {
            $s = array_map(function ($c) {
                return sprintf('%02x', $c);
            }, $opcodes);
            $s = join(' ', $s);
            $this->output->writeln(sprintf('-> %d = %s', $xlatId, $s));
        }

        $this->output->writeln('XLAT END');
    }

    public function run()
    {
        //$this->setupBiosDataTables(); // @todo activate this
        $this->setupDevBiosDataTables();
        $this->printXlatOpcodes();

        // Debug
        $this->output->writeln(sprintf('CS: %04x', $this->cs->toInt()));
        $this->output->writeln(sprintf('IP: %04x', $this->ip->toInt()));

        //throw new \RuntimeException('Not implemented');

        $trapFlag = false;
        $segOverride = '';
        $segOverrideEn = 0;
        $regOverride = '';
        $regOverrideEn = 0;
        //$scratch=0;
        //$scratch2=0;

        $cycle = 0;
        while ($opcodeRaw = $this->getOpcode()) {
            $this->output->writeln(sprintf(
                '[%s] run %d @%04x:%04x -> %02x',
                'CPU',
                $cycle,
                $this->cs->toInt(),
                $this->ip->toInt(),
                $opcodeRaw
            ));

            // Decode
            $xlatId = $this->biosDataTables[self::TABLE_XLAT_OPCODE][$opcodeRaw];
            //$extra = $this->biosDataTables[self::TABLE_XLAT_SUBFUNCTION][$opcodeRaw];
            $iModeSize = $this->biosDataTables[self::TABLE_I_MOD_SIZE][$opcodeRaw];
            $setFlagsType = $this->biosDataTables[self::TABLE_STD_FLAGS][$opcodeRaw];
            if ($setFlagsType) {
                throw new NotImplementedException(sprintf('FLAGS TYPE: %d', $setFlagsType));
            }

            // 0-7 number of the 8-bit Registers.
            $iReg4bit = $opcodeRaw & 7; // xxxx111

            // Is Word Instruction, means 2 Byte long.
            $iw = (bool)($iReg4bit & 1); // xxxxxx1

            // Instruction Direction
            $id = (bool)($iReg4bit & 2); // xxxxx1x

            $this->output->writeln(sprintf('reg 4bit: %x (%s) %x %x', $iReg4bit, $iReg4bit / 2, $iw, $id));

            $offset = $this->getInstructionOffset();

            $data = $this->ram->read($offset + 1, 3);

            if ($segOverrideEn) {
                --$segOverrideEn;
            }
            if ($regOverrideEn) {
                --$segOverrideEn;
            }

            $iMod = 0;
            $iRm = 0; // Is Register/Memory?
            $iReg = 0;
            //$from = null;
            //$to = null;

            // $iModeSize > 0 indicates that opcode uses Mod/Reg/RM, so decode them
            if ($iModeSize) {
                $iMod = $data[0] >> 6;     // 11xxxxxx
                $iReg = $data[0] >> 3 & 7; // xx111xxx
                $iRm = $data[0] & 7;       // xxxxx111

                if (!$iMod && 6 === $iRm || 2 === $iMod) { // 6 = 110 || 2 = 10
                    throw new NotImplementedException();
                    //$dataTmp = $this->ram->read($offset + 4, 1);
                    //$data[2] = $dataTmp[0];
                } elseif (1 !== $iMod) {
                    //throw new NotImplementedException();
                    //$data[2] = $data[1];
                } else {
                    throw new NotImplementedException(sprintf('iModeSize ELSE: %d', $data[1]));
                    // i_data1 = (char)i_data1;
                }

                //throw new NotImplementedException('DECODE_RM_REG');
                //$scratch2=4*!$iMod;

                //switch ($iMod) {
                //    case 3:
                //
                //        $to = $iReg;
                //        break;
                //
                //    default:
                //        throw new NotImplementedException(sprintf('iMod %d', $iMod));
                //}
            }

            switch ($xlatId) {
                case 1: // MOV reg, imm - OpCodes: b0 b1 b2 b3 b4 b5 b6 b7 b8 b9 ba bb bc bd be bf
                    $iw = (bool)($opcodeRaw & 8); // xxxx1xxx

                    // R_M_OP
                    $register = $this->getRegisterByNumber($iw, $iReg4bit);

                    if ($iw) {
                        $register->setData([$data[0], $data[1]]);
                    } else {
                        $register->setData([$data[0]]);
                    }

                    $this->output->writeln(sprintf('MOV reg, imm (iw=%d, iReg4bit=%d, reg=%s)', $iw, $iReg4bit, $register));
                    break;

                case 3: // PUSH reg - OpCodes: 98
                    $register = $this->getRegisterByNumber(true, $iReg4bit);
                    $this->output->writeln(sprintf('PUSH %s', $register));
                    $this->pushToStack($register, self::SIZE_BYTE);
                    break;

                case 10: // MOV sreg, r/m | POP r/m | LEA reg, r/m - OpCodes: c8
                    if (!$iw) {
                        // MOV
                        $this->output->writeln(sprintf('MOV sreg, r/m'));

                        $toRegister = $this->getSegmentRegisterByNumber($iReg);
                        $this->output->writeln(sprintf(' -> TO   %s', $toRegister));

                        if (3 === $iMod) {
                            // if mod = 11 then r/m is treated as a REG field
                            $fromRegister = $this->getRegisterByNumber(true, $iRm);
                            $this->output->writeln(sprintf(' -> FROM %s', $fromRegister));

                            $toRegister->setData($fromRegister);
                        } else {
                            throw new NotImplementedException(sprintf('else %d', $iMod));
                        }
                    } elseif (!$id) {
                        // LEA
                        //$segOverrideEn = 1;
                        //$segOverride = 'ZERO';
                        throw new NotImplementedException('LEA');
                    } else {
                        // POP
                        throw new NotImplementedException('POP');
                    }
                    break;

                case 14: // JMP | CALL short/near - OpCodes: e8 e9 ea eb
                    $this->ip->add(3 - $id);
                    if (!$iw) {
                        if ($id) {
                            // JMP far
                            $this->output->writeln(sprintf('JMP far'));
                            //$this->cs->setData($iData2);
                            $this->ip->setData(0);
                        } else {
                            // CALL
                            //@todo
                            $this->output->writeln(sprintf('CALL'));
                            throw new NotImplementedException('CALL');
                        }
                    }

                    if ($id && $iw) {
                        //$add = $iData0->getLow();
                        $add = $data[0];
                    } else {
                        throw new NotImplementedException('NOT ID AND NOT IW');
                    }

                    //$this->output->writeln(sprintf('IP old: %04x', $this->ip->toInt()));
                    $this->ip->add($add);
                    //$this->output->writeln(sprintf('IP new: %04x', $this->ip->toInt()));
                    break;

                case 25: // PUSH reg - OpCodes: c4 c5
                    $iReg = $opcodeRaw >> 3 & 3; // xxx11xxx
                    $register = $this->getSegmentRegisterByNumber($iReg);
                    $this->output->writeln(sprintf('PUSH %s', $register));
                    $this->pushToStack($register, self::SIZE_BYTE);
                    break;

                case 26: // POP reg - OpCodes: c6 c7
                    $iReg = $opcodeRaw >> 3 & 3; // xxx11xxx
                    $register = $this->getSegmentRegisterByNumber($iReg);
                    $this->output->writeln(sprintf('POP %s', $register));
                    $stackData = $this->popFromStack(self::SIZE_BYTE);
                    $register->setData($stackData);
                    break;

                default:
                    throw new NotImplementedException(sprintf('xLatID %02x (=%d dec)', $xlatId, $xlatId));
            } // switch $xlatId

            // Increment instruction pointer by computed instruction length.
            // Tables in the BIOS binary help us here.
            $instSize = $this->biosDataTables[self::TABLE_BASE_INST_SIZE][$opcodeRaw];
            $iwSize = $this->biosDataTables[self::TABLE_I_W_SIZE][$opcodeRaw] * ($iw + 1);
            $add =
                (
                    $iMod * (3 !== $iMod)
                    + 2 * (!$iMod && 6 === $iRm)
                ) * $iModeSize
                + $instSize
                + $iwSize;
            $this->output->writeln(sprintf('IP old: %04x', $this->ip->toInt()));
            $this->ip->add($add);
            $this->output->writeln(sprintf('IP new: %04x (+%04x)', $this->ip->toInt(), $add));

            // Update Instruction counter.
            $cycle++;

            $int8 = false;
            if (0 === $cycle % self::KEYBOARD_TIMER_UPDATE_DELAY) {
                $int8 = true;
            }

            if (0 === $cycle % self::GRAPHICS_UPDATE_DELAY) {
                $this->updateGraphics();
            }

            // If instruction needs to update SF, ZF and PF, set them as appropriate.
            // @todo

            if ($trapFlag) {
                $this->interrupt(1);
            }
            $trapFlag = $this->flags->get('TF');

            // @todo interrupt 8

            // Debug
            //$l = $this->ip->getLow();
            //$o = ord($l);
            //$this->ip->setLow(chr($o + self::SIZE_BYTE));

            if ($cycle > 5000) {
                // @todo remove this. just dev
                break;
            }
        } // while $opcodeRaw
    } // run()

    private function interrupt(int $code)
    {
        // @todo
        $this->output->writeln(sprintf('Interrupt %02x', $code));
        throw new NotImplementedException('Interrupt');
    }

    private function updateGraphics()
    {
        // @todo use separate framebuffer, or tty, or whatever.
        $this->output->writeln('Update Graphics');
        throw new NotImplementedException('Update Graphics');
    }

    private function getRegisterByNumber(bool $isWord, int $regId, int $loop = 0): Register
    {
        if ($isWord) {
            $register = $this->registers[$regId];
            return $register;
        }
        if ($loop >= 2) {
            throw new \RuntimeException('Unhandled recursive call detected.');
        }

        $effectiveRegId = $regId & 3; // x11
        $register = $this->getRegisterByNumber(false, $effectiveRegId, 1 + $loop);
        return $register;
    }

    private function getSegmentRegisterByNumber(int $regId): Register
    {
        $register = $this->segmentRegisters[$regId];
        return $register;
    }

    /**
     * EA
     *
     * @return int
     */
    private function getEffectiveAddress(): int
    {
        $ea = ($this->ss->toInt() << 4) + $this->sp->toInt();
        return $ea;
    }

    /**
     * @param Register|Address|int[] $data
     * @param int $size
     */
    private function pushToStack($data, int $size)
    {
        if ($data instanceof RegisterInterface) {
            /** @var Register $register */
            $register = $data;

            if ($size !== $register->getSize()) {
                throw new \RangeException(sprintf('Wrong size. Register is %d bytes, data is %d bytes.', $register->getSize(), $size));
            }

            $data = $register->getData();
            if ($data instanceof AddressInterface || is_array($data)) {
                $this->pushToStack($data, $size);
            } else {
                throw new NotImplementedException('ELSE push data B');
            }
        } elseif ($data instanceof AddressInterface) {
            /** @var Address $address */
            $address = $data;
            $this->pushToStack($address->getData(), $size);
        } elseif (is_array($data)) {
            $ea = $this->getEffectiveAddress();
            for ($i = 0; $i < $size; ++$i) {
                $c = array_pop($data);
                --$ea;
                $this->ram->writeRaw($c, $ea);
            }
            $this->sp->add(-$size);
        } else {
            throw new NotImplementedException('ELSE push data A');
        }
    }

    private function popFromStack(int $size): array
    {
        $ea = $this->getEffectiveAddress();
        $data = $this->ram->read($ea, $size);

        $this->sp->add($size);

        return $data;
    }
}
