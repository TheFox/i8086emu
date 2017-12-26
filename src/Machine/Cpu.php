<?php

/**
 * This class holds all stuff that a CPU needs.
 * It's connected to the RAM.
 *
 * @link https://en.wikipedia.org/wiki/Processor_(computing)
 */

namespace TheFox\I8086emu\Machine;

use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\Output;
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
    private const TABLE_LENGTHS = [
        15 => 8,
        16 => 8,
        17 => 8,
        18 => 8,
        19 => 10,
    ];

    /**
     * Debug
     * @var Output
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
     * @var Register
     */
    private $zero;

    /**
     * var Register
     */
    //private $scratch;

    /**
     * @var \SplFixedArray
     */
    private $registers;

    /**
     * @var \SplFixedArray
     */
    private $segmentRegisters;

    /**
     * @var Flags
     */
    private $flags;

    /**
     * @var \SplFixedArray
     */
    private $biosDataTables;

    public function __construct()
    {
        $this->output = new NullOutput();

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

        //print(''.$this->cs);

        $this->zero = new Register('ZERO'); // I don't know what's this is.
        //$this->scratch = new Register('SCRATCH'); // I don't know what's this is.

        $this->registers = \SplFixedArray::fromArray([
            0 => $this->ax,
            1 => $this->cx,
            2 => $this->dx,
            3 => $this->bx,

            4 => $this->sp,
            5 => $this->bp,
            6 => $this->si,
            7 => $this->di,

            8 => $this->es,
            9 => $this->cs,
            10 => $this->ss,
            11 => $this->ds,

            12 => $this->zero,
            //13 => $this->scratch,
        ]);

        $this->segmentRegisters = \SplFixedArray::fromArray([
            0 => $this->es,
            1 => $this->cs,
            2 => $this->ss,
            3 => $this->ds,
        ]);
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

    /**
     * Using Code Segment (CS) and Instruction Pointer (IP) to get the current OpCode.
     *
     * @return int
     */
    private function getOpcode(): ?int
    {
        $offset = $this->getEffectiveInstructionPointerAddress();

        /** @var int[] $opcodes */
        $opcodes = $this->ram->read($offset, 1);
        $opcode = $opcodes[0];

        return $opcode;
    }

    /**
     * Read the data tables from BIOS.
     */
    private function setupBiosDataTables()
    {
        $this->output->writeln('setup bios data tables');

        $tables = \SplFixedArray::fromArray(array_fill(0, 20, 0));
        foreach ($tables as $i => $table) {
            if (isset(self::TABLE_LENGTHS[$i])) {
                $tableLength = self::TABLE_LENGTHS[$i];
            } else {
                $tableLength = 256;
            }
            $tables[$i] = \SplFixedArray::fromArray(array_fill(0, $tableLength, $table));
        }

        for ($i = 0; $i < 20; $i++) {
            $offset = 0xF0000 + (0x81 + $i) * self::SIZE_BYTE;
            $data = $this->ram->read($offset, self::SIZE_BYTE);
            $addr = ($data[1] << 8) | $data[0];
            //$tableAddr = $this->ram->readAddress($offset, self::SIZE_BYTE);
            //$tableAddrInt = $tableAddr->toInt();

            $this->output->writeln(sprintf('table %d', $i));

            if (isset(self::TABLE_LENGTHS[$i])) {
                $tableLength = self::TABLE_LENGTHS[$i];
            } else {
                $tableLength = 256;
            }

            for ($j = 0; $j < $tableLength; $j++) {
                $valueAddr = 0xF0000 + $addr + $j;
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

        ksort($groupped);
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
        $this->setupBiosDataTables();
        //$this->setupDevBiosDataTables();
        $this->printXlatOpcodes();

        // Debug
        $this->output->writeln(sprintf('CS: %04x', $this->cs->toInt()));
        $this->output->writeln(sprintf('IP: %04x', $this->ip->toInt()));

        $trapFlag = false;
        $segOverride = 0;
        $segOverrideEn = 0; // Segment Override
        $regOverride = 0;
        $repOverrideEn = 0; // Repeat

        $cycle = 0;
        while ($opcodeRaw = $this->getOpcode()) {
            // Decode
            $xlatId = $this->biosDataTables[self::TABLE_XLAT_OPCODE][$opcodeRaw];
            $extra = $this->biosDataTables[self::TABLE_XLAT_SUBFUNCTION][$opcodeRaw];
            $iModeSize = $this->biosDataTables[self::TABLE_I_MOD_SIZE][$opcodeRaw];

            // 0-7 number of the 8-bit Registers.
            $iReg4bit = $opcodeRaw & 7; // xxxx111

            // Is Word Instruction, means 2 Byte long.
            $iw = (bool)($iReg4bit & 1); // xxxxxx1
            $iwSize = $iw ? 2 : 1;

            // Instruction Direction
            $id = (bool)($iReg4bit & 2); // xxxxx1x

            //$this->output->writeln(sprintf('reg 4bit: %x %03b', $iReg4bit, $iReg4bit));

            $offset = $this->getEffectiveInstructionPointerAddress();

            $dataByte = $this->ram->read($offset + 1, 5);
            $dataWord = [
                ($dataByte[1] << 8) | $dataByte[0],
                ($dataByte[2] << 8) | $dataByte[1],
                ($dataByte[3] << 8) | $dataByte[2],
                ($dataByte[4] << 8) | $dataByte[3],
            ];

            $this->output->writeln(sprintf(
                '<info>[%s] run %d @%04x:%04x -> OP 0x%02x %d [%08b] XLAT 0x%02x %d [%08b]</info>',
                'CPU',
                $cycle,
                $this->cs->toInt(),
                $this->ip->toInt(),
                $opcodeRaw, $opcodeRaw, $opcodeRaw,
                $xlatId, $xlatId, $xlatId
            ));
            $this->output->writeln(sprintf('data byte: 0=%02x 1=%02x 2=%02x 3=%02x 4=%02x', $dataByte[0], $dataByte[1], $dataByte[2], $dataByte[3], $dataByte[4]));
            foreach ($dataWord as $n => $tmpWord) {
                $this->output->writeln(sprintf('data%d word: %x', $n, $tmpWord));
            }

            if ($segOverrideEn) {
                --$segOverrideEn;
            }
            if ($repOverrideEn) {
                --$segOverrideEn;
            }

            $iMod = 0;
            $iRm = 0; // Is Register/Memory?
            $iReg = 0;
            //$disp = 0;
            $from = null;
            $to = null;
            $opSource = null;
            $opDest = null;
            $opResult = null; // Needs to be null for development.

            $debug = null;

            // $iModeSize > 0 indicates that opcode uses Mod/Reg/RM, so decode them
            if ($iModeSize) {
                $iMod = $dataByte[0] >> 6;     // 11xxxxxx
                $iReg = $dataByte[0] >> 3 & 7; // xx111xxx
                $iRm = $dataByte[0] & 7;       // xxxxx111

                $this->output->writeln(sprintf('MOD %d  %02b', $iMod, $iMod));
                $this->output->writeln(sprintf('REG %d %03b', $iReg, $iReg));
                $this->output->writeln(sprintf('R/M %d %03b', $iRm, $iRm));

                $biosDataTableBaseIndex = 0;

                switch ($iMod) {
                    case 0:
                        $biosDataTableBaseIndex += 4;
                    // no break

                    case 1:
                    case 2:
                        if (0 === $iMod && 6 === $iRm || 2 === $iMod) {
                            // *except if mod = 00 and r/m = 110 then EA = disp-high; disp-low
                            // if mod = 10 then DISP = disp-high; disp-low
                            $dataWord[2] = $dataWord[3];
                            $dataByte[2] = $dataWord[2] & 0xFF;
                            //$debug = 'set $dataWord[2] = $dataWord[3]';
                        } else { // $iMod == 1
                            // If i_mod is 1, operand is (usually) 8 bits rather than 16 bits
                            //$dataWord[1] = $data[1]; // @todo activate this if needed
                            $debug = 'set $dataWord[1] = $data[1]';
                        }

                        if ($segOverrideEn) {
                            $defaultSegId = $segOverride;
                        } else {
                            /**
                             * Table 3/7: R/M "default segment" lookup
                             * @var int $defaultSegId
                             */
                            $defaultSegId = $this->biosDataTables[$biosDataTableBaseIndex + 3][$iRm];
                        }

                        // Table 0/4: R/M "register 1" lookup
                        $register1Id = $this->biosDataTables[$biosDataTableBaseIndex][$iRm];

                        // Table 1/5: R/M "register 2" lookup
                        $register2Id = $this->biosDataTables[$biosDataTableBaseIndex + 1][$iRm];

                        // Convert Register IDs to objects.
                        $defaultSegReg = $this->getRegisterByNumber(true, $defaultSegId);
                        $register1 = $this->getRegisterByNumber(true, $register1Id);
                        $register2 = $this->getRegisterByNumber(true, $register2Id);

                        // Table 2/6: R/M "DISP multiplier" lookup
                        $dispMultiplier = $this->biosDataTables[$biosDataTableBaseIndex + 2][$iRm];

                        $addr1 =
                            $register1->toInt()
                            + $register2->toInt()
                            + $dataWord[1] * $dispMultiplier;

                        $addr2 =
                            ($defaultSegReg->toInt() << 4)
                            + (0xFFFF & $addr1); // cast to "unsigned short".

                        $rm = new AbsoluteAddress($addr2);

                        $this->output->writeln(sprintf('DEF  %s %d', $defaultSegReg, $defaultSegId));
                        $this->output->writeln(sprintf('REG1 %s %d', $register1, $register1Id));
                        $this->output->writeln(sprintf('REG2 %s %d', $register2, $register2Id));
                        $this->output->writeln(sprintf('ADDR1 %x', $addr1));
                        $this->output->writeln(sprintf('ADDR2 %x', $addr2));
                        break;

                    case 3:
                        // if mod = 11 then r/m is treated as a REG field
                        $rm = $this->getRegisterByNumber($iw, $iRm);
                        //$dataWord[2] = $dataWord[1]; // @todo activate this if needed
                        //$dataByte[2] = $dataWord[2]&0xFF; // @todo activate this if needed
                        $debug = 'set $dataWord[2] = $dataWord[1]';
                        break;

                    default:
                        throw new NotImplementedException(sprintf('Unhandled mode: %d', $iMod));
                } // switch $iMod

                if (!isset($rm)) {
                    throw new \RuntimeException('rm variable has not been set yet.');
                }

                $from = $to = $this->getRegisterByNumber($iw, $iReg);
                if ($id) {
                    $from = $rm;
                } else {
                    $to = $rm;
                }

                $this->output->writeln(sprintf('<info>FROM %s</info>', $from));
                $this->output->writeln(sprintf('<info>TO   %s</info>', $to));
                $this->output->writeln('---');
            }

            switch ($xlatId) {
                case 0: // Conditional jump (JAE, JNAE, etc.) - OpCodes: 70 71 72 73 74 75 76 77 78 79 7a 7b 7c 7d 7e 7f f1
                    /**
                     * $iw is the invert Flag.
                     * For example, $iw == 0 means JAE, $iw == 1 means JNAE
                     */

                    $flagId = ($opcodeRaw >> 1) & 7; // xxxx111x

                    $condDecodeA = $this->biosDataTables[self::TABLE_COND_JUMP_DECODE_A][$flagId];
                    $condDecodeB = $this->biosDataTables[self::TABLE_COND_JUMP_DECODE_B][$flagId];
                    $condDecodeC = $this->biosDataTables[self::TABLE_COND_JUMP_DECODE_C][$flagId];
                    $condDecodeD = $this->biosDataTables[self::TABLE_COND_JUMP_DECODE_D][$flagId];

                    //if ($condDecodeA >= 40) {
                    //    $condDecodeA -= 40;
                    //}
                    //if ($condDecodeB >= 40) {
                    //    $condDecodeB -= 40;
                    //}
                    //if ($condDecodeC >= 40) {
                    //    $condDecodeC -= 40;
                    //}
                    //if ($condDecodeD >= 40) {
                    //    $condDecodeD -= 40;
                    //}

                    //if ($condDecodeA >= 9) {
                    //    $realFlagIdA = 12;
                    //} else {
                    //    $realFlagIdA = $this->biosDataTables[self::TABLE_FLAGS_BITFIELDS][$condDecodeA];
                    //}
                    //if ($condDecodeB >= 9) {
                    //    $realFlagIdB = 12;
                    //} else {
                    //    $realFlagIdB = $this->biosDataTables[self::TABLE_FLAGS_BITFIELDS][$condDecodeB];
                    //}
                    //if ($condDecodeC >= 9) {
                    //    $realFlagIdC = 12;
                    //} else {
                    //    $realFlagIdC = $this->biosDataTables[self::TABLE_FLAGS_BITFIELDS][$condDecodeC];
                    //}
                    //if ($condDecodeD >= 9) {
                    //    $realFlagIdD = 12;
                    //} else {
                    //    $realFlagIdD = $this->biosDataTables[self::TABLE_FLAGS_BITFIELDS][$condDecodeD];
                    //}

                    $realFlagIdA = $this->biosDataTables[self::TABLE_FLAGS_BITFIELDS][$condDecodeA];
                    $realFlagIdB = $this->biosDataTables[self::TABLE_FLAGS_BITFIELDS][$condDecodeB];
                    $realFlagIdC = $this->biosDataTables[self::TABLE_FLAGS_BITFIELDS][$condDecodeC];
                    $realFlagIdD = $this->biosDataTables[self::TABLE_FLAGS_BITFIELDS][$condDecodeD];

                    $flagA = $this->flags->get($realFlagIdA);
                    $flagB = $this->flags->get($realFlagIdB);
                    $flagC = $this->flags->get($realFlagIdC);
                    $flagD = $this->flags->get($realFlagIdD);

                    $this->output->writeln(sprintf('JMP w=%d e=%b f=%d d0=%d', $iw, $extra, $flagId, $dataByte[0]));

                    $this->output->writeln(sprintf(' -> A org: %d', $condDecodeA));
                    $this->output->writeln(sprintf(' -> B org: %d', $condDecodeB));
                    $this->output->writeln(sprintf(' -> C org: %d', $condDecodeC));
                    $this->output->writeln(sprintf(' -> D org: %d', $condDecodeD));

                    $this->output->writeln(sprintf(' -> Flag A: %s %d', $realFlagIdA, $flagA));
                    $this->output->writeln(sprintf(' -> Flag B: %s %d', $realFlagIdB, $flagB));
                    $this->output->writeln(sprintf(' -> Flag C: %s %d', $realFlagIdC, $flagC));
                    $this->output->writeln(sprintf(' -> Flag D: %s %d', $realFlagIdD, $flagD));

                    $flagsVal1 =
                        $flagA
                        || $flagB
                        || $flagC ^ $flagD;
                    $flagsVal2 = $flagsVal1 ^ $iw;
                    $add = $dataByte[0] * $flagsVal2;

                    $this->debugCsIpRegister();
                    if ($add) {
                        $this->ip->add($add);
                    }
                    $this->debugCsIpRegister();
                    break;

                case 1: // MOV reg, imm - OpCodes: b0 b1 b2 b3 b4 b5 b6 b7 b8 b9 ba bb bc bd be bf
                    $iw = (bool)($opcodeRaw & 8); // xxxx1xxx

                    $register = $this->getRegisterByNumber($iw, $iReg4bit);

                    if ($iw) {
                        $register->setData($dataWord[0]);
                    } else {
                        $register->setData($dataByte[0]);
                    }

                    $this->output->writeln(sprintf('MOV reg, imm (reg=%s)', $register));
                    break;

                case 3: // PUSH reg - OpCodes: 50 51 52 53 54 55 56 57
                    $register = $this->getRegisterByNumber(true, $iReg4bit);
                    $this->output->writeln(sprintf('PUSH %s', $register));
                    $this->pushToStack($register, self::SIZE_BYTE);
                    break;

                case 4: // POP reg - OpCodes: 58 59 5a 5b 5c 5d 5e 5f
                    $register = $this->getRegisterByNumber(true, $iReg4bit);
                    $this->output->writeln(sprintf('POP %s', $register));
                    $stackData = $this->popFromStack(self::SIZE_BYTE);
                    $register->setData($stackData);
                    break;

                case 8: // ADD|OR|ADC|SBB|AND|SUB|XOR|CMP reg, immed OpCodes: 80 81 82 83
                    $this->output->writeln(sprintf('08: mod=%b reg=%b r/m=%s s=%b w=%d/%d e=%b ip=%s', $iMod, $iReg, $rm, $id, $iw, !$iw, $extra, $this->ip));
                    $this->output->writeln(sprintf('data2 %x %x', $dataWord[2], $dataByte[2]));
                    //$extra = $iReg;

                    $id |= !$iw;

                    // I don't know what's this good for.
                    if ($id) {
                        $from = $dataWord[2] & 0xFF;
                    } else {
                        $from = $dataWord[2];
                    }

                    $this->output->writeln(sprintf('FROM %s', $from));

                    $this->ip->add(!$id + 1);
                    //$this->output->writeln(sprintf('08: ip=%s', $this->ip));

                    // Decode
                    $opcodeRaw = 0x8 * $iReg;
                    //$xlatId = $this->biosDataTables[self::TABLE_XLAT_OPCODE][$opcodeRaw];
                    $extra = $this->biosDataTables[self::TABLE_XLAT_SUBFUNCTION][$opcodeRaw];
                    //$iModeSize = $this->biosDataTables[self::TABLE_I_MOD_SIZE][$opcodeRaw];

                    $this->output->writeln(sprintf('%02x: mod=%b reg=%b r/m=%s s=%b w=%d/%d e=%b ip=%s', $opcodeRaw, $iMod, $iReg, $rm, $id, $iw, !$iw, $extra, $this->ip));
                // no break

                case 9: // ADD|OR|ADC|SBB|AND|SUB|XOR|CMP|MOV reg, r/m - OpCodes: 00 01 02 03 08 09 0a 0b 10 11 12 13 18 19 1a 1b 20 21 22 23 28 29 2a 2b 30 31 32 33 38 39 3a 3b 88 89 8a 8b
                    switch ($extra) {
                        case 0: // ADD
                            throw new NotImplementedException(sprintf('ADD'));
                            break;

                        case 6: // XOR
                            $this->output->writeln(sprintf('XOR reg, r/m'));
                            $this->output->writeln(sprintf(' -> FROM %s', $from));
                            $this->output->writeln(sprintf(' -> TO   %s', $to));
                            if ($from instanceof Register && $to instanceof Register) {
                                $opResult = $from->toInt() ^ $to->toInt();
                                $to->setData($opResult);
                            } else {
                                throw new NotImplementedException(sprintf('XOR else'));
                            }
                            break;

                        case 7: // CMP
                            //throw new NotImplementedException(sprintf('CMP'));
                            if (is_numeric($from) && $to instanceof Address) {
                                //$op2 = $this->ram->read($from->toInt(), $length);
                                $opSource = $from;

                                $to = $this->ram->read($to->toInt(), $iwSize);
                                if ($iwSize == 2) {
                                    $opDest = ($to[1] << 8) | $to[0];
                                } else {
                                    $opDest = $to[0];
                                }

                                $opResult = $opDest - $opSource;
                            } else {
                                throw new NotImplementedException(sprintf('CMP else'));
                            }
                            $cf = $opResult > $opDest;
                            $this->flags->setByName('CF', $cf);

                            $this->output->writeln(sprintf('CMP %b %b => %b CF=%d', $opDest, $opSource, $opResult, $cf));

                            break;

                        case 8: // MOV
                            $this->output->writeln(sprintf('MOV reg, r/m'));
                            $this->output->writeln(sprintf(' -> FROM %s', $from));
                            $this->output->writeln(sprintf(' -> TO   %s', $to));

                            if ($from instanceof Register && $to instanceof Register) {
                                throw new NotImplementedException('from REG to REG');
                            } elseif ($from instanceof Register && $to instanceof Address) {
                                //$this->ram->writeRegisterToAddress($from, $to);
                                $offset = $to->toInt();
                                $data = $from->getData();
                                $this->ram->write($data, $offset);
                            } elseif ($from instanceof Address && $to instanceof Register) {
                                throw new NotImplementedException('from ADDR to REG');
                            } elseif ($from instanceof Address && $to instanceof Address) {
                                throw new NotImplementedException('from ADDR to ADDR');
                            }
                            break;

                        default:
                            throw new NotImplementedException(sprintf('else %d %b', $extra, $extra));
                    }
                    break;

                case 10: // MOV sreg, r/m | POP r/m | LEA reg, r/m - OpCodes: 8c 8d 8e 8f
                    if (!$iw) {
                        // MOV
                        $this->output->writeln(sprintf('MOV sreg, r/m'));

                        $from = $to = $this->getRegisterByNumber(true, $iRm);

                        if ($id) {
                            //$from = $this->getRegisterByNumber(true, $iRm);
                            $to = $this->getSegmentRegisterByNumber($iReg);
                        } else {
                            $from = $this->getSegmentRegisterByNumber($iReg);
                            //$to = $this->getRegisterByNumber(true, $iRm);
                        }

                        $this->output->writeln(sprintf('FROM %s', $from));
                        $this->output->writeln(sprintf('TO   %s', $to));

                        $to->setData($from);

                        $this->output->writeln(sprintf('NEW TO %s', $to));
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
                            $this->ip->setData(0);
                        } else {
                            // CALL
                            $this->output->writeln(sprintf('CALL'));
                            throw new NotImplementedException('CALL');
                        }
                    }

                    if ($id && $iw) {
                        $add = $dataByte[0];
                    } else {
                        $add = $dataWord[0];
                        //throw new NotImplementedException('NOT ID AND NOT IW');
                    }

                    $this->debugCsIpRegister();
                    if ($add) {
                        $this->ip->add($add);
                    }
                    $this->debugCsIpRegister();
                    break;

                case 16: // NOP|XCHG AX, regs16 OpCodes: 90 91 92 93 94 95 96 97
                    $from=$this->getRegisterByNumber($iw, $iReg4bit);
                    $opSource = $from->toInt();
                    $opDest = 0xF0000;
                    // no break

                case 24: // NOP|XCHG reg, r/m
                    throw new NotImplementedException('24');
                    break;

                case 17: // MOVSx|STOSx|LODSx - OpCodes: a4 a5 aa ab ac ad
                    if ($segOverrideEn) {
                        throw new NotImplementedException('SEG REG Override');
                    }
                    if ($repOverrideEn) {
                        throw new NotImplementedException('REP Override');
                    }
                    switch ($extra) {
                        case 0: // MOVSx
                            throw new NotImplementedException('MOVSx');
                            break;

                        case 1: // STOSx
                            $this->output->writeln(sprintf('STOSx %d %d %b', $iw, $extra, $extra));
                            $this->output->writeln(sprintf(' -> REG %s', $this->di));

                            $from = $this->getRegisterByNumber($iw, 0); // AL/AX
                            $this->output->writeln(sprintf(' -> FROM %s', $from));

                            $ea = $this->getEffectiveEsDiAddress();
                            $this->output->writeln(sprintf(' -> EA %04x [%016b]', $ea, $ea));

                            $data = $from->getData();
                            $this->ram->write($data, $ea);

                            $add = (2 * $this->flags->getByName('DF') - 1) * ($iw + 1); // direction flag
                            $this->di->add(-$add);

                            $this->output->writeln(sprintf(' -> REG %s (%d)', $this->di, $add));
                            break;

                        case 2: // LODSx
                            throw new NotImplementedException('LODSx');
                            break;
                    }
                    if ($repOverrideEn) {
                        $this->cx->setData(0);
                    }
                    break;

                case 22: // OUT DX/imm8, AL/AX - OpCodes: e6 e7 ee ef
                    // @link https://pdos.csail.mit.edu/6.828/2010/readings/i386/OUT.htm

                    //$this->output->writeln('<error>OUT DX/imm8, AL/AX</error>');
                    $ahReg = $this->getRegisterByNumber($iw, 0);

                    if ($extra) {
                        $scratch = $this->dx->toInt();
                    } else {
                        $scratch = $dataByte[0];
                    }

                    $this->output->writeln(sprintf('<error>[%s] word=%d extra=%d AL/AH=%s DX=%s v=%x</error>',
                        'PORT', $iw, $extra, $ahReg, $this->dx, $scratch));

                    // @todo create class to handle shit below
                    // handle Speaker control here
                    // handle PIT rate programming here
                    // handle Graphics here? Hm?
                    // handle Speaker here
                    // handle CRT video RAM start offset
                    // handle CRT cursor position
                    break;

                case 25: // PUSH sreg - OpCodes: 06 0e 16 1e
                    $iReg = $opcodeRaw >> 3 & 3; // xxx11xxx
                    $register = $this->getSegmentRegisterByNumber($iReg);
                    $this->output->writeln(sprintf('PUSH %s', $register));
                    $this->pushToStack($register, self::SIZE_BYTE);
                    break;

                case 26: // POP sreg - OpCodes: 07 17 1f
                    $iReg = $opcodeRaw >> 3 & 3; // xxx11xxx
                    $register = $this->getSegmentRegisterByNumber($iReg);
                    $this->output->writeln(sprintf('POP %s', $register));
                    $stackData = $this->popFromStack(self::SIZE_BYTE);
                    $register->setData($stackData);
                    break;

                case 27: // xS: segment overrides - OpCodes: 26 2e 36 3e
                    $segOverrideEn = 2;
                    $segOverride = $extra;
                    if ($repOverrideEn) {
                        $repOverrideEn++;
                    }
                    $iReg = ($opcodeRaw >> 3) & 3; // Segment Override Prefix = 001xx110, xx = Register
                    $this->output->writeln(sprintf('SEG override %d %02b', $iReg, $iReg));
                    break;

                case 46: // CLC|STC|CLI|STI|CLD|STD - OpCodes: f8 f9 fa fb fc fd
                    $val = $extra & 1;
                    $flagId = ($extra >> 1) & 7; // xxxx111x
                    $realFlagId = $this->biosDataTables[self::TABLE_FLAGS_BITFIELDS][$flagId];
                    $flagName = $this->flags->getName($realFlagId);
                    $this->output->writeln(sprintf('CLx %02x (=%d [%08b]) ID=%d/%d v=%d F=%s', $extra, $extra, $extra, $flagId, $realFlagId, $val, $flagName));
                    $this->flags->set($realFlagId, (bool)$val);
                    break;

                default:
                    throw new NotImplementedException(sprintf('OP 0x%02x (=%d [%08b]) xLatID 0x%02x (=%d [%08b])',
                        $opcodeRaw, $opcodeRaw, $opcodeRaw,
                        $xlatId, $xlatId, $xlatId
                    ));
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
            $this->debugCsIpRegister();
            if ($add) {
                $this->ip->add($add);
            }
            $this->debugCsIpRegister();

            // If instruction needs to update SF, ZF and PF, set them as appropriate.
            $setFlagsType = $this->biosDataTables[self::TABLE_STD_FLAGS][$opcodeRaw];
            if ($setFlagsType & 1) {
                if (null === $opResult) {
                    throw new NotImplementedException('$opResult has not been set, but maybe it needs to be.');
                }

                $sign = $opResult < 0;

                // unsigned char. For example, int -42 = unsigned char 214
                // Since we deal with Integer values < 256 we only need a 0xFF-mask.
                $ucOpResult = $opResult & 0xFF;

                $this->flags->setByName('SF', $sign);
                $this->flags->setByName('ZF', !$opResult);
                $this->flags->setByName('PF', $this->biosDataTables[self::TABLE_PARITY_FLAG][$ucOpResult]);

                if ($setFlagsType & 2) { // FLAGS_UPDATE_AO_ARITH
                    //throw new NotImplementedException(sprintf('FLAGS TYPE: %d [%04b]', $setFlagsType, $setFlagsType));
                    $this->setAfOfArith($opSource, $opDest, $opResult, $iw);
                }
                if ($setFlagsType & 4) { // FLAGS_UPDATE_OC_LOGIC
                    $this->flags->setByName('CF', false);
                    $this->flags->setByName('OF', false);
                }
            }

            // Update Instruction counter.
            ++$cycle;

            $int8 = false;
            if (0 === $cycle % self::KEYBOARD_TIMER_UPDATE_DELAY) {
                $int8 = true;
            }

            if (0 === $cycle % self::GRAPHICS_UPDATE_DELAY) {
                $this->updateGraphics();
            }

            if ($trapFlag) {
                $this->interrupt(1);
            }
            $trapFlag = $this->flags->getByName('TF');

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
        $isHigh = $regId & 4; // 1xx
        $register = $this->getRegisterByNumber(true, $effectiveRegId, 1 + $loop);
        $data = $register->getData();
        $name = sprintf('%s%s', $register->getName()[0], $isHigh ? 'H' : 'L');
        $subRegister = new Register($name, $isHigh ? $data[1] : $data[0], self::SIZE_BYTE >> 1);
        $subRegister->setParent($register);
        $subRegister->setIsParentHigh($isHigh);
        return $subRegister;
    }

    private function getSegmentRegisterByNumber(int $regId): Register
    {
        $register = $this->segmentRegisters[$regId];
        return $register;
    }

    /**
     * EA of SS:SP
     *
     * @return int
     */
    private function getEffectiveStackPointerAddress(): int
    {
        $ea = ($this->ss->toInt() << 4) + $this->sp->toInt();
        return $ea;
    }

    /**
     * EA of CS:IP
     *
     * @return int
     */
    private function getEffectiveInstructionPointerAddress(): int
    {
        $ea = ($this->cs->toInt() << 4) + $this->ip->toInt();
        return $ea;
    }

    /**
     * EA of ES:DI
     *
     * @return int
     */
    private function getEffectiveEsDiAddress(): int
    {
        $ea = ($this->es->toInt() << 4) + $this->di->toInt();
        return $ea;
    }

    /**
     * r/m EA
     * SIB: Scale*Index+Base
     *
     * @param int $rm
     * @param int $disp
     * @return int
     */
    private function getEffectiveRegisterMemoryAddress(int $rm, int $disp): int
    {
        switch ($rm) {
            case 0: // 000 EA = (BX) + (SI) + DISP
                $ea = $this->bx->toInt() + $this->si->toInt() + $disp;
                break;

            case 1: // 001 EA = (BX) + (DI) + DISP
                $ea = $this->bx->toInt() + $this->di->toInt() + $disp;
                break;

            case 2: // 010 EA = (BP) + (SI) + DISP
                $ea = $this->bp->toInt() + $this->si->toInt() + $disp;
                break;

            case 3: // 011 EA = (BP) + (DI) + DISP
                $ea = $this->bp->toInt() + $this->di->toInt() + $disp;
                break;

            case 4: // 100 EA = (SI) + DISP
                $ea = $this->si->toInt() + $disp;
                break;

            case 5: // 101 EA = (DI) + DISP
                $ea = $this->di->toInt() + $disp;
                break;

            case 6: // 110 EA = (BP) + DISP
                // except if mod = 00 and r/m = 110 then EA = disp-high; disp-low
                // @todo What needs to be done for the comment above?

                $ea = $this->bp->toInt() + $disp;
                break;

            case 7: // 111 EA = (BX) + DISP
                $ea = $this->bx->toInt() + $disp;
                break;

            default:
                throw new \RuntimeException(sprintf('getEffectiveRegisterMemoryAddress invalid: %d %b', $rm, $rm));
        }
        return $ea;
    }

    /**
     * @param Register|Address|int[]|\SplFixedArray $data
     * @param int $size
     */
    private function pushToStack($data, int $size)
    {
        if ($data instanceof RegisterInterface) {
            /** @var Register $register */
            $register = $data;

            //if ($size !== $register->getSize()) {
            //    throw new \RangeException(sprintf('Wrong size. Register is %d bytes, data is %d bytes.', $register->getSize(), $size));
            //}

            $data = $register->getData();
            if (is_iterable($data)) {
                $this->pushToStack($data, $size);
            } elseif (null === $data) {
                $this->pushToStack(new \SplFixedArray($size), $size);
            } else {
                throw new NotImplementedException('ELSE push data B');
            }
        } elseif ($data instanceof AddressInterface) {
            /** @var Address $address */
            $address = $data;
            $this->pushToStack($address->getData(), $size);
        } elseif (is_iterable($data)) {
            $this->debugSsSpRegister();

            $this->sp->add(-$size);
            $ea = $this->getEffectiveStackPointerAddress();
            $this->ram->write($data, $ea);

            $this->debugSsSpRegister();
        } else {
            throw new NotImplementedException('ELSE push data A');
        }
    }

    private function popFromStack(int $size): \SplFixedArray
    {
        $ea = $this->getEffectiveStackPointerAddress();
        $data = $this->ram->read($ea, $size);

        $this->sp->add($size);

        return $data;
    }

    private function setAfOfArith(int $src, int $dest, int $result, bool $isWord)
    {
        $topBit = $isWord ? 15 : 7;

        $x = $dest ^ $result;
        $src ^= $x;
        $af = $src & 0x10;
        $this->flags->setByName('AF', $af);

        $cf = $this->flags->getByName('CF');

        if ($result === $dest) {
            $of = 0;
        } else {
            $of = ($cf ^ $src >> $topBit) & 1;
        }
        $this->flags->setByName('OF', $of);
    }

    private function debugSsSpRegister()
    {
        $ea = $this->getEffectiveStackPointerAddress();
        $data = $this->ram->read($ea, self::SIZE_BYTE);
        $this->output->writeln(sprintf('%s %s -> %04x [%020b] -> %08b %08b', $this->ss, $this->sp, $ea, $ea, $data[0], $data[1]));
    }

    private function debugCsIpRegister()
    {
        $ea = $this->getEffectiveInstructionPointerAddress();
        $data = $this->ram->read($ea, self::SIZE_BYTE);
        $this->output->writeln(sprintf('%s %s -> %04x [%020b] -> %08b %08b', $this->cs, $this->ip, $ea, $ea, $data[0], $data[1]));
    }
}
