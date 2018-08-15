<?php

/**
 * This class holds all stuff that a CPU needs.
 * It's connected to the RAM.
 *
 * @link https://en.wikipedia.org/wiki/Processor_(computing)
 */

namespace TheFox\I8086emu\Machine;

use Carbon\Carbon;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use TheFox\I8086emu\Blueprint\CpuInterface;
use TheFox\I8086emu\Blueprint\DebugAwareInterface;
use TheFox\I8086emu\Blueprint\MachineInterface;
use TheFox\I8086emu\Blueprint\OutputDeviceInterface;
use TheFox\I8086emu\Blueprint\RamInterface;
use TheFox\I8086emu\Components\AbsoluteAddress;
use TheFox\I8086emu\Components\Address;
use TheFox\I8086emu\Components\Flags;
use TheFox\I8086emu\Components\Memory;
use TheFox\I8086emu\Components\Register;
use TheFox\I8086emu\Exception\NotImplementedException;
use TheFox\I8086emu\Exception\UnknownTypeException;
use TheFox\I8086emu\Exception\ValueExceededException;
use TheFox\I8086emu\Helper\DataHelper;
use TheFox\I8086emu\Helper\NumberHelper;

class Cpu implements CpuInterface, DebugAwareInterface
{
    public const SIZE_BYTE = 2;
    public const KEYBOARD_TIMER_UPDATE_DELAY = 20000;
    public const GRAPHICS_UPDATE_DELAY = 50000; // Original 360000
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
    public const FLAGS_UPDATE_SZP = 1;
    public const FLAGS_UPDATE_AO_ARITH = 2;
    public const FLAGS_UPDATE_OC_LOGIC = 4;

    /**
     * @var MachineInterface
     */
    private $machine;

    /**
     * Debug
     * @var OutputInterface
     */
    private $output;

    /**
     * @var int
     */
    private $runLoop;

    /**
     * @var RamInterface|Ram|DebugRam
     */
    private $ram;

    /**
     * @var TtyOutputDevice
     */
    private $tty;

    /**
     * IO Ports
     *
     * @var array
     */
    private $io;

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

    /**
     * Default Segment Register
     *
     * @var null|Register
     */
    private $segDefaultReg;

    /**
     * Segment Override Enabled?
     *
     * @var int
     */
    private $segOverrideEn;

    /**
     * Register ID
     *
     * @var int
     */
    private $segOverrideReg;

    /**
     * Repeat Enabled?
     *
     * @var int
     */
    private $repOverrideEn;

    /**
     * Repeat Mode
     *
     * @var int
     */
    private $repOverrideMode;

    /**
     * Current Instruction Informations
     *
     * @var array
     */
    private $instr;

    /**
     * Arithmetic Operation
     *
     * @var array
     */
    private $op;

    /**
     * @var bool
     */
    private $trapFlag;

    /**
     * @var bool
     */
    private $int8;

    public function __construct(MachineInterface $machine)
    {
        $this->machine = $machine;

        $this->output = new NullOutput();
        $this->runLoop = 0;

        $this->segDefaultReg = null;
        $this->segOverrideEn = 0;
        $this->segOverrideReg = 0;

        $this->repOverrideEn = 0;
        $this->repOverrideMode = 0;

        $this->instr = [
            'raw' => 0,
            'raw_low3' => 0,
            'data_b' => null, // Byte
            'data_w' => null, // Word
            'set_flags_type' => 0,

            'xlat' => 0,
            'extra' => 0,

            'size' => 0, // @var int // Size in byte: 1 or 2.
            'bsize' => 0, // @var int // Size in bit: 8 or 16.

            'is_word' => false,
            'dir' => 0, // Direction

            // Mod/Reg/RM -- Is Enabled?
            'has_modregrm' => 0, // @var int // 0 or 1 -- i_mod_adder Table

            // Mod/Reg/RM -- 1. Part: 'Mode'
            'mode' => 0, // @var int

            // Mod/Reg/RM -- 2. Part: 'Register'
            'reg' => 0, // @var int

            // Mod/Reg/RM -- 3. Part: 'Register/Memory'
            // Sometimes als referred as known as 'R/M'.
            'rm_i' => 0,  // Number
            'rm_o' => null, // @var AbsoluteAddress|Address|Register

            'from' => null,
            'to' => null,
        ];

        // Arithmetic Operation
        $this->op = [
            'src' => null,
            'dst' => null,

            // Needs to be null for development.
            // Because?
            'res' => null,
        ];

        $this->trapFlag = false;
        $this->int8 = false;

        // IO Ports
        $this->io = new \SplFixedArray(0x10000);

        $this->setupRegisters();
        $this->setupFlags();
    }

    /**
     * General-Purpose Registers (GPR) - 16-bit naming conventions
     */
    private function setupRegisters()
    {
        // Common register
        $this->ax = new Register(self::SIZE_BYTE, null, 'AX'); // AX: Accumulator
        $this->cx = new Register(self::SIZE_BYTE, null, 'CX'); // CX: Count
        $this->dx = new Register(self::SIZE_BYTE, null, 'DX'); // DX: Data
        $this->bx = new Register(self::SIZE_BYTE, null, 'BX'); // BX: Base

        // Pointer
        $this->sp = new Register(self::SIZE_BYTE, null, 'SP'); // Stack Pointer
        $this->bp = new Register(self::SIZE_BYTE, null, 'BP'); // Base Pointer

        // Index
        $this->si = new Register(self::SIZE_BYTE, null, 'SI'); // Source Index
        $this->di = new Register(self::SIZE_BYTE, null, 'DI'); // Destination Index

        // Segment
        $this->ds = new Register(self::SIZE_BYTE, null, 'DS'); // Data Segment
        $this->es = new Register(self::SIZE_BYTE, null, 'ES'); // Extra Segment
        $this->ss = new Register(self::SIZE_BYTE, null, 'SS'); // Stack Segment

        // Set CS:IP to F000:0100
        $this->cs = new Register(self::SIZE_BYTE, 0xF000, 'CS'); // Code Segment
        $this->ip = new Register(self::SIZE_BYTE, 0x0100, 'IP'); // Instruction Pointer

        $this->zero = new Register(self::SIZE_BYTE, null, 'ZERO');

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

        // Debug int1e SPT
        // $fn = function (array $eventData) {
        //     // $this->output->writeln(sprintf(' -> EVENT_CALLBACK'));
        //     [
        //         'offset' => $offset,
        //         'length' => $length,
        //     ] = $eventData;
        //     // $this->output->writeln(sprintf(' -> offset %x', $offset));
        //     // $this->output->writeln(sprintf(' -> length %d', $length));
        //
        //     $sptAddr = 0xf1039; // Prod
        //     // $sptAddr = 0xf1044; // Dev
        //     $end = $offset + $length;
        //     if ($sptAddr >= $offset && $sptAddr <= $end) {
        //         $this->output->writeln(sprintf(' -> EVENT_CALLBACK OK'));
        //     }
        // };
        // $event = new Event(DebugRam::EVENT_WRITE_POST, $fn);
        // $this->ram->addEvent($event);
        //
        // $event = new Event(DebugRam::EVENT_READ_PRE, $fn);
        // $this->ram->addEvent($event);
    }

    /**
     * @param OutputDeviceInterface $tty
     */
    public function setTty(OutputDeviceInterface $tty): void
    {
        $this->tty = $tty;
    }

    /**
     * @param OutputInterface $output
     */
    public function setOutput(OutputInterface $output): void
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
        $address = $this->getEffectiveInstructionPointerAddress();
        $offset = $address->toInt();

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

        for ($i = 0; $i < 20; ++$i) {
            $offset = 0xF0000 + (0x81 + $i) * self::SIZE_BYTE;
            $data = $this->ram->read($offset, self::SIZE_BYTE);
            $addr = ($data[1] << 8) | $data[0];

            // $this->output->writeln(sprintf('table %d', $i));

            if (isset(self::TABLE_LENGTHS[$i])) {
                $tableLength = self::TABLE_LENGTHS[$i];
            } else {
                $tableLength = 256;
            }

            for ($j = 0; $j < $tableLength; ++$j) {
                $valueAddr = 0xF0000 + $addr + $j;
                $v = $this->ram->read($valueAddr, 1);

                $tables[$i][$j] = $v[0];
            }
        }

        $this->biosDataTables = $tables;
        $this->output->writeln('bios data tables done');
    }

    private function printXlatOpcodes()
    {
        // $this->output->writeln('XLAT');

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
        $this->printXlatOpcodes();

        // Initialize TTY.
        $this->tty->init();

        // Debug
        $this->output->writeln(sprintf('CS: %04x', $this->cs->toInt()));
        $this->output->writeln(sprintf('IP: %04x', $this->ip->toInt()));

        // $fh = fopen("/Users/thefox/work/dev/i8086emu/log/i8086emu_debug.log", "w");

        while ($this->instr['raw'] = $this->getOpcode()) {
            $this->initInstruction();

            $ipAddress = $this->getEffectiveInstructionPointerAddress();
            $ipOffset = $ipAddress->toInt();

            // Sometimes an instruction is longer than 1 byte.
            // Prepare additional data bytes as Byte and Word type.
            $this->instr['data_b'] = $this->ram->read($ipOffset + 1, 5);
            $this->instr['data_w'] = \SplFixedArray::fromArray([
                ($this->instr['data_b'][1] << 8) | $this->instr['data_b'][0],
                ($this->instr['data_b'][2] << 8) | $this->instr['data_b'][1],
                ($this->instr['data_b'][3] << 8) | $this->instr['data_b'][2],
                ($this->instr['data_b'][4] << 8) | $this->instr['data_b'][3],
            ]);

            $this->debugInfo(sprintf(
                '[%s] run %d %04x:%04x    OP %02x %d    XLAT %02x %d',
                'CPU',
                $this->runLoop,
                $this->cs->toInt(),
                $this->ip->toInt(),
                $this->instr['raw'],
                $this->instr['raw'],
                $this->instr['xlat'],
                $this->instr['xlat']
            ));

            // Segment Register Override
            if ($this->segOverrideEn) {
                --$this->segOverrideEn;
                $this->segDefaultReg = $this->getRegisterByNumber(true, $this->segOverrideReg);
            } else {
                // Set Data Segment as the Default Segment Register.
                $this->segDefaultReg = $this->ds;
            }
            if ($this->repOverrideEn) {
                --$this->repOverrideEn;
            }

            // $this->instr['has_modregrm'] > 0 indicates that opcode uses Mod/Reg/RM, so decode them.
            if ($this->instr['has_modregrm']) {
                $this->instr['mode'] = $this->instr['data_b'][0] >> 6;     // 11xxxxxx
                $this->instr['reg'] = $this->instr['data_b'][0] >> 3 & 7;  // xx111xxx
                $this->instr['rm_i'] = $this->instr['data_b'][0] & 7;      // xxxxx111

                switch ($this->instr['mode']) {
                    case 0: // 00
                    case 1: // 01
                    case 2: // 10
                        if (0 === $this->instr['mode'] && 6 === $this->instr['rm_i'] || 2 === $this->instr['mode']) {
                            // *except if mod = 00 and r/m = 110 then EA = disp-high; disp-low
                            // if mod = 10 then DISP = disp-high; disp-low
                            $this->instr['data_w'][2] = $this->instr['data_w'][3];
                            $this->instr['data_b'][2] = $this->instr['data_w'][2] & 0xFF;
                        } else { // $this->instr['mode'] == 1
                            // If i_mod is 1, operand is (usually) 8 bits rather than 16 bits.
                            $this->instr['data_w'][1] = $this->instr['data_b'][1];
                        }
                        break;

                    case 3: // 11
                        $this->instr['data_w'][2] = $this->instr['data_w'][1];
                        $this->instr['data_b'][2] = $this->instr['data_w'][2] & 0xFF;
                        break;

                    default:
                        throw new UnknownTypeException();
                }

                $this->decodeRegisterMemory();

                // $this->output->writeln(sprintf(' -> <info>FROM %s</info>', $this->instr['from']));
                // $this->output->writeln(sprintf(' -> <info>TO   %s</info>', $this->instr['to']));
                // $this->output->writeln(sprintf(' -> <info>RM   %s (%d)</info>', $this->instr['rm_o'], $this->instr['rm_i']));
            }

            // fwrite(STDERR, sprintf("OP %d: %d\n", $this->runLoop, $this->instr['xlat']));

            // if ($this->runLoop >= 65753) {
            //     exit(0);
            // }

            switch ($this->instr['xlat']) {
                // JMP Conditional jump (JAE, JNAE, etc.) - OpCodes: 70 71 72 73 74 75 76 77 78 79 7a 7b 7c 7d 7e 7f f1
                case 0:
                    // $this->instr['is_word'] is the invert Flag.
                    // For example, $this->instr['is_word'] == 0 means JAE, $this->instr['is_word'] == 1 means JNAE

                    $this->debugOp(sprintf(
                        'JMP b=%x w=%x',
                        $this->instr['data_b'][0],
                        $this->instr['data_w'][0]
                    ));

                    $flagId = ($this->instr['raw'] >> 1) & 7; // xxxx111x

                    $condDecodeA = $this->biosDataTables[self::TABLE_COND_JUMP_DECODE_A][$flagId];
                    $condDecodeB = $this->biosDataTables[self::TABLE_COND_JUMP_DECODE_B][$flagId];
                    $condDecodeC = $this->biosDataTables[self::TABLE_COND_JUMP_DECODE_C][$flagId];
                    $condDecodeD = $this->biosDataTables[self::TABLE_COND_JUMP_DECODE_D][$flagId];

                    $realFlagIdA = $this->biosDataTables[self::TABLE_FLAGS_BITFIELDS][$condDecodeA];
                    $realFlagIdB = $this->biosDataTables[self::TABLE_FLAGS_BITFIELDS][$condDecodeB];
                    $realFlagIdC = $this->biosDataTables[self::TABLE_FLAGS_BITFIELDS][$condDecodeC];
                    $realFlagIdD = $this->biosDataTables[self::TABLE_FLAGS_BITFIELDS][$condDecodeD];

                    $flagA = $this->flags->get($realFlagIdA);
                    $flagB = $this->flags->get($realFlagIdB);
                    $flagC = $this->flags->get($realFlagIdC);
                    $flagD = $this->flags->get($realFlagIdD);

                    // $this->output->writeln(sprintf(' -> flag %2d %s = %d', $realFlagIdA, $this->flags->getName($realFlagIdA), $flagA));
                    // $this->output->writeln(sprintf(' -> flag %2d %s = %d', $realFlagIdB, $this->flags->getName($realFlagIdB), $flagB));
                    // $this->output->writeln(sprintf(' -> flag %2d %s = %d', $realFlagIdC, $this->flags->getName($realFlagIdC), $flagC));
                    // $this->output->writeln(sprintf(' -> flag %2d %s = %d', $realFlagIdD, $this->flags->getName($realFlagIdD), $flagD));

                    $data = NumberHelper::unsignedIntToChar($this->instr['data_b'][0]);

                    $flagsVal1 =
                        $flagA
                        || $flagB
                        || $flagC ^ $flagD;
                    $flagsVal2 = $flagsVal1 ^ $this->instr['is_word'];
                    $add = $data * $flagsVal2;

                    $this->output->writeln(sprintf(' -> ADD %d %x', $add, $add));

                    // $this->debugCsIpRegister();
                    if ($add) {
                        $this->ip->add($add);
                    }
                    // $this->debugCsIpRegister();
                    break;

                // MOV reg, imm - OpCodes: b0 b1 b2 b3 b4 b5 b6 b7 b8 b9 ba bb bc bd be bf
                case 1:
                    $this->instr['is_word'] = boolval($this->instr['raw'] & 8); // xxxx1xxx
                    $this->initInstrSize();

                    $tmpFrom = $this->instr['is_word'] ? $this->instr['data_w'][0] : $this->instr['data_b'][0];
                    $tmpTo = $this->getRegisterByNumber($this->instr['is_word'], $this->instr['raw_low3']);

                    $this->debugOp(sprintf('MOV %s %x', $tmpTo, $tmpFrom));

                    $tmpTo->setData($tmpFrom);
                    $this->output->writeln(sprintf(' -> REG %s', $tmpTo));
                    break;

                // INC|DEC reg - OpCodes: 40 41 42 43 44 45 46 47 48 49 4a 4b 4c 4d 4e 4f
                case 2:
                    $this->instr['dir'] = ($this->instr['raw'] >> 3) & 1; // xxxx1xxx
                    $this->instr['is_word'] = true;
                    $this->initInstrSize();

                    $register = $this->getRegisterByNumber($this->instr['is_word'], $this->instr['raw_low3']);

                    if ($this->instr['dir']) {
                        $this->debugOp(sprintf('DEC %s', $register));
                    } else {
                        $this->debugOp(sprintf('INC %s', $register));
                    }

                    $this->op['dst'] = $register->toInt();
                    $add = 1 - ($this->instr['dir'] << 1);
                    $register->add($add);

                    $this->op['res'] = $register->toInt();

                    $af = $this->setAuxiliaryFlagArith(1, $this->op['dst'], $this->op['res']);
                    $of = $this->setOverflowFlagArith2($this->op['dst'], $this->instr['size'], $this->instr['dir']);

                    // $this->output->writeln(sprintf(' -> REG %s add=%d AF=%d OF=%d', $register, $add, $af, $of));
                    // $this->output->writeln(sprintf(' -> AF %d', $this->flags->getByName('AF')));
                    // $this->output->writeln(sprintf(' -> OF %d', $this->flags->getByName('OF')));
                    break;

                // INC|DEC|JMP|CALL|PUSH - OpCodes: fe ff
                case 5:
                    $this->debugOp(sprintf('INC|DEC|JMP|CALL|PUSH reg=%d f=%s d=%b', $this->instr['reg'], $this->instr['from'], $this->instr['data_b'][0]));

                    switch ($this->instr['reg']) {
                        case 0:
                        case 1: // INC|DEC [loc]
                            $this->instr['dir'] = boolval($this->instr['reg']);
                            $add = 1 - ($this->instr['dir'] << 1);

                            $this->op['src'] = 0;

                            if ($this->instr['from'] instanceof Register) {
                                $this->op['dst'] = $this->instr['from']->toInt();
                                $this->op['res'] = $this->instr['from']->add($add);

                                $this->output->writeln(sprintf(' -> %s', $this->instr['from']));
                            } elseif ($this->instr['from'] instanceof AbsoluteAddress) {
                                $offset = $this->instr['from']->toInt();
                                $data = $this->ram->read($offset, $this->instr['size']);

                                $this->op['dst'] = DataHelper::arrayToInt($data);
                                $this->op['res'] = $this->op['dst'] + $add;

                                $this->ram->write($this->op['res'], $offset, $this->instr['size']);
                            }

                            $this->output->writeln(sprintf(' -> SRC %04x', $this->op['src']));
                            $this->output->writeln(sprintf(' -> DST %04x', $this->op['dst']));
                            $this->output->writeln(sprintf(' -> RES %04x', $this->op['res']));

                            // Auxiliary Flag
                            $tmpAf = $this->setAuxiliaryFlagArith(1, $this->op['dst'], $this->op['res']);

                            // Overflow Flag
                            $tmpOf = $this->setOverflowFlagArith2($this->op['dst'], $this->instr['size'], $this->instr['dir']);

                            // Debug
                            $this->debugOp(sprintf('%s reg=%d d=%x d0=%08b w=%d AF=%d OF=%d RES=0x%x',
                                $this->instr['dir'] ? 'DEC' : 'INC',
                                $this->instr['reg'],
                                $this->instr['dir'],
                                $this->instr['data_b'][0],
                                $this->instr['is_word'],
                                $tmpAf, $tmpOf,
                                $this->op['res']));

                            // Decode like ADC.
                            // We need that later.
                            $this->instr['raw'] = 0x10; // needed
                            $this->instr['xlat'] = $this->biosDataTables[self::TABLE_XLAT_OPCODE][$this->instr['raw']];
                            break;

                        default:
                            throw new NotImplementedException(sprintf('REG %d', $this->instr['reg']));
                            break;
                    }
                    break;

                // PUSH reg - OpCodes: 50 51 52 53 54 55 56 57
                case 3:
                    $register = $this->getRegisterByNumber(true, $this->instr['raw_low3']);
                    $this->debugOp(sprintf('PUSH %s', $register));
                    $this->pushRegisterToStack($register);
                    break;

                // POP reg - OpCodes: 58 59 5a 5b 5c 5d 5e 5f
                case 4:
                    $register = $this->getRegisterByNumber(true, $this->instr['raw_low3']);
                    $stackData = $this->popFromStack(self::SIZE_BYTE);
                    $register->setData($stackData);
                    $this->debugOp(sprintf('POP %s', $register));
                    break;

                // TEST r/m, imm16 / NOT|NEG|MUL|IMUL|DIV|IDIV reg - OpCodes: f6 f7
                case 6:
                    $tmpTo = $this->instr['from'];

                    switch ($this->instr['reg']) {
                        // TEST
                        case 0:
                            // Decode like AND.
                            $this->instr['raw'] = 0x20;
                            $this->instr['xlat'] = $this->biosDataTables[self::TABLE_XLAT_OPCODE][$this->instr['raw']];
                            //$this->$this->instr['extra'] = $this->biosDataTables[self::TABLE_XLAT_SUBFUNCTION][$this->instr['raw']];
                            $this->instr['has_modregrm'] = $this->biosDataTables[self::TABLE_I_MOD_SIZE][$this->instr['raw']];

                            $add = 1;
                            if ($this->instr['is_word']) {
                                ++$add;
                            }
                            $this->ip->add($add);

                            if ($this->instr['is_word']) {
                                $data = $this->instr['data_w'][2];
                            } else {
                                $data = $this->instr['data_b'][2];
                            }
                            $this->debugOp(sprintf('TEST %s %04x', $tmpTo, $data));

                            $this->op['src'] = $data;
                            $this->op['dst'] = $tmpTo->toInt();
                            $this->op['res'] = $this->op['dst'] & $this->op['src'];
                            // $this->output->writeln(sprintf(' -> RES %08b', $this->op['res']));
                            break;

                        // NOT
                        case 2:
                            $this->debugOp(sprintf('NOT %s', $tmpTo));

                            if ($tmpTo instanceof Register) {
                                $this->op['src'] = $tmpTo->toInt();
                                $this->op['dst'] = ~$this->op['src'];
                            } else {
                                throw new UnknownTypeException();
                            }

                            $this->op['res'] = $this->op['dst'];

                            $tmpTo->setData($this->op['dst']);
                            // $this->output->writeln(sprintf(' -> %s', $tmpTo));
                            break;

                        // NEG
                        case 3:
                            $this->debugOp(sprintf('NEG %s', $tmpTo));

                            $this->instr['raw'] = 0x28; // Decode like SUB
                            $this->instr['xlat'] = $this->biosDataTables[self::TABLE_XLAT_OPCODE][$this->instr['raw']];

                            if ($tmpTo instanceof Register) {
                                $this->op['src'] = $tmpTo->toInt();
                                $this->op['dst'] = -$this->op['src'];
                            } else {
                                throw new UnknownTypeException();
                            }

                            $this->op['res'] = $this->op['dst'];

                            $tmpTo->setData($this->op['dst']);
                            $this->output->writeln(sprintf(' -> %s', $tmpTo));

                            $this->op['dst'] = 0;

                            // CF
                            $tmpCf = $tmpTo->toInt() > $this->op['dst'];
                            $this->flags->setByName('CF', $tmpCf);
                            $this->output->writeln(sprintf(' -> CF %d', $tmpCf));
                            break;

                        // MUL|IMUL
                        case 4:
                        case 5:
                            $this->debugOp(sprintf('MUL %s (%d)', $tmpTo, $this->instr['reg']));

                            $this->instr['raw'] = 0x10;
                            $this->instr['xlat'] = $this->biosDataTables[self::TABLE_XLAT_OPCODE][$this->instr['raw']];

                            if ($this->instr['is_word']) {
                                $multiplicand = $this->ax;
                            } else {
                                $multiplicand = $this->ax->getChildRegister();
                            }

                            // $this->output->writeln(sprintf(' -> %s', $multiplicand));

                            if ($tmpTo instanceof Register) {

                                $this->op['src'] = $tmpTo->toInt();
                                $this->op['res'] = $this->op['src'] * $multiplicand->toInt();

                                $multiplicand->setData($this->op['res']);
                                // $this->output->writeln(sprintf(' -> %s %d', $multiplicand, $multiplicand->toInt()));

                                if ($this->instr['is_word']) {
                                    // Touch DX only when a word operator is used.
                                    $tmpDx = $this->op['res'] >> 16;
                                    $this->dx->setData($tmpDx);
                                }
                                // $this->output->writeln(sprintf(' -> %s %d', $this->dx, $this->dx->toInt()));

                                // if ($this->instr['reg']==4)
                                if ($this->instr['is_word']) {
                                    $tmpCf = $this->op['res'] !== 0;
                                } else {
                                    $tmpCf = (0xFF00 & $this->op['res']) !== 0;
                                }

                                $this->flags->setByName('CF', $tmpCf);
                                // $this->output->writeln(sprintf(' -> CF %d', $tmpCf));
                            } else {
                                throw new UnknownTypeException();
                            }
                            break;

                        // DIV
                        case 6:
                            // Divisor
                            /** @var Register $divisor */
                            $divisor = $this->instr['rm_o'];
                            $divisorInt = $divisor->toInt();

                            // Dividend
                            $dividendLow = $this->ax->toInt();

                            if ($this->instr['is_word']) {
                                $dividendHigh = $this->dx->toInt() << 16;
                            } else {
                                $dividendHigh = 0;
                            }

                            $dividend = $dividendHigh | $dividendLow;
                            $this->output->writeln(sprintf(' -> dividend %d %x', $dividend, $dividend));

                            // Debug
                            $this->debugOp(sprintf('DIV %x / %s', $dividend, $divisor));

                            // Quotient
                            $quotient = intval($dividend / $divisorInt);
                            $this->output->writeln(sprintf(' -> quotient %d %x', $quotient, $quotient));

                            // Unsigned Short Quotient
                            $usQuotient = $quotient & 0xFFFF;
                            $this->output->writeln(sprintf(' -> us quotient %d %x', $usQuotient, $usQuotient));

                            // Diff
                            $tmpDiff = $quotient - $usQuotient;
                            $this->output->writeln(sprintf(' -> diff %d %x', $tmpDiff, $tmpDiff));

                            if ($divisorInt && !$tmpDiff) {
                                $this->ax->setData($quotient);

                                // Remainder
                                $remainder = $dividend - $divisorInt * $this->ax->toInt();

                                if ($this->instr['is_word']) {
                                    $this->dx->setData($remainder);
                                } else {
                                    $this->cx->setData($remainder);
                                }
                            } else {
                                $this->output->writeln(sprintf(' -> int0'));
                                $this->interrupt(0);
                            }

                            $this->output->writeln(sprintf(' -> %s', $this->ax));
                            $this->output->writeln(sprintf(' -> %s', $this->cx));
                            $this->output->writeln(sprintf(' -> %s', $this->dx));
                            break;

                        // IDIV
                        case 7:
                            throw new NotImplementedException('IDIV');
                            break;

                        default:
                            throw new UnknownTypeException(sprintf('ireg %d', $this->instr['reg']));
                    }
                    break;

                // CMP reg, imm - OpCodes: 04 05 0c 0d 14 15 1c 1d 24 25 2c 2d 34 35 3c 3d
                case 7:
                    $this->debugOp(sprintf('CMP[7] reg, imm'));

                    $this->instr['rm_o'] = $this->ax;
                    $this->instr['data_b'][2] = $this->instr['data_b'][0];
                    $this->instr['data_w'][2] = $this->instr['data_w'][0];

                    // Will later be switched back.
                    $this->instr['reg'] = $this->instr['extra'];

                    // Correct IP for case 8.
                    $this->ip->add(-1);
                // no break

                // CMP reg, imm - OpCodes: 80 81 82 83
                case 8:
                    $this->instr['to'] = $this->instr['rm_o'];

                    $this->debugOp(sprintf('CMP[8] from=%s to=%s size=%d word=%d dir=%d',
                        $this->instr['from'], $this->instr['to'], $this->instr['size'], $this->instr['is_word'], $this->instr['dir']));

                    $this->instr['dir'] |= !$this->instr['is_word'];

                    if ($this->instr['dir']) {
                        $this->instr['from'] = $this->instr['data_b'][2];
                    } else {
                        $this->instr['from'] = $this->instr['data_w'][2];
                    }

                    $add = !$this->instr['dir'] + 1;
                    $this->ip->add($add);

                    // Decode
                    $this->instr['raw'] = $this->instr['reg'] << 3;
                    $this->instr['xlat'] = $this->biosDataTables[self::TABLE_XLAT_OPCODE][$this->instr['raw']];
                    $this->instr['extra'] = $this->biosDataTables[self::TABLE_XLAT_SUBFUNCTION][$this->instr['raw']];
                    $this->instr['set_flags_type'] = $this->biosDataTables[self::TABLE_STD_FLAGS][$this->instr['raw']];
                // no break

                // ADD|OR|ADC|SBB|AND|SUB|XOR|CMP|MOV reg, r/m
                // OpCodes: 00 01 02 03 08 09 0a 0b 10 11 12 13 18 19 1a 1b 20 21 22 23 28 29 2a 2b 30 31 32 33 38 39 3a 3b 88 89 8a 8b
                case 9:
                    $this->debugOp(sprintf('CMP[9] reg, r/m'));
                    switch ($this->instr['extra']) {
                        // ADD
                        case 0:
                            $tmpFrom = $this->instr['from'];
                            $tmpTo = $this->instr['to'];

                            $this->debugOp(sprintf('ADD %s %s', $tmpTo, $tmpFrom));

                            if (is_numeric($tmpFrom) && $tmpTo instanceof Register) {
                                // FROM  numeric
                                //   TO  Register

                                $this->op['src'] = $tmpFrom;
                                $this->op['dst'] = $tmpTo->toInt();
                            } elseif ($tmpFrom instanceof Register && $tmpTo instanceof Register) {
                                // FROM  Register
                                //   TO  Register

                                $this->op['src'] = $tmpFrom->toInt();
                                $this->op['dst'] = $tmpTo->toInt();
                            } else {
                                throw new UnknownTypeException();
                            }

                            $this->op['dst'] += $this->op['src'];
                            $this->op['res'] = $this->op['dst'];

                            $tmpTo->setData($this->op['dst']);
                            $this->output->writeln(sprintf(' -> %s', $tmpTo));

                            // Write back to RAM.
                            // if ($tmpTo instanceof AbsoluteAddress) {$this->writeAbsoluteAddressToRam($tmpTo, $this->instr['size']);}

                            // CF
                            $tmpCf = $this->op['res'] < $this->op['dst'];
                            $this->flags->setByName('CF', $tmpCf);
                            $this->output->writeln(sprintf(' -> CF %d', $tmpCf));
                            break;

                        // OR
                        case 1:
                            $tmpFrom = $this->instr['from'];
                            $tmpTo = $this->instr['to'];

                            $this->debugOp(sprintf('OR %s %s', $tmpTo, $tmpFrom));

                            if (is_numeric($tmpFrom) && $tmpTo instanceof Register) {
                                $this->op['src'] = $tmpFrom;
                                $this->op['dst'] = $tmpTo->toInt() | $this->op['src'];
                            } else {
                                throw new UnknownTypeException();
                            }

                            $this->op['res'] = $this->op['dst'];

                            $tmpTo->setData($this->op['dst']);
                            $this->output->writeln(sprintf(' -> %s', $tmpTo));

                            // Write back to RAM.
                            // if ($tmpTo instanceof AbsoluteAddress) {$this->writeAbsoluteAddressToRam($tmpTo, $this->instr['size']);}
                            break;

                        // ADC
                        case 2:
                            $tmpFrom = $this->instr['from'];
                            $tmpTo = $this->instr['to'];

                            $this->debugOp(sprintf('ADC %s %s', $tmpTo, $tmpFrom));

                            if (is_numeric($tmpFrom) && $tmpTo instanceof Register) {
                                // FROM  numeric
                                //   TO  Register

                                $this->op['src'] = $tmpFrom;
                                $this->op['dst'] = $tmpTo->toInt() + $this->flags->getByName('CF') + $this->op['src'];
                            } elseif ($tmpFrom instanceof Register && $tmpTo instanceof AbsoluteAddress) {
                                // FROM  Register
                                //   TO  Absolute Address

                                throw new NotImplementedException();
                            } else {
                                throw new UnknownTypeException();
                            }

                            $this->op['res'] = $this->op['dst'];

                            $tmpTo->setData($this->op['dst']);
                            $this->output->writeln(sprintf(' -> %s', $tmpTo));

                            // Write back to RAM.
                            if ($tmpTo instanceof AbsoluteAddress) {
                                $this->writeAbsoluteAddressToRam($tmpTo, $this->instr['size']);
                            }

                            // CF
                            $uiDst = $this->op['dst'] & 0xFFFF;
                            $tmpCf1 = $this->flags->getByName('CF') && ($this->op['res'] == $uiDst);
                            $tmpCf2 = +$this->op['res'] < +$this->op['dst'];
                            $tmpCf = $tmpCf1 || $tmpCf2;
                            $this->flags->setByName('CF', $tmpCf);
                            $this->output->writeln(sprintf(' -> CF %d', $tmpCf));

                            // AF/OF
                            $this->setAuxiliaryFlagArith($this->op['src'], $this->op['dst'], $this->op['res']);
                            $this->setOverflowFlagArith1($this->op['src'], $this->op['dst'], $this->op['res'], $this->instr['is_word']);

                            // Debug
                            $this->output->writeln(sprintf(' -> AF %d', $this->flags->getByName('AF')));
                            $this->output->writeln(sprintf(' -> OF %d', $this->flags->getByName('OF')));
                            break;

                        // SBB
                        case 3:
                            $tmpFrom = $this->instr['from'];
                            $tmpTo = $this->instr['to'];

                            $this->debugOp(sprintf('SBB %s %s', $tmpTo, $tmpFrom));

                            if (is_numeric($tmpFrom) && $tmpTo instanceof Register) {
                                $this->op['src'] = $tmpFrom;
                                $this->op['dst'] = $tmpTo->toInt() - $this->flags->getByName('CF') + $this->op['src'];
                            } else {
                                throw new UnknownTypeException();
                            }

                            $this->op['res'] = $this->op['dst'];

                            $tmpTo->setData($this->op['dst']);
                            $this->output->writeln(sprintf(' -> %s', $tmpTo));

                            // Write back to RAM.
                            // if ($tmpTo instanceof AbsoluteAddress) {$this->writeAbsoluteAddressToRam($tmpTo,$this->instr['size']);}

                            // CF
                            $uiDst = $this->op['dst'] & 0xFFFF;
                            $tmpCf1 = $this->flags->getByName('CF') && ($this->op['res'] == $uiDst);
                            $tmpCf2 = -$this->op['res'] < -$this->op['dst'];
                            $tmpCf = $tmpCf1 || $tmpCf2;
                            $this->flags->setByName('CF', $tmpCf);
                            $this->output->writeln(sprintf(' -> CF %d', $tmpCf));

                            // AF/OF
                            $this->setAuxiliaryFlagArith($this->op['src'], $this->op['dst'], $this->op['res']);
                            $this->setOverflowFlagArith1($this->op['src'], $this->op['dst'], $this->op['res'], $this->instr['is_word']);
                            break;

                        // AND
                        case 4:
                            $tmpFrom = $this->instr['from'];
                            $tmpTo = $this->instr['to'];

                            $this->debugOp(sprintf('AND %s %s', $tmpTo, $tmpFrom));

                            if (is_numeric($tmpFrom) && $tmpTo instanceof Register) {
                                // FROM  numeric
                                //   TO  Register

                                $this->op['src'] = $tmpFrom;
                                $this->op['dst'] = $tmpTo->toInt();
                            } elseif (is_numeric($tmpFrom) && $tmpTo instanceof AbsoluteAddress) {
                                // FROM  numeric
                                //   TO  Absolute Address

                                $this->op['src'] = $tmpFrom;

                                $data = $this->ram->read($tmpTo->toInt(), $this->instr['size']);
                                $this->op['dst'] = DataHelper::arrayToInt($data);
                            } else {
                                throw new UnknownTypeException();
                            }

                            $this->op['dst'] &= $this->op['src'];
                            $this->op['res'] = $this->op['dst'];

                            $tmpTo->setData($this->op['dst']);
                            $this->output->writeln(sprintf(' -> %s', $tmpTo));

                            // Write back to RAM.
                            if ($tmpTo instanceof AbsoluteAddress) {
                                $this->writeAbsoluteAddressToRam($tmpTo, $this->instr['size']);
                            }
                            break;

                        // SUB
                        case 5:
                            $tmpFrom = $this->instr['from'];
                            $tmpTo = $this->instr['to'];

                            $this->debugOp(sprintf('SUB %s %s', $tmpTo, $tmpFrom));

                            if (is_numeric($tmpFrom) && $tmpTo instanceof Register) {
                                // FROM  numeric
                                //   TO  Register

                                $this->op['src'] = $tmpFrom;
                                $this->op['dst'] = $tmpTo->toInt();
                            } elseif ($tmpFrom instanceof AbsoluteAddress && $tmpTo instanceof Register) {
                                // FROM  Absolute Address
                                //   TO  Register

                                $this->output->writeln(sprintf(' -> from %s', $tmpFrom));
                                $this->output->writeln(sprintf(' ->   to %s', $tmpTo));

                                $data = $this->ram->read($tmpFrom->toInt(), $this->instr['size']);

                                $this->op['src'] = DataHelper::arrayToInt($data);
                                $this->op['dst'] = $tmpTo->toInt();
                            } elseif ($tmpFrom instanceof Register && $tmpTo instanceof Register) {
                                // FROM  Register
                                //   TO  Register

                                $this->output->writeln(sprintf(' -> from %s', $tmpFrom));
                                $this->output->writeln(sprintf(' ->   to %s', $tmpTo));

                                $this->op['src'] = $tmpFrom->toInt();
                                $this->op['dst'] = $tmpTo->toInt();
                            } else {
                                throw new UnknownTypeException(sprintf('from=%s to=%s', gettype($tmpFrom), gettype($tmpTo)));
                            }

                            $this->op['dst'] -= $this->op['src'];
                            $this->op['res'] = $this->op['dst'];

                            $tmpTo->setData($this->op['dst']);
                            $this->output->writeln(sprintf(' -> %s', $tmpTo));

                            // Write back to RAM.
                            // if ($tmpTo instanceof AbsoluteAddress)$this->writeAbsoluteAddressToRam($tmpTo, $this->instr['size']);

                            // CF
                            $tmpCf = $this->op['res'] > $this->op['dst'];
                            $this->flags->setByName('CF', $tmpCf);
                            $this->output->writeln(sprintf(' -> CF %d', $tmpCf));
                            break;

                        // XOR
                        case 6:
                            $tmpFrom = $this->instr['from'];
                            $tmpTo = $this->instr['to'];

                            $this->debugOp(sprintf('XOR %s %s', $tmpTo, $tmpFrom));

                            if ($tmpFrom instanceof Register && $tmpTo instanceof Register) {
                                // FROM  Register
                                //   TO  Register

                                $this->op['src'] = $tmpFrom->toInt();
                                $this->op['dst'] = $tmpTo->toInt();
                            } else {
                                throw new UnknownTypeException();
                            }

                            $this->op['dst'] ^= $this->op['src'];
                            $this->op['res'] = $this->op['dst'];

                            $tmpTo->setData($this->op['res']);
                            $this->output->writeln(sprintf(' -> %s', $tmpTo));

                            // Write back to RAM.
                            // if ($tmpTo instanceof AbsoluteAddress)$this->writeAbsoluteAddressToRam($tmpTo, $this->instr['size']);
                            break;

                        // CMP reg, imm
                        case 7:
                            $this->debugOp(sprintf('CMP reg=%s imm=%s', $this->instr['to'], $this->instr['from']));

                            if ($this->instr['from'] instanceof Register && $this->instr['to'] instanceof Register) {
                                // FROM  Register
                                // TO    Register

                                $this->op['src'] = $this->instr['from']->toInt();
                                $this->op['dst'] = $this->instr['to']->toInt();
                            } elseif (is_numeric($this->instr['from']) && $this->instr['to'] instanceof Register) {
                                // FROM  numberic
                                // TO    Register

                                $this->op['src'] = $this->instr['from'];
                                $this->op['dst'] = $this->instr['to']->toInt();
                            } elseif (is_numeric($this->instr['from']) && $this->instr['to'] instanceof AbsoluteAddress) {
                                // FROM  numberic
                                // TO    Absolute Address

                                $this->op['src'] = $this->instr['from'];

                                $offset = $this->instr['to']->toInt();
                                $data = $this->ram->read($offset, $this->instr['size']);
                                $this->op['dst'] = DataHelper::arrayToInt($data);
                            } elseif ($this->instr['from'] instanceof AbsoluteAddress && $this->instr['to'] instanceof Register) {
                                // FROM  Absolute Address
                                // TO    numberic

                                $offset = $this->instr['from']->toInt();
                                $data = $this->ram->read($offset, $this->instr['size']);
                                $this->op['src'] = DataHelper::arrayToInt($data);

                                $this->op['dst'] = $this->instr['to']->toInt();
                            } else {
                                throw new UnknownTypeException();
                            }

                            // Operation
                            $this->op['res'] = $this->op['dst'] - $this->op['src'];

                            // CF dependency
                            if ($this->instr['is_word']) {
                                $tmpUiOpResult = $this->op['res'] & 0xFFFF;
                            } else {
                                $tmpUiOpResult = $this->op['res'] & 0xFF;
                            }

                            // Calc CF
                            $tmpCf = $tmpUiOpResult > $this->op['dst'];

                            // Set new CF.
                            $this->flags->setByName('CF', $tmpCf);

                            $this->output->writeln(sprintf(' -> CF %d', $tmpCf));
                            $this->output->writeln(sprintf(' -> %s', $this->instr['to']));
                            break;

                        // MOV
                        case 8:
                            $this->debugOp(sprintf('MOV %s %s', $this->instr['to'], $this->instr['from']));

                            if ($this->instr['from'] instanceof AbsoluteAddress && $this->instr['to'] instanceof Register) {
                                // FROM  AbsoluteAddress
                                //   TO  Register

                                $offset = $this->instr['from']->toInt();
                                $data = $this->ram->read($offset, $this->instr['to']->getSize());

                                $this->instr['to']->setData($data);

                                // Debug
                                $this->output->writeln(sprintf(' -> %s', $this->instr['to']));
                            } elseif ($this->instr['from'] instanceof Register && $this->instr['to'] instanceof AbsoluteAddress) {
                                // FROM  Register
                                //   TO  AbsoluteAddress

                                $data = $this->instr['from']->getData();

                                $offset = $this->instr['to']->toInt();
                                $this->ram->write($data, $offset, $this->instr['from']->getSize());

                                // Debug
                                $this->output->writeln(sprintf(' -> %s', $this->instr['to']));
                            } elseif ($this->instr['from'] instanceof Register && $this->instr['to'] instanceof Register) {
                                // FROM  Register
                                //   TO  Register

                                $data = $this->instr['from']->getData();
                                $this->instr['to']->setData($data);

                                // Debug
                                $this->output->writeln(sprintf(' -> %s', $this->instr['to']));
                            } else {
                                throw new UnknownTypeException();
                            }
                            break;

                        default:
                            throw new NotImplementedException(sprintf('else e=%d', $this->instr['extra']));
                    }
                    break;

                // MOV sreg, r/m | POP r/m | LEA reg, r/m - OpCodes: 8c 8d 8e 8f
                case 10:
                    if (!$this->instr['is_word']) {
                        // MOV
                        $this->instr['is_word'] = true;
                        $this->initInstrSize();
                        $this->instr['reg'] += 8;

                        $this->decodeRegisterMemory();

                        $this->debugOp(sprintf('MOV %s %s', $this->instr['to'], $this->instr['from']));

                        if ($this->instr['from'] instanceof AbsoluteAddress && $this->instr['to'] instanceof Register) {
                            // FROM  Absolute Address
                            //   TO  Register

                            $offset = $this->instr['from']->toInt();
                            $data = $this->ram->read($offset, $this->instr['to']->getSize());

                            $this->instr['to']->setData($data);

                            // Debug
                            $this->output->writeln(sprintf(' -> %s', $this->instr['to']));
                        } elseif ($this->instr['from'] instanceof Register && $this->instr['to'] instanceof AbsoluteAddress) {
                            // FROM  Register
                            //   TO  Absolute Address

                            $data = $this->instr['from']->getData();

                            $offset = $this->instr['to']->toInt();
                            $this->ram->write($data, $offset, $this->instr['from']->getSize());

                            // Debug
                            $this->output->writeln(sprintf(' -> %s', $this->instr['to']));
                        } elseif ($this->instr['from'] instanceof Register && $this->instr['to'] instanceof Register) {
                            // FROM  Register
                            //   TO  Register

                            $data = $this->instr['from']->toInt();
                            $this->instr['to']->setData($data);

                            // Debug
                            $this->output->writeln(sprintf(' -> REG %s', $this->instr['to']));
                        } else {
                            throw new UnknownTypeException();
                        }
                    } elseif (!$this->instr['dir']) {
                        // LEA
                        // $this->instr['reg'] += 8;
                        $this->debugOp(sprintf('LEA w=%d reg=%d', $this->instr['is_word'], $this->instr['reg']));

                        $tmpFrom = $this->instr['from'];
                        $tmpTo = $this->instr['to'];
                        $this->output->writeln(sprintf(' -> %s', $tmpFrom));
                        $this->output->writeln(sprintf(' -> %s', $tmpTo));

                        $this->segOverrideEn = 1;
                        $this->segOverrideReg = 12; // Zero-Register

                        // Since the direction in this case is always false we have to swap FROM/TO.
                        $this->decodeRegisterMemory();

                        $tmpFrom = $this->instr['to'];
                        $tmpTo = $this->instr['from'];

                        $this->output->writeln(sprintf(' -> %s', $tmpFrom));
                        $this->output->writeln(sprintf(' -> %s', $tmpTo));

                        if ($tmpFrom instanceof AbsoluteAddress && $tmpTo instanceof Register) {
                            // FROM  Absolute Address
                            //   TO  Register

                            $data = $tmpFrom->toInt();
                            // $offset = $tmpTo->toInt();
                            $tmpTo->setData($data);
                        } else {
                            throw new UnknownTypeException();
                        }

                        // Debug
                        $this->output->writeln(sprintf(' -> %s', $tmpTo));
                    } else {
                        // POP
                        $this->debugOp(sprintf('POP'));
                        throw new NotImplementedException('POP');
                    }
                    break;

                // MOV AL/AX, [loc] - OpCodes: a0 a1 a2 a3
                case 11:
                    $address = $this->instr['data_w'][0];
                    $offset = ($this->segDefaultReg->toInt() << 4) + $address;

                    if ($this->instr['is_word']) {
                        $register = $this->ax;
                    } else {
                        $register = $this->ax->getLowRegister();
                    }

                    if ($this->instr['dir']) {
                        // Accumulator to Memory.
                        $this->debugOp(sprintf('MOV [0x%x] %s', $offset, $register));
                        $data = $register->getData();
                        $this->ram->write($data, $offset, $this->instr['size']);
                    } else {
                        // Memory to Accumulator.
                        $this->debugOp(sprintf('MOV %s [0x%x]', $register, $offset));
                        $data = $this->ram->read($offset, $this->instr['size']);
                        $register->setData($data);
                        $this->output->writeln(sprintf(' -> %s', $register));
                    }
                    break;

                // ROL|ROR|RCL|RCR|SHL|SHR|???|SAR reg/mem, 1/CL/imm (80186)
                case 12:
                    /** @var Address $to */
                    $to = $this->instr['rm_o'];
                    $scratch2 = $to->toInt() < 0;

                    $this->debugOp(sprintf('[80186] SHL|SHR|... %s', $to));
                    // $this->output->writeln(sprintf(' -> is_word %d', $this->instr['is_word']));
                    // $this->output->writeln(sprintf(' -> scratch2 %d', $scratch2));

                    if ($this->instr['extra']) {
                        // xxx reg/mem, imm
                        $this->ip->add(1);

                        $scratch = $this->instr['data_b'][1];
                    } elseif ($this->instr['dir']) {
                        // xxx reg/mem, CL
                        $cl = $this->cx->getLowRegister();
                        $scratch = 31 & $cl->toInt();
                    } else {
                        // xxx reg/mem, 1
                        $scratch = 1;
                    }
                    // $this->output->writeln(sprintf(' -> scratch %d', $scratch));

                    $ireg = $this->instr['reg'];
                    // $this->output->writeln(sprintf(' -> ireg %s', $ireg));

                    // $register = $this->getRegisterByNumber($this->instr['is_word'], $ireg);
                    // $this->output->writeln(sprintf(' -> register %s', $register));

                    if ($scratch) {
                        // Rotate operations
                        if ($ireg < 4) {
                            $scratch %= ($ireg >> 1) + $this->instr['bsize'];

                            // $this->output->writeln(sprintf(' -> scratch %d', $scratch));
                            // $this->output->writeln(sprintf(' -> scratch2 %d', $scratch2));
                        }

                        // Rotate/shift right operations
                        $this->op['src'] = $scratch; // R_M_OP
                        $this->op['dst'] = $to->toInt();
                        if ($ireg & 1) {
                            $this->op['res'] = $this->op['dst'] >> $this->op['src'];
                        } else {
                            $this->op['res'] = $this->op['dst'] << $this->op['src'];
                        }

                        $to->setData($this->op['res']);
                        // $this->output->writeln(sprintf(' -> %s', $to));

                        // $this->output->writeln(sprintf(' -> src %d', $this->op['src']));
                        // $this->output->writeln(sprintf(' -> dst %d', $this->op['dst']));
                        // $this->output->writeln(sprintf(' -> res %d', $this->op['res']));

                        // Shift operations
                        if ($ireg > 3) {
                            // Decode like ADC.
                            // We need that later.
                            $this->instr['raw'] = 0x10; // needed
                            $this->instr['xlat'] = $this->biosDataTables[self::TABLE_XLAT_OPCODE][$this->instr['raw']];
                        }

                        // SHR or SAR
                        if ($ireg > 4) {
                            $tmpCf = $this->op['dst'] >> (($scratch - 1) & 1);
                            $this->flags->setByName('CF', $tmpCf);
                            // $this->output->writeln(sprintf(' -> CF %d', $tmpCf));
                        }
                    }

                    switch ($ireg) {
                        // ROL
                        case 0:
                            $this->debugOp(sprintf('[80186] ROL %s', $to));

                            $toInt = $to->toInt();

                            $this->op['dst'] = $toInt;
                            $this->op['src'] = $scratch2 >> ($this->instr['bsize'] - $scratch);

                            $toInt += $this->op['src'];
                            $to->setData($toInt);
                            $this->op['res'] = $toInt;

                            $tmpCf = $this->op['res'] & 1;
                            $tmpOf = intval($this->op['res'] < 0) ^ $tmpCf;

                            $this->flags->setByName('CF', $tmpCf);
                            $this->flags->setByName('OF', $tmpOf);

                            // $this->output->writeln(sprintf(' -> %s', $to));
                            // $this->output->writeln(sprintf(' -> CF %d', $tmpCf));
                            // $this->output->writeln(sprintf(' -> OF %d', $tmpOf));
                            // $this->output->writeln(sprintf(' -> dst %d', $this->op['dst']));
                            // $this->output->writeln(sprintf(' -> src %d', $this->op['src']));
                            // $this->output->writeln(sprintf(' -> res %d', $this->op['res']));
                            break;

                        // ROR
                        case 1:
                            $this->debugOp(sprintf('[80186] ROR %s', $to));

                            $scratch2 = intval($scratch2) & (1 << $scratch) - 1;

                            $toInt = $to->toInt();

                            $this->op['dst'] = $toInt;
                            $this->op['src'] = $scratch2 << ($this->instr['bsize'] - $scratch);

                            $toInt += $this->op['src'];
                            $to->setData($toInt);
                            $this->op['res'] = $toInt;

                            $tmpCf = $this->op['res'] < 0;
                            $tmpOf = intval(($this->op['res'] << 1) < 0) ^ intval($tmpCf);

                            $this->flags->setByName('CF', $tmpCf);
                            $this->flags->setByName('OF', $tmpOf);

                            // $this->output->writeln(sprintf(' -> %s', $to));
                            // $this->output->writeln(sprintf(' -> CF %d', $tmpCf));
                            // $this->output->writeln(sprintf(' -> OF %d', $tmpOf));
                            // $this->output->writeln(sprintf(' -> dst %d', $this->op['dst']));
                            // $this->output->writeln(sprintf(' -> src %d', $this->op['src']));
                            // $this->output->writeln(sprintf(' -> res %d', $this->op['res']));
                            break;

                        // RCL
                        case 2:
                            // @todo FINISH IMPLEMENTATION
                            $this->debugOp(sprintf('[80186] RCL %s', $to));
                            throw new NotImplementedException(sprintf('ireg %d', $ireg));
                            break;

                        // RCR
                        case 3:
                            // @todo FINISH IMPLEMENTATION
                            $this->debugOp(sprintf('[80186] RCR %s', $to));
                            throw new NotImplementedException(sprintf('ireg %d', $ireg));
                            break;

                        // SHL
                        case 4:
                            $this->debugOp(sprintf('[80186] SHL %s', $to));

                            $tmpDst = $this->op['dst'] << ($scratch - 1);

                            // CF
                            $tmpCf1 = $tmpDst < 0;
                            $tmpCf2 = $this->op['dst'] < 0;
                            $tmpCf = $tmpCf1 ^ $tmpCf2;
                            $this->flags->setByName('CF', $tmpCf);
                            $this->output->writeln(sprintf(' -> CF %d', $tmpCf));
                            break;

                        // SHR
                        case 5:
                            $this->debugOp(sprintf('[80186] SHR %s', $to));

                            $tmpCf = $this->op['dst'] < 0;
                            $this->flags->setByName('CF', $tmpCf);
                            $this->output->writeln(sprintf(' -> CF %d', $tmpCf));
                            break;

                        // SAR
                        case 7:
                            $this->debugOp(sprintf('[80186] SAR %s', $to));

                            // $this->output->writeln(sprintf(' -> bsize %d', $this->instr['bsize']));
                            if ($scratch < $this->instr['bsize']) {
                                $this->flags->setByName('CF', $scratch);
                            }
                            $this->flags->setByName('OF', false);

                            $scratch2 = intval($scratch2) * ~(((1 << $this->instr['bsize']) - 1) >> $scratch);

                            $toInt = $to->toInt();

                            $this->op['dst'] = $toInt;
                            $this->op['src'] = $scratch2;

                            $toInt += $this->op['src'];
                            $to->setData($toInt);
                            $this->op['res'] = $toInt;

                            /**
                             * op_dest = $to->toInt();
                             * op_source = $scratch2;
                             * op_result = $to->toInt() += $scratch2;
                             */

                            // $this->output->writeln(sprintf(' -> %s', $to));
                            break;

                        default:
                            throw new NotImplementedException(sprintf('ireg %d', $ireg));
                    }
                    break;

                // JMP | CALL short/near - OpCodes: e8 e9 ea eb
                case 14:
                    $this->ip->add(3 - $this->instr['dir']);
                    if ($this->instr['is_word']) {
                        $this->debugOp(sprintf('JMP'));
                    } else {
                        if ($this->instr['dir']) {
                            // JMP far
                            $this->debugOp(sprintf('JMP far'));

                            $this->ip->setData(0);

                            $data = $this->instr['data_w'][2];

                            $this->output->writeln(sprintf(' -> %s', $this->cs));
                            $this->cs->setData($data);
                            $this->output->writeln(sprintf(' -> %s (%d)', $this->cs, $data));
                        } else {
                            // CALL
                            $this->debugOp(sprintf('CALL'));
                            // $this->output->writeln(sprintf(' -> %s', $this->ip));
                            $this->pushRegisterToStack($this->ip);
                        }
                    }

                    if ($this->instr['dir'] && $this->instr['is_word']) {
                        // $add = $this->instr['data_b'][0] - 0x100; // Signed Char
                        $add = NumberHelper::unsignedIntToChar($this->instr['data_w'][0]);
                    } else {
                        $add = $this->instr['data_w'][0];
                    }

                    $this->debugCsIpRegister();
                    if ($add) {
                        $this->ip->add($add);
                    }
                    $this->debugCsIpRegister($add);
                    break;

                // TEST reg, r/m - OpCodes: 84 85
                case 15:
                    $this->debugOp(sprintf('TEST %s %s', $this->instr['to'], $this->instr['from']));
                    $this->op['src'] = $this->instr['from']->toInt();
                    $this->op['dst'] = $this->instr['to']->toInt();
                    $this->op['res'] = $this->op['dst'] & $this->op['src'];
                    // $this->output->writeln(sprintf(' -> RES %08b', $this->op['res']));
                    break;

                // NOP|XCHG AX, reg - OpCodes: 90 91 92 93 94 95 96 97
                case 16:
                    // For NOP the source and the destination is AX.
                    // Since AX is mandatory for 'XCHG AX, regs16' (not for 'XCHG reg, r/m'),
                    // NOP is the same as XCHG AX, AX.
                    $this->instr['is_word'] = true;
                    $this->instr['size'] = 2;
                    $this->instr['bsize'] = $this->instr['size'] * 8;

                    $this->instr['from'] = $this->getRegisterByNumber($this->instr['is_word'], $this->instr['raw_low3']);
                    $this->instr['to'] = $this->ax;

                    $this->debugOp(sprintf('NOP %s %s', $this->instr['to'], $this->instr['from']));
                // no break

                // NOP|XCHG reg, r/m - OpCodes: 86 87
                case 24:
                    $this->debugOp(sprintf('XCHG to=%s from=%s', $this->instr['to'], $this->instr['from']));

                    if ($this->instr['from'] instanceof Register && $this->instr['to'] instanceof Register) {
                        if ('AX' !== $this->instr['from']->getName()) { // Not NOP
                            // XCHG AX, reg
                            // $this->output->writeln(sprintf(' -> OK REG'));

                            $tmp = $this->instr['from']->getData();
                            $this->instr['from']->setData($this->instr['to']->getData());
                            $this->instr['to']->setData($tmp);
                            // $this->output->writeln(sprintf(' -> OK REG to=%s from=%s', $this->instr['to'], $this->instr['from']));
                        }
                    } elseif ($this->instr['from'] instanceof AbsoluteAddress && $this->instr['to'] instanceof Register) {
                        // XCHG reg, r/m
                        $offset = $this->instr['from']->toInt();
                        $length = $this->instr['to']->getSize();
                        $this->output->writeln(sprintf(' -> OK ADDR o=%x l=%d', $offset, $length));

                        $data = $this->ram->read($offset, $length);
                        $this->ram->write($this->instr['to']->getData(), $offset, $length);
                        $this->instr['to']->setData($data);

                        $this->output->writeln(sprintf(' -> OK REG to=%s from=%s', $this->instr['to'], $this->instr['from']));
                    } else {
                        throw new UnknownTypeException();
                    }
                    break;

                // MOVSx (extra=0)|STOSx (extra=1)|LODSx (extra=2) - OpCodes: a4 a5 aa ab ac ad
                case 17:
                    if ($this->repOverrideEn) {
                        $tmpCount = $this->cx->toInt();
                    } else {
                        $tmpCount = 1;
                    }

                    $ax = $this->getRegisterByNumber($this->instr['is_word'], 0);
                    $add = (2 * $this->flags->getByName('DF') - 1) * ($this->instr['is_word'] + 1); // direction flag

                    $this->debugOp(sprintf('MOVSx|STOSx|LODSx w=%d extra=%b add=%d', $this->instr['is_word'], $this->instr['extra'], $add));

                    for ($tmpInt = $tmpCount; $tmpInt > 0; --$tmpInt) {
                        if (1 == $this->instr['extra']) {
                            // Extra 1: AL/AX
                            $tmpFrom = $ax;
                        } else {
                            // Extra 0, 2: SEG:SI
                            $offset = ($this->segDefaultReg->toInt() << 4) + $this->si->toInt();
                            $tmpFrom = new AbsoluteAddress(self::SIZE_BYTE << 1, $offset);
                        }

                        if ($this->instr['extra'] < 2) {
                            // Extra 0, 1: ES:DI
                            $tmpTo = $this->getEffectiveEsDiAddress();
                        } else {
                            // Extra 2: AL/AX
                            $tmpTo = $ax;
                        }

                        // $this->output->writeln(sprintf(' -> SEG   %s', $this->segDefaultReg));
                        // $this->output->writeln(sprintf(' -> INDEX %d', $tmpInt));
                        // $this->output->writeln(sprintf(' -> FROM %s', $tmpFrom));
                        // $this->output->writeln(sprintf(' -> TO   %s', $tmpTo));

                        if ($tmpFrom instanceof Register && $tmpTo instanceof AbsoluteAddress) {
                            // FROM  Register
                            // TO    Address

                            $data = $tmpFrom->getData();

                            $offset = $tmpTo->toInt();
                            $this->ram->write($data, $offset, $tmpFrom->getSize());
                        } elseif ($tmpFrom instanceof AbsoluteAddress && $tmpTo instanceof Register) {
                            // FROM  Address
                            // TO    Register

                            $offset = $tmpFrom->toInt();
                            $data = $this->ram->read($offset, $tmpTo->getSize());

                            $tmpTo->setData($data, true);
                        } elseif ($tmpFrom instanceof AbsoluteAddress && $tmpTo instanceof AbsoluteAddress) {
                            // FROM  Address
                            // TO    Address

                            $offset = $tmpFrom->toInt();
                            $data = $this->ram->read($offset, $this->instr['size']);

                            $offset = $tmpTo->toInt();
                            $this->ram->write($data, $offset, $this->instr['size']);
                        } else {
                            throw new UnknownTypeException();
                        }

                        if (1 !== $this->instr['extra']) {
                            $this->si->add(-$add);
                            // $this->output->writeln(sprintf(' -> SI   %s', $this->si));
                        }
                        if (2 !== $this->instr['extra']) {
                            $this->di->add(-$add);
                            // $this->output->writeln(sprintf(' -> DI   %s', $this->di));
                        }
                        // $this->output->writeln('');
                    }

                    // Reset CX on repeat mode.
                    if ($this->repOverrideEn) {
                        $this->cx->setData(0);
                    }
                    break;

                // CMPSx (extra=0)|SCASx (extra=1)
                case 18:
                    $this->debugOp(sprintf('CMPSx|SCASx e=%d', $this->instr['extra']));

                    if ($this->repOverrideMode) {
                        $scratch = $this->cx->toInt();
                        if (0 === $scratch) {
                            break;
                        }
                    } else {
                        $scratch = 1;
                    }

                    if ($this->segOverrideEn) {
                        $scratch2 = $this->segOverrideReg;
                    } else {
                        $scratch2 = 11; // DS Register
                    }

                    // $this->output->writeln(sprintf(' -> scratch2 %d', $scratch2));
                    // $this->output->writeln(sprintf(' -> scratch %d', $scratch));
                    // $this->output->writeln(sprintf(' -> segovr %s', $this->segDefaultReg));

                    if ($scratch) {
                        $subloopCount = 0;
                        for (; $scratch; $this->repOverrideEn || --$scratch) {
                            ++$subloopCount;

                            $add = ($this->flags->getByName('DF') << 1) - 1;
                            if ($this->instr['is_word']) {
                                $add <<= 1;
                            }

                            // TO Address
                            if ($this->instr['extra']) {
                                $tmpTo = 0;//@todo
                                $siAdd = 0;
                            } else {
                                $tmpTo = ($this->segDefaultReg->toInt() << 4) + $this->si->toInt();
                                $siAdd = $add;
                            }

                            // FROM Address
                            $tmpFrom = $this->getEffectiveEsDiAddress()->toInt();

                            // FROM Read
                            $fromData = DataHelper::arrayToInt($this->ram->read($tmpFrom, 1));

                            // TO Read
                            $toData = DataHelper::arrayToInt($this->ram->read($tmpTo, 1));

                            // Write
                            if ($fromData !== $toData) {
                                $this->ram->write($fromData, $this->op['dst'], 1);
                            }

                            // OP
                            $this->op['src'] = $fromData;
                            $this->op['dst'] = $toData;
                            $this->op['res'] = $this->op['dst'] - $this->op['src'];
                            $notRes = !boolval($this->op['res']);

                            $this->output->writeln(sprintf(' -> subrun %d (%d)', $scratch, $subloopCount));
                            $this->output->writeln(sprintf(' -> FROM %x (%x)', $tmpFrom, $fromData));
                            $this->output->writeln(sprintf(' ->   TO %x (%x)', $tmpTo, $toData));

                            $this->si->sub($siAdd);
                            $this->di->sub($add);
                            $this->output->writeln(sprintf(' -> %s', $this->si));
                            $this->output->writeln(sprintf(' -> %s', $this->di));

                            $cx = $this->cx->sub(1);
                            $this->output->writeln(sprintf(' -> %s', $this->cx));

                            if ($this->repOverrideEn && !($cx && ($notRes == $this->repOverrideMode))) {
                                $this->output->writeln(sprintf(' -> res %d', $this->op['res']));
                                $this->output->writeln(sprintf(' -> rep %d', $this->repOverrideMode));
                                break;
                            }

                            $this->output->writeln(''); // Debug
                        }

                        // Flags Type
                        $this->instr['set_flags_type'] = self::FLAGS_UPDATE_SZP | self::FLAGS_UPDATE_AO_ARITH;

                        // CF
                        $tmpCf = $this->op['res'] > $this->op['dst'];
                        $this->flags->setByName('CF', $tmpCf);
                        $this->output->writeln(sprintf(' -> CF %d', $tmpCf));
                    }
                    break;

                // RET|RETF|IRET - OpCodes: c2 c3 ca cb cf
                case 19:
                    $this->debugOp(sprintf('RET %b %s', $this->instr['extra'], $this->ip));

                    // Restore IP register.
                    $data = $this->popFromStack($this->ip->getSize());
                    $this->ip->setData($data);
                    $this->output->writeln(sprintf(' -> POP %s', $this->ip));

                    if ($this->instr['extra']) { // IRET|RETF|RETF imm16
                        // Restore CS register.
                        $data = $this->popFromStack($this->cs->getSize());
                        $this->cs->setData($data);
                        $this->output->writeln(sprintf(' -> POP %s', $this->cs));
                    }

                    if ($this->instr['extra'] & 2) { // IRET
                        // Restore Flags.
                        $data = $this->popFromStack($this->flags->getSize());
                        $this->flags->setData($data);
                        $this->output->writeln(sprintf(' -> POP %s', $this->flags));
                    } elseif (!$this->instr['is_word']) { // RET|RETF imm16
                        $this->sp->setData($this->instr['data_w'][0]);
                    }

                    // $this->debugCsIpRegister();
                    // $this->debugSsSpRegister();
                    break;

                // MOV r/m, immed - OpCodes: c6 c7
                case 20:
                    if ($this->instr['is_word']) {
                        $data = $this->instr['data_w'][2];
                    } else {
                        $data = $this->instr['data_b'][2];
                    }

                    // $this->instr['dir'] is always true (1100011x) so take FROM here.
                    $this->debugOp(sprintf('MOV %s %x', $this->instr['from'], $data));

                    $offset = $this->instr['from']->toInt();
                    $this->ram->write($data, $offset, $this->instr['size']);
                    break;

                // IN AL/AX, DX/imm8
                case 21:
                    $this->debugOp(sprintf('IN AL/AX, DX/imm8'));

                    // PIC EOI
                    $this->io[0x20] = 0;

                    // PIT channel 0/2 read placeholder
                    if (!array_key_exists(0x40, $this->io)) {
                        $this->io[0x40] = 0;
                    }
                    --$this->io[0x40];
                    $this->io[0x42] = $this->io[0x40];

                    // CGA refresh
                    if (array_key_exists(0x3DA, $this->io)) {
                        $this->io[0x3DA] = intval($this->io[0x3DA]) ^ 9;
                    }

                    if ($this->instr['extra']) {
                        $data = $this->dx->toInt();
                    } else {
                        $data = $this->instr['data_b'][0];
                    }

                    $this->output->writeln(sprintf(' -> data %x', $data));

                    // Scancode read flag.
                    if (0x60 === $data) {
                        $this->io[0x64] = 0;
                    }

                    // CRT cursor position
                    if (0x3D5 === $data && (7 === intval($this->io[0x3D4]) >> 1)) {
                        if ($this->io[0x3D4] & 1) {
                            $bitsShift = 0;
                            $bitsAnd = 0xFF;
                        } else {
                            $bitsShift = 8;
                            $bitsAnd = 0xFF00;
                        }

                        $ram49de = $this->ram->read(0x49E, 2);
                        $ram49d = $ram49de[0];
                        $ram49e = $ram49de[1];
                        $ram4ad = $this->ram->read(0x4ad, 1);
                        $this->io[0x3D5] = (($ram49e * 80 + $ram49d + $ram4ad) & $bitsAnd) >> $bitsShift;
                    }

                    $al = $this->ax->getChildRegister();
                    $this->output->writeln(sprintf(' -> %s', $al));

                    if (array_key_exists($data, $this->io)) {
                        $this->op['src'] = intval($this->io[$data]);
                        $this->op['dst'] = $al->toInt();
                        $this->op['res'] = $this->op['src'];

                        $al->setData($this->op['res']);
                    }

                    $this->output->writeln(sprintf(' -> src %x', $this->op['src']));
                    $this->output->writeln(sprintf(' -> dst %x', $this->op['dst']));
                    $this->output->writeln(sprintf(' -> res %x', $this->op['res']));
                    $this->output->writeln(sprintf(' -> %s', $al));
                    break;

                // OUT DX/imm8, AL/AX - OpCodes: e6 e7 ee ef
                case 22:
                    // @link https://pdos.csail.mit.edu/6.828/2010/readings/i386/OUT.htm

                    // AL/AX
                    $ax = $this->getRegisterByNumber($this->instr['is_word'], 0);

                    if ($this->instr['extra']) {
                        $scratch = $this->dx->toInt();
                    } else {
                        $scratch = $this->instr['data_b'][0];
                    }

                    $this->debugOp(sprintf(
                        'OUT word=%s extra=%d AL/AH=%s DX=%s v=%x',
                        $this->instr['is_word'] ? 'Y' : 'N',
                        $this->instr['extra'],
                        $ax,
                        $this->dx,
                        $scratch
                    ));

                    // @TODO create class to handle shit below
                    // handle Speaker control here
                    // handle PIT rate programming here
                    // handle Graphics here? Hm?
                    // handle Speaker here
                    // handle CRT video RAM start offset
                    // handle CRT cursor position
                    break;

                // REPxx - OpCodes: f2 f3
                case 23:
                    $this->repOverrideEn = 2;
                    $this->repOverrideMode = $this->instr['is_word'];
                    if ($this->segOverrideEn) {
                        ++$this->segOverrideEn;
                    }
                    $this->debugOp(sprintf('REP %d', $this->repOverrideMode));
                    break;

                // PUSH sreg - OpCodes: 06 0e 16 1e
                case 25:
                    $this->instr['reg'] = ($this->instr['raw'] >> 3) & 3; // xxx11xxx
                    $register = $this->getSegmentRegisterByNumber($this->instr['reg']);

                    $this->debugOp(sprintf('PUSH %s', $register));

                    $this->pushRegisterToStack($register);
                    break;

                // POP sreg - OpCodes: 07 17 1f
                case 26:
                    $this->instr['reg'] = ($this->instr['raw'] >> 3) & 3; // xxx11xxx
                    if (($this->instr['reg'] + 8) !== $this->instr['extra']) {
                        throw new \RuntimeException(sprintf('In 8086tiny extra is used. %d != %d', $this->instr['reg'], $this->instr['extra']));
                    }
                    $register = $this->getSegmentRegisterByNumber($this->instr['reg']);
                    //$register = $this->getRegisterByNumber($this->instr['is_word'], $this->$this->instr['extra']);

                    $stackData = $this->popFromStack($register->getSize());
                    $register->setData($stackData);

                    // $this->debugOp(sprintf('POP %s reg=%d e=%d', $register, $this->instr['reg'], $this->instr['extra']));
                    // $this->debugSsSpRegister();

                    break;

                // xS: segment overrides - OpCodes: 26 2e 36 3e
                case 27:
                    $this->segOverrideEn = 2;
                    $this->segOverrideReg = $this->instr['extra'];
                    if ($this->repOverrideEn) {
                        ++$this->repOverrideEn;
                    }
                    $this->instr['reg'] = ($this->instr['raw'] >> 3) & 3; // Segment Override Prefix = 001xx110, xx = Register

                    // Debug
                    $tmpReg = $this->getRegisterByNumber(true, $this->segOverrideReg);

                    $this->debugOp(sprintf('SEG override: %s', $tmpReg));
                    break;

                // AAA/AAS
                case 29:
                    $this->debugOp(sprintf('AAA/AAS'));

                    // CF
                    $al = $this->ax->getChildRegister();
                    $this->output->writeln(sprintf(' -> %s', $this->ax));
                    $tmpCf = ($al->toInt() & 0xF) > 9 || $this->flags->getByName('AF');
                    $this->output->writeln(sprintf(' -> CF %d', $tmpCf));

                    $this->flags->setByName('CF', $tmpCf);
                    $this->flags->setByName('AF', $tmpCf);

                    // AX
                    $op = $this->instr['extra'] - 1;
                    $this->output->writeln(sprintf(' -> %s (%d)', $this->ax, $op));
                    $add = 262 * $op * $tmpCf;
                    $this->ax->setData($this->ax->toInt() + $add);
                    $this->output->writeln(sprintf(' -> %s (%d)', $this->ax, $add));

                    $this->output->writeln(sprintf(' -> %s', $this->ax));
                    $al = $this->ax->getChildRegister();
                    $this->output->writeln(sprintf(' -> %s', $this->ax));
                    $tmpAl = $al->toInt() & 0xF;
                    $al->setData($tmpAl);
                    $this->output->writeln(sprintf(' -> %s', $this->ax));

                    // Res
                    $this->op['res'] = $tmpAl;
                    $this->output->writeln(sprintf(' -> res %d', $this->op['res']));
                    break;

                // PUSHF - OpCodes: 9c
                case 33:
                    $this->debugOp(sprintf('PUSHF %s', $this->flags));
                    $data = $this->flags->getData();
                    $size = $this->flags->getSize();
                    $this->pushDataToStack($data, $size);
                    break;

                // POPF - OpCodes: 9d
                case 34:
                    $data = $this->popFromStack(self::SIZE_BYTE);
                    $tmpInt = DataHelper::arrayToInt($data);
                    $this->flags->setIntData($tmpInt);

                    $this->debugOp(sprintf('POPF %s', $this->flags));
                    break;

                // INT imm - OpCodes: cd
                case 39:
                    // Decode like INT
                    $this->instr['raw'] = 0xCD;
                    $this->instr['xlat'] = $this->biosDataTables[self::TABLE_XLAT_OPCODE][$this->instr['raw']];
                    //$this->$this->instr['extra'] = $this->biosDataTables[self::TABLE_XLAT_SUBFUNCTION][$this->instr['raw']];
                    $this->instr['has_modregrm'] = $this->biosDataTables[self::TABLE_I_MOD_SIZE][$this->instr['raw']];

                    $this->debugOp(sprintf('INT %x', $this->instr['data_b'][0]));
                    $this->ip->add(2);
                    // $this->output->writeln(sprintf(' -> %s', $this->ip));
                    $this->interrupt($this->instr['data_b'][0]);
                    break;

                // AAM
                case 41:
                    $this->debugOp(sprintf('AAM %x', $this->instr['data_w'][0]));

                    $data = $this->instr['data_w'][0];
                    $data &= 0xFF;
                    $this->output->writeln(sprintf(' -> data: %x (%x)', $data, $this->instr['data_w'][0]));

                    $data = $this->instr['data_b'][0];
                    $this->output->writeln(sprintf(' -> data: %x', $data));

                    if ($data) {
                        $al = $this->ax->getChildRegister();
                        $ah = $this->ax->getChildRegister(true);

                        $this->output->writeln(sprintf(' -> %s', $this->ax));
                        $this->output->writeln(sprintf(' -> %s', $ah));
                        $this->output->writeln(sprintf(' -> %s', $al));

                        // AH
                        $tmpAh = intval($al->toInt() / $data);
                        $ah->setData($tmpAh);

                        // Res
                        $this->op['res'] = $al->toInt() % $data;
                    } else {
                        $this->interrupt(0);
                    }
                    break;

                // AAD
                case 42:
                    $this->debugOp(sprintf('AAD'));
                    $this->instr['is_word'] = false;

                    $data = $this->instr['data_w'][0];
                    $this->output->writeln(sprintf(' -> data %x', $data));

                    $al = $this->ax->getChildRegister();
                    $ah = $this->ax->getChildRegister(true);

                    $tmpAx = 0xFF & ($al->toint() + $data * $ah->toInt());
                    $this->ax->setData($tmpAx);
                    $this->op['res'] = $tmpAx;

                    $this->output->writeln(sprintf(' -> %s', $this->ax));
                    break;

                // XLAT - OpCodes: d7
                case 44:
                    $offset = ($this->segDefaultReg->toInt() << 4) + $this->bx->toInt() + $this->ax->getLowInt();

                    $data = $this->ram->read($offset, 1); // Read only one byte.

                    $this->debugOp(sprintf('XLAT seg=%s ax=%s offset=%x', $this->segDefaultReg, $this->ax, $offset));
                    //$this->output->writeln(sprintf(' -> seg1: %x', $defaultSeg->toInt()));
                    //$this->output->writeln(sprintf(' -> seg2: %x', $defaultSeg->toInt() << 4));
                    //$this->output->writeln(sprintf(' -> AL: %x', $this->ax->getLowInt()));
                    //$this->output->writeln(sprintf(' -> AH: %x', $this->ax->getHighInt()));

                    $this->ax->setLowInt(intval($data[0]));

                    // $this->output->writeln(sprintf(' -> AL: %x', $this->ax->getLowInt()));
                    //$this->output->writeln(sprintf(' -> AH: %x', $this->ax->getHighInt()));
                    break;

                // CLC|STC|CLI|STI|CLD|STD - OpCodes: f8 f9 fa fb fc fd
                case 46:
                    $data = $this->instr['extra'] & 1; // @todo rename to $data
                    $flagId = ($this->instr['extra'] >> 1) & 7; // xxxx111x
                    $realFlagId = $this->biosDataTables[self::TABLE_FLAGS_BITFIELDS][$flagId];
                    $flagName = $this->flags->getName($realFlagId);

                    $this->debugOp(sprintf('CLx|STx %02x (=%d [%08b]) ID=%d/%d v=%d F=%s', $this->instr['extra'], $this->instr['extra'], $this->instr['extra'], $flagId, $realFlagId, $data, $flagName));

                    $this->flags->set($realFlagId, $data);
                    break;

                // TEST AL/AX, imm - OpCodes: a8 a9
                case 47:
                    // AL/AX
                    $register = $this->getRegisterByNumber($this->instr['is_word'], 0);
                    $data = $this->instr['is_word'] ? $this->instr['data_w'][0] : $this->instr['data_b'][0];

                    $this->debugOp(sprintf('TEST %s %04x', $register, $data));

                    $this->op['src'] = $data;
                    $this->op['dst'] = $register->toInt();
                    $this->op['res'] = $this->op['dst'] & $this->op['src'];
                    // $this->output->writeln(sprintf(' -> RES %08b', $this->op['res']));
                    break;

                // Emulator-specific 0F xx opcodes
                case 48:
                    $subOpCode = NumberHelper::unsignedIntToChar($this->instr['data_b'][0]);
                    switch ($subOpCode) {
                        // PUTCHAR_AL
                        case 0:
                            $al = $this->ax->getChildRegister();
                            $char = chr($al->toInt());
                            $this->debugOp(sprintf('PUTCHAR_AL %s %s', $al, $char));
                            $this->tty->putChar($char);
                            break;

                        // Get RTC
                        case 1:
                            $this->debugOp(sprintf('GET RTC'));
                            $now = Carbon::now();

                            $dayOfYear = new Memory(4, $now->dayOfYear);

                            // struct tm
                            $tm = [
                                [$now->second],
                                [$now->minute],
                                [$now->hour],
                                [$now->day],
                                [$now->month],
                                [$now->year - 1900],
                                [$now->dayOfWeek],
                                $dayOfYear->getData()->toArray(),
                                [$now->dst],
                                [],
                                /* @TODO offset from UTC in seconds */
                                // 0, /* @TODO timezone abbreviation */
                            ];

                            $tmFilled = array_map(function (array $item) {
                                $c = count($item);
                                $d = 4 - $c; // 4 because 'timetable' in Bios is actually 32-bit. IDK why.
                                $item = array_merge($item, array_fill(0, $d, 0));
                                return $item;
                            }, $tm);

                            $tmFlatten = call_user_func_array('array_merge', $tmFilled);

                            $ea = $this->getEffectiveEsBxAddress();
                            $offset = $ea->toInt();
                            $size = count($tmFlatten);

                            $this->ram->write($tmFlatten, $offset, $size);
                            break;

                        // DISK_READ
                        case 2:
                            // Disk ID is stored in DL.
                            $dl = $this->dx->getChildRegister();
                            $disk = $this->machine->getDiskByNum($dl->toInt());

                            // File Descriptor
                            $fd = $disk->getFd();

                            $this->debugOp(sprintf('DISK_READ %s', $disk));

                            // From
                            $tmpFrom = $this->bp->toInt() << 9;
                            $this->output->writeln(sprintf(' -> DISK FROM %x', $tmpFrom));

                            // Seek
                            $seek = fseek($fd, $tmpFrom, SEEK_SET);

                            if ($seek) {
                                break;
                            }

                            // Length to read is stored in AX.
                            $length = $this->ax->toInt();

                            // Read
                            $data = fread($fd, $length);
                            $dataLen = strlen($data);

                            // To
                            $tmpTo = $this->getEffectiveEsBxAddress()->toInt();
                            $this->output->writeln(sprintf(' -> RAM TO %x', $tmpTo));

                            // Write
                            $this->ram->write($data, $tmpTo, $length);

                            // Reached EOF?
                            $al = $this->ax->getChildRegister();
                            $al->setData(intval($dataLen > 0));
                            $this->output->writeln(sprintf(' -> AL %s', $al));

                            // Debug
                            $this->output->writeln(sprintf(' -> AX %s', $this->ax));
                            break;

                        // DISK_WRITE
                        case 3:
                            // @todo
                            $this->debugOp(sprintf('DISK_WRITE'));

                            throw new NotImplementedException('DISK_WRITE');
                            break;

                        // STOP
                        case 4:
                            $this->debugOp('STOP');
                            $this->debugAll();
                            break 3;

                        default:
                            throw new NotImplementedException(sprintf('Emulator-specific 0F xx opcodes: %d', $subOpCode));
                    }
                    //throw new NotImplementedException('Emulator-specific 0F xx opcodes');
                    break;

                case 49: // OpCodes: c8
                case 50: // OpCodes: c9
                case 51: // OpCodes: 60
                    throw new NotImplementedException(sprintf(' -> opcode: %x', $this->instr['raw']));
                    break;

                // ? UNKNOWN - OpCodes: 62 63 64 65 66 67 6c 6d 6e 6f
                case 52:
                    throw new NotImplementedException(sprintf(' -> opcode: %x', $this->instr['raw']));
                    break;

                // HLT - OpCodes: f4 (and d8 d9 da db dc dd de df f0)
                case 53:
                    if (0xf4 === $this->instr['raw']) {
                        $this->debugOp('HLT');
                    } else {
                        $this->debugInstrData();
                        throw new NotImplementedException(sprintf(' -> opcode: %x', $this->instr['raw']));
                    }
                    break;

                // WAIT - OpCodes: 9b
                case 54:
                    $this->debugOp('WAIT');
                    $this->debugInstrData();
                    break;

                default:
                    throw new NotImplementedException(sprintf(
                        'OP 0x%02x (=%d [%08b]) xLatID 0x%02x (=%d [%08b])',
                        $this->instr['raw'],
                        $this->instr['raw'],
                        $this->instr['raw'],
                        $this->instr['xlat'],
                        $this->instr['xlat'],
                        $this->instr['xlat']
                    ));
            } // switch $this->instr['xlat']

            // $this->output->writeln(sprintf(' -> %s', $this->flags));

            // Debug
            // $tmpData = $this->ram->read(0x7c00, 1);
            // if (null !== $tmpData[0]) {
            //     $this->output->writeln(sprintf(' -> 0x7c00 OK'));
            // }
            // $tmpData = $this->ram->read(0x8100, 1);
            // if (null !== $tmpData[0]) {
            //     $this->output->writeln(sprintf(' -> 0x8100 OK'));
            // }

            // Increment instruction pointer by computed instruction length.
            // Tables in the BIOS binary help us here.
            $baseInstrSize = $this->biosDataTables[self::TABLE_BASE_INST_SIZE][$this->instr['raw']];
            if ($this->biosDataTables[self::TABLE_I_W_SIZE][$this->instr['raw']]) {
                $iwAdder = $this->instr['size'];
            } else {
                $iwAdder = 0;
            }

            $add =
                (
                    $this->instr['mode'] * (3 !== $this->instr['mode'])
                    + 2 * (!$this->instr['mode'] && 6 === $this->instr['rm_i'])
                ) * $this->instr['has_modregrm']
                + $baseInstrSize
                + $iwAdder;
            if ($add) {
                // $this->debugCsIpRegister();
                $this->ip->add($add);
                // $this->debugCsIpRegister();
            }

            // If instruction needs to update SF, ZF and PF, set them as appropriate.
            // $this->output->writeln(sprintf(' -> Set Flags Type: %d', $this->instr['set_flags_type']));
            if ($this->instr['set_flags_type'] & self::FLAGS_UPDATE_SZP) {
                if (null === $this->op['res']) {
                    throw new NotImplementedException('op result has not been set, but maybe it needs to be.'); // @todo remove
                }

                // unsigned int. For example, int -42 = unsigned char 214
                // Since we deal with Integer values < 256 we only need a 0xFF-mask.
                $ucOpResult = $this->op['res'] & 0xFF;

                if ($ucOpResult < 0 || $ucOpResult > 255) {
                    throw new ValueExceededException(sprintf('ucOpResult is %d (%x, res=%d/%x). Must be >=0 and < 256.', $ucOpResult, $ucOpResult, $this->op['res'], $this->op['res'])); // @todo remove
                }

                // Sign Flag
                $tmpSign = $this->op['res'] < 0;
                $this->flags->setByName('SF', $tmpSign);
                // $this->output->writeln(sprintf(' -> SF %d', $this->flags->getByName('SF')));

                // Zero Flag
                $tmpZero = $this->op['res'] == 0;
                $this->flags->setByName('ZF', $tmpZero);
                // $this->output->writeln(sprintf(' -> ZF %d', $this->flags->getByName('ZF')));

                // Parity Flag
                $tmpParity = $this->biosDataTables[self::TABLE_PARITY_FLAG][$ucOpResult];
                $this->flags->setByName('PF', $tmpParity);
                $this->output->writeln(sprintf(' -> PF %d', $this->flags->getByName('PF')));

                if ($this->instr['set_flags_type'] & self::FLAGS_UPDATE_AO_ARITH) {
                    $this->setAuxiliaryFlagArith($this->op['src'], $this->op['dst'], $this->op['res']);
                    $this->setOverflowFlagArith1($this->op['src'], $this->op['dst'], $this->op['res'], $this->instr['is_word']);

                    // $this->output->writeln(sprintf(' -> AF %d', $this->flags->getByName('AF')));
                    // $this->output->writeln(sprintf(' -> OF %d', $this->flags->getByName('OF')));
                }
                if ($this->instr['set_flags_type'] & self::FLAGS_UPDATE_OC_LOGIC) {
                    $this->flags->setByName('CF', false);
                    $this->flags->setByName('OF', false);
                }
            }

            // Debug
            // fwrite($fh, sprintf("OP %d: %d\n", $this->runLoop, $this->instr['xlat']));
            // fwrite($fh, sprintf("%s\n", $this->flags));

            // Update Instruction counter.
            ++$this->runLoop;

            if (0 === $this->runLoop % self::GRAPHICS_UPDATE_DELAY) {
                $this->updateGraphics();
            }

            if ($this->trapFlag) {
                $this->interrupt(1);
            }
            $this->trapFlag = $this->flags->getByName('TF');

            // @todo also set $this->int8 to true on keyboard read
            if (0 === $this->runLoop % self::KEYBOARD_TIMER_UPDATE_DELAY) {
                $this->int8 = true;
            }

            // If a timer tick is pending, interrupts are enabled, and no overrides/REP are active,
            // then process the tick and check for new keystrokes.
            $flagI = $this->flags->get(Flags::FLAG_I);
            if ($this->int8 && !$this->segOverrideEn && !$this->repOverrideEn && $flagI && !$this->trapFlag) {
                $this->interrupt(0xA);
                $this->int8 = false;

                // KEYBOARD_DRIVER read(0, mem + 0x4A6, 1) && (int8_asap = (mem[0x4A6] == 0x1B), pc_interrupt(7))
                $char = $this->tty->getChar();

                if ($char) {
                    $this->ram->write($char, 0x4A6, 1);
                    $this->int8 = 0x1B === $char;
                    $this->interrupt(7);
                }
            }
        } // while $this->instr['raw']

        $this->output->writeln(sprintf('Run loop end: %d', $this->instr['raw']));
    } // run()

    private function initInstruction()
    {
        // Decode
        $this->instr['xlat'] = $this->biosDataTables[self::TABLE_XLAT_OPCODE][$this->instr['raw']];
        $this->instr['extra'] = $this->biosDataTables[self::TABLE_XLAT_SUBFUNCTION][$this->instr['raw']];
        $this->instr['has_modregrm'] = $this->biosDataTables[self::TABLE_I_MOD_SIZE][$this->instr['raw']];
        $this->instr['set_flags_type'] = $this->biosDataTables[self::TABLE_STD_FLAGS][$this->instr['raw']];

        // 0-7 number of the 8-bit Registers.
        $this->instr['raw_low3'] = $this->instr['raw'] & 7; // xxxx111

        // Is Word Instruction, means 2 Byte long.
        $this->instr['is_word'] = boolval($this->instr['raw'] & 1); // xxxxxx1
        $this->initInstrSize();

        // Instruction Direction
        $this->instr['dir'] = boolval($this->instr['raw'] & 2); // xxxxx1x

        $this->instr['mode'] = 0; // Mode
        $this->instr['rm_i'] = 0; // Register/Memory
        $this->instr['reg'] = 0; // Register
    }

    private function initInstrSize()
    {
        if ($this->instr['is_word']) {
            $this->instr['size'] = 2;
            $this->instr['bsize'] = 16;
        } else {
            $this->instr['size'] = 1;
            $this->instr['bsize'] = 8;
        }
    }

    /**
     * @return void
     */
    private function decodeRegisterMemory(): void
    {
        $biosDataTableBaseIndex = 0;
        switch ($this->instr['mode']) {
            case 0:
                $biosDataTableBaseIndex += 4;
            // no break

            case 1:
            case 2:
                if ($this->segOverrideEn) {
                    $defaultSegId = $this->segOverrideReg;
                } else {
                    /**
                     * Table 3/7: R/M "default segment" lookup
                     *
                     * @var int $defaultSegId
                     */
                    $defaultSegId = $this->biosDataTables[$biosDataTableBaseIndex + 3][$this->instr['rm_i']];
                }

                // Table 0/4: R/M "register 1" lookup
                $register1Id = $this->biosDataTables[$biosDataTableBaseIndex][$this->instr['rm_i']];

                // Table 1/5: R/M "register 2" lookup
                $register2Id = $this->biosDataTables[$biosDataTableBaseIndex + 1][$this->instr['rm_i']];

                // Convert Register IDs to objects.
                $defaultSegReg = $this->getRegisterByNumber(true, $defaultSegId);
                $register1 = $this->getRegisterByNumber(true, $register1Id);
                $register2 = $this->getRegisterByNumber(true, $register2Id);

                // Table 2/6: R/M "DISP multiplier" lookup
                $dispMultiplier = $this->biosDataTables[$biosDataTableBaseIndex + 2][$this->instr['rm_i']];

                $addr1 =
                    $register1->toInt()
                    + $register2->toInt()
                    + $this->instr['data_w'][1] * $dispMultiplier;

                $addr2 =
                    ($defaultSegReg->toInt() << 4)
                    + (0xFFFF & $addr1); // cast to "unsigned short".

                $tmpRm = new AbsoluteAddress(self::SIZE_BYTE << 1, $addr2);
                // $tmpTo = $tmpRm;
                break;

            case 3:
                // if mod = 11 then r/m is treated as a REG field
                $tmpRm = $this->getRegisterByNumber($this->instr['is_word'], $this->instr['rm_i']);

                // $regIndex = $this->getRegIndex($this->instr['is_word'], $this->instr['rm_i']);
                // $tmpRm = $this->getRegisterByNumber($this->instr['is_word'], $regIndex);
                // $tmpTo = $tmpRm;
                break;

            default:
                throw new NotImplementedException(sprintf('Unhandled mode: %d', $this->instr['mode']));
        } // switch $this->instr['mode']

        if (!isset($tmpRm)) {
            throw new \RuntimeException(sprintf('rm variable has not been set yet. mod=%d', $this->instr['mode']));
        }

        // $tmpFromIndex = $this->getRegIndex($this->instr['is_word'], $this->instr['reg']);
        // $tmpFrom = $this->getRegisterByNumber($this->instr['is_word'], $tmpFromIndex);

        $this->instr['rm_o'] = $tmpRm;

        // Convert Number to Object.
        $this->instr['from'] = $this->instr['to'] = $this->getRegisterByNumber($this->instr['is_word'], $this->instr['reg']);

        // TO FROM correct direction.
        if ($this->instr['dir']) {
            $this->instr['from'] = $tmpRm;
        } else {
            $this->instr['to'] = $tmpRm;
        }

        // if ($this->instr['dir']) {
        //     $tmp = $tmpFrom;
        //     $tmpFrom = $tmpRm;
        //     $tmpTo = $tmp;
        // }
        // $this->instr['from'] = $tmpFrom;
        // $this->instr['to'] = $tmpTo;
    }

    private function interrupt(int $code)
    {
        $this->output->writeln(sprintf(' -> Interrupt %02x', $code));

        // Push Flags.
        $tmpFlags = DataHelper::arrayToInt($this->flags->getStandardizedData());
        $this->output->writeln(sprintf(' -> PUSH %s (%x)', $this->flags, $tmpFlags));
        // $this->pushDataToStack($this->flags->getData(), $this->flags->getSize());
        $this->pushDataToStack($this->flags->getStandardizedData(), $this->flags->getSize());

        // Push Registers.
        // $this->output->writeln(sprintf(' -> PUSH %s', $this->cs));
        $this->pushRegisterToStack($this->cs);
        // $this->output->writeln(sprintf(' -> PUSH %s', $this->ip));
        $this->pushRegisterToStack($this->ip);

        $this->output->writeln(sprintf(' -> %s', $this->ss));
        $this->output->writeln(sprintf(' -> %s', $this->sp));

        // Write CS Register.
        $offset = ($code << 2) + 2;
        // $this->output->writeln(sprintf(' -> CS Offset %d/%x', $offset, $offset));
        // $this->ram->write($this->cs->getData(), $offset, $this->cs->getSize());
        $data = $this->ram->read($offset, $this->cs->getSize());
        $this->cs->setData($data);
        $this->output->writeln(sprintf(' -> %s', $this->cs));

        // Set IP Register.
        $offset = $code << 2;
        $data = $this->ram->read($offset, $this->ip->getSize());
        // $this->output->writeln(sprintf(' -> %s Offset %d/%x', $this->ip, $offset, $offset));
        $this->ip->setData($data);
        $this->output->writeln(sprintf(' -> %s', $this->ip));

        // Set Flags.
        $this->flags->setByName('TF', false);
        $this->flags->setByName('IF', false);
    }

    private function updateGraphics()
    {
        // @todo use separate framebuffer, or tty, or whatever.
        $this->output->writeln('Update Graphics');
        // throw new NotImplementedException('Update Graphics');

        // Run TTY.
        /** @var TtyOutputDevice $tty */
        $tty = $this->machine->getTty();
        $tty->run();
    }

    /**
     * @param bool $isWord
     * @param int $registerId Number of the Register.
     * @param int $fnLoop Limit the recursion.
     * @return Register
     */
    private function getRegisterByNumber(bool $isWord, int $registerId, int $fnLoop = 0): Register
    {
        if ($isWord) {
            return $this->registers[$registerId];
            // $reg= $this->registers[$registerId];

            // $registerId2=$registerId<<1;
            // $reg2= $this->registers[$registerId2];
            // return $reg2;
        }
        if ($fnLoop >= 2) {
            throw new \RuntimeException('Unhandled recursive call detected.');
        }

        $effectiveRegId = $registerId & 3; // x11
        $register = $this->getRegisterByNumber(true, $effectiveRegId, 1 + $fnLoop);

        $isHigh = $registerId & 4; // 1xx
        return $register->getChildRegister($isHigh);
    }

    private function getRegIndex(bool $isWord, int $registerId): int
    {
        if ($this->instr['is_word']) {
            return $registerId < 1;
        }
        // $x= ($registerId < 1) + ($registerId >> 2) & 7;
        $x = (($registerId << 1) + ($registerId >> 2)) & 7;
        return $x;
    }

    private function getSegmentRegisterByNumber(int $regId): Register
    {
        return $this->segmentRegisters[$regId];
    }

    /**
     * EA of SS:SP
     */
    private function getEffectiveStackPointerAddress(): AbsoluteAddress
    {
        $offset = ($this->ss->toInt() << 4) + $this->sp->toInt();
        $address = new AbsoluteAddress(self::SIZE_BYTE << 1, $offset);
        return $address;
    }

    /**
     * EA of CS:IP
     */
    private function getEffectiveInstructionPointerAddress(): AbsoluteAddress
    {
        $offset = ($this->cs->toInt() << 4) + $this->ip->toInt();
        $address = new AbsoluteAddress(self::SIZE_BYTE << 1, $offset);
        return $address;
    }

    /**
     * EA of ES:DI
     */
    private function getEffectiveEsDiAddress(): AbsoluteAddress
    {
        $offset = ($this->es->toInt() << 4) + $this->di->toInt();
        $address = new AbsoluteAddress(self::SIZE_BYTE << 1, $offset);
        return $address;
    }

    /**
     * EA of ES:BX
     */
    private function getEffectiveEsBxAddress(): AbsoluteAddress
    {
        $offset = ($this->es->toInt() << 4) + $this->bx->toInt();
        $address = new AbsoluteAddress(self::SIZE_BYTE << 1, $offset);
        return $address;
    }

    /**
     * EA of DS:BX
     */
    private function getEffectiveDsBxAddress(): AbsoluteAddress
    {
        $offset = ($this->ds->toInt() << 4) + $this->bx->toInt();
        $address = new AbsoluteAddress(self::SIZE_BYTE << 1, $offset);
        return $address;
    }

    /**
     * r/m EA
     *
     * @param int $disp
     * @return AbsoluteAddress
     */
    private function getEffectiveRegisterMemoryAddress(int $disp): AbsoluteAddress
    {
        switch ($this->instr['rm_o']) {
            case 0: // 000 EA = (BX) + (SI) + DISP
                $offset = $this->bx->toInt() + $this->si->toInt() + $disp;
                break;

            case 1: // 001 EA = (BX) + (DI) + DISP
                $offset = $this->bx->toInt() + $this->di->toInt() + $disp;
                break;

            case 2: // 010 EA = (BP) + (SI) + DISP
                $offset = $this->bp->toInt() + $this->si->toInt() + $disp;
                break;

            case 3: // 011 EA = (BP) + (DI) + DISP
                $offset = $this->bp->toInt() + $this->di->toInt() + $disp;
                break;

            case 4: // 100 EA = (SI) + DISP
                $offset = $this->si->toInt() + $disp;
                break;

            case 5: // 101 EA = (DI) + DISP
                $offset = $this->di->toInt() + $disp;
                break;

            case 6: // 110 EA = (BP) + DISP
                // except if mod = 00 and r/m = 110 then EA = disp-high; disp-low
                // @todo What needs to be done for the comment above?

                $offset = $this->bp->toInt() + $disp;
                break;

            case 7: // 111 EA = (BX) + DISP
                $offset = $this->bx->toInt() + $disp;
                break;

            default:
                throw new \RuntimeException(sprintf('getEffectiveRegisterMemoryAddress invalid: %d %b', $this->instr['rm_o'], $this->instr['rm_o']));
        }
        $address = new AbsoluteAddress(self::SIZE_BYTE << 1, $offset);
        return $address;
    }

    private function pushDataToStack(iterable $data, int $size)
    {
        $this->debugSsSpRegister();

        $this->sp->add(-$size);

        $address = $this->getEffectiveStackPointerAddress();
        $offset = $address->toInt();
        $this->ram->write($data, $offset, $size);

        $this->debugSsSpRegister();
    }

    private function pushRegisterToStack(Register $register)
    {
        $size = $register->getSize();
        if (self::SIZE_BYTE !== $size) {
            throw new \RangeException(sprintf('Wrong size. Register is %d bytes, data is %d bytes.', $register->getSize(), self::SIZE_BYTE));
        }

        $this->pushDataToStack($register->getData(), $size);
    }

    private function popFromStack(int $size): \SplFixedArray
    {
        $address = $this->getEffectiveStackPointerAddress();
        $offset = $address->toInt();
        $data = $this->ram->read($offset, $size);

        $this->sp->add($size);

        return $data;
    }

    private function writeAbsoluteAddressToRam(AbsoluteAddress $address, int $length)
    {
        $offset = $address->toInt();
        $data = $address->getData();
        $this->ram->write($data, $offset, $length);
    }

    private function setAuxiliaryFlagArith(int $src, int $dest, int $result): bool
    {
        $x = $dest ^ $result;
        $src ^= $x;

        $af = ($src >> 4) & 0x1;
        $this->flags->setByName('AF', $af);

        return $af;
    }

    private function setOverflowFlagArith1(int $src, int $dest, int $result, bool $isWord): void
    {
        if ($result === $dest) {
            $of = false;
        } else {
            $x = $dest ^ $result;
            $src ^= $x;

            $cf = $this->flags->getByName('CF');
            $topBit = $isWord ? 15 : 7;
            $of = ($cf ^ ($src >> $topBit)) & 1;
        }
        $this->flags->setByName('OF', $of);
    }

    private function setOverflowFlagArith2(int $dest, int $size, bool $direction): bool
    {
        $x = $dest + 1 - $direction;

        // $y = 1 << (($size << 3) - 1);
        $y = $size << 3;
        $y -= 1;
        $y = 1 << $y;

        $of = $x === $y;
        $this->flags->setByName('OF', $of);

        return $of;
    }

    private function debugSsSpRegister()
    {
        $address = $this->getEffectiveStackPointerAddress();
        $offset = $address->toInt();
        $data = $this->ram->read($offset, self::SIZE_BYTE);

        $this->output->writeln(sprintf(' -> %s %s -> %04x [%020b] -> 0=%02x 1=%02x', $this->ss, $this->sp, $offset, $offset, $data[0], $data[1]));
    }

    private function debugCsIpRegister(int $add = 0)
    {
        if (0 === $add) {
            $this->output->writeln(sprintf(' -> %s %s', $this->cs, $this->ip));
        } else {
            $this->output->writeln(sprintf(' -> %s %s (%d)', $this->cs, $this->ip, $add));
        }
    }

    private function debugInfo(string $text)
    {
        $text = sprintf('<bg=green>%s</>', $text);
        $this->output->writeln($text);
    }

    private function debugOp(string $text)
    {
        $text = sprintf('<bg=red;fg=white>%s</>', $text);
        $this->output->writeln($text);
    }

    private function debugAll()
    {
        $this->output->writeln(sprintf(' -> %s %s %s %s', $this->ax, $this->cx, $this->bx, $this->dx));

        //$this->output->writeln(sprintf(' -> %s',  $this->bp));
        $this->output->writeln(sprintf(' -> %s %s', $this->ss, $this->sp));
        $this->output->writeln(sprintf(' -> %s %s', $this->si, $this->di));

        $this->output->writeln(sprintf(' -> %s %s', $this->cs, $this->ip));
        //$this->output->writeln(sprintf(' -> %s', $this->es));
        //$this->output->writeln(sprintf(' -> %s', ));
        //$this->output->writeln(sprintf(' -> %s', $this->ds));

        $this->output->writeln(sprintf(' -> %s', $this->flags));

        $this->debugInstrData();
    }

    private function debugInstrData()
    {
        $fn = function (int $x) {
            return sprintf('%02x', $x);
        };

        /** @var \SplFixedArray $data */
        $data = $this->instr['data_b'];
        $data = $data->toArray();
        $data = array_map($fn, $data);
        $data = join(' ', $data);
        $this->output->writeln(sprintf(' -> data b: %s', $data));

        $data = $this->instr['data_w'];
        $data = $data->toArray();
        $data = array_map($fn, $data);
        $data = join(' ', $data);
        $this->output->writeln(sprintf(' -> data w: %s', $data));
    }
}
