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
use TheFox\I8086emu\Blueprint\OutputAwareInterface;
use TheFox\I8086emu\Blueprint\RamInterface;
use TheFox\I8086emu\Components\AbsoluteAddress;
use TheFox\I8086emu\Components\Address;
use TheFox\I8086emu\Components\ChildRegister;
use TheFox\I8086emu\Components\Flags;
use TheFox\I8086emu\Components\Register;
use TheFox\I8086emu\Exception\NotImplementedException;
use TheFox\I8086emu\Exception\UnknownTypeException;
use TheFox\I8086emu\Exception\ValueExceededException;
use TheFox\I8086emu\Helper\DataHelper;
use TheFox\I8086emu\Helper\NumberHelper;

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
    public const FLAGS_UPDATE_SZP = 1;
    public const FLAGS_UPDATE_AO_ARITH = 2;
    public const FLAGS_UPDATE_OC_LOGIC = 4;

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

            $this->output->writeln(sprintf('table %d', $i));

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
        $this->printXlatOpcodes();

        // Debug
        $this->output->writeln(sprintf('CS: %04x', $this->cs->toInt()));
        $this->output->writeln(sprintf('IP: %04x', $this->ip->toInt()));

        //$trapFlag = false;
        $segOverride = 0;
        $segOverrideEn = 0; // Segment Override
        //$repOverride = 0;
        $repOverrideEn = 0; // Repeat
        $repMode = null;

        $loop = 0;
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

            $ipAddress = $this->getEffectiveInstructionPointerAddress();
            $ipOffset = $ipAddress->toInt();

            $dataByte = $this->ram->read($ipOffset + 1, 5);
            $dataWord = \SplFixedArray::fromArray([
                ($dataByte[1] << 8) | $dataByte[0],
                ($dataByte[2] << 8) | $dataByte[1],
                ($dataByte[3] << 8) | $dataByte[2],
                ($dataByte[4] << 8) | $dataByte[3],
            ]);

            $this->debugInfo(sprintf(
                '[%s] run %d %04x:%04x -> OP x%02x %d [%08b] -> XLAT x%02x %d',
                'CPU',
                $loop,
                $this->cs->toInt(),
                $this->ip->toInt(),
                $opcodeRaw,
                $opcodeRaw,
                $opcodeRaw,
                $xlatId,
                $xlatId
            ));

            //$this->output->writeln(sprintf('data byte: 0=%02x 1=%02x 2=%02x 3=%02x 4=%02x',
            //    $dataByte[0], $dataByte[1], $dataByte[2], $dataByte[3], $dataByte[4]));
            //foreach ($dataWord as $n => $tmpWord) {
            //    $this->output->writeln(sprintf('data%d word: %x', $n, $tmpWord));
            //}

            // Segment Register Override
            // @todo move this to class parameter
            if ($segOverrideEn) {
                --$segOverrideEn;
                $defaultSegmentRegister = $this->getRegisterByNumber(true, $segOverride);
            } else {
                $defaultSegmentRegister = $this->ds;
            }
            if ($repOverrideEn) {
                --$repOverrideEn;
            }

            $iMod = 0; // Mode
            $iRm = 0; // Register/Memory
            $iReg = 0; // Register

            /** @var AbsoluteAddress|Address|Register $rm */
            $rm = null;
            /** @var AbsoluteAddress|Address|Register $from */
            $from = null;
            /** @var AbsoluteAddress|Address|Register $to */
            $to = null;

            $opSource = null;
            $opDest = null;
            $opResult = null; // Needs to be null for development.

            $debug = null;

            // $iModeSize > 0 indicates that opcode uses Mod/Reg/RM, so decode them.
            if ($iModeSize) {
                $iMod = $dataByte[0] >> 6;     // 11xxxxxx
                $iReg = $dataByte[0] >> 3 & 7; // xx111xxx
                $iRm = $dataByte[0] & 7;       // xxxxx111

                //$this->output->writeln(sprintf(' -> MOD %d  %02b', $iMod, $iMod));
                //$this->output->writeln(sprintf(' -> REG %d %03b', $iReg, $iReg));
                //$this->output->writeln(sprintf(' -> R/M %d %03b', $iRm, $iRm));

                switch ($iMod) {
                    case 0:
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
                        break;

                    case 3:
                        $dataWord[2] = $dataWord[1];
                        $dataByte[2] = $dataWord[2] & 0xFF;
                        break;

                    default:
                        throw new UnknownTypeException();
                }

                [$rm, $from, $to] = $this->decodeRegisterMemory($iw, $id, $iMod, $segOverrideEn, $segOverride, $iRm, $iReg, $dataWord[1]);

                $this->output->writeln(sprintf(' -> <info>FROM %s</info>', $from));
                $this->output->writeln(sprintf(' -> <info>TO   %s</info>', $to));
                //$this->output->writeln('---');
            }

            switch ($xlatId) {
                // Conditional jump (JAE, JNAE, etc.) - OpCodes: 70 71 72 73 74 75 76 77 78 79 7a 7b 7c 7d 7e 7f f1
                case 0:
                    // $iw is the invert Flag.
                    // For example, $iw == 0 means JAE, $iw == 1 means JNAE

                    $this->debugOp(sprintf(
                        'JMP %d/%x %d/%x',
                        $dataByte[0],
                        $dataByte[0],
                        $dataWord[0],
                        $dataWord[0]
                    ));

                    $flagId = ($opcodeRaw >> 1) & 7; // xxxx111x

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

                    //$this->debugOp(sprintf('JMP w=%d e=%b f=%d d0=%d/%x', $iw, $extra, $flagId,
                    //    $dataByte[0], $dataByte[0]));
                    //
                    //$this->output->writeln(sprintf(' -> A org: %d', $condDecodeA));
                    //$this->output->writeln(sprintf(' -> B org: %d', $condDecodeB));
                    //$this->output->writeln(sprintf(' -> C org: %d', $condDecodeC));
                    //$this->output->writeln(sprintf(' -> D org: %d', $condDecodeD));
                    //
                    //$this->output->writeln(sprintf(' -> Flag A: %2d %d', $realFlagIdA, $flagA));
                    //$this->output->writeln(sprintf(' -> Flag B: %2d %d', $realFlagIdB, $flagB));
                    //$this->output->writeln(sprintf(' -> Flag C: %2d %d', $realFlagIdC, $flagC));
                    //$this->output->writeln(sprintf(' -> Flag D: %2d %d', $realFlagIdD, $flagD));

                    $data = NumberHelper::unsignedIntToChar($dataByte[0]);

                    $flagsVal1 =
                        $flagA
                        || $flagB
                        || $flagC ^ $flagD;
                    $flagsVal2 = $flagsVal1 ^ $iw;
                    $add = $data * $flagsVal2;

                    $this->output->writeln(sprintf(' -> ADD %d %x', $add, $add));

                    //$this->debugCsIpRegister();
                    if ($add) {
                        $this->ip->add($add);
                    }
                    //$this->debugCsIpRegister();
                    break;

                case 1: // MOV reg, imm - OpCodes: b0 b1 b2 b3 b4 b5 b6 b7 b8 b9 ba bb bc bd be bf
                    $iw = (bool)($opcodeRaw & 8); // xxxx1xxx
                    $iwSize = $iw ? 2 : 1;
                    $from = $iw ? $dataWord[0] : $dataByte[0];
                    $to = $this->getRegisterByNumber($iw, $iReg4bit);

                    $this->debugOp(sprintf('MOV %s %x', $to, $from));

                    $to->setData($from);
                    $this->output->writeln(sprintf(' -> REG %s', $to));
                    break;

                case 2: // INC|DEC reg - OpCodes: 40 41 42 43 44 45 46 47 48 49 4a 4b 4c 4d 4e 4f
                    $id = ($opcodeRaw >> 3) & 1; // xxxx1xxx
                    $iw = true;
                    $iwSize = 2;
                    $register = $this->getRegisterByNumber($iw, $iReg4bit);

                    $this->debugOp(sprintf('%s %s', $id ? 'DEC' : 'INC', $register));

                    $opDest = $register->toInt();
                    $add = 1 - ($id << 1);
                    $register->add($add);

                    $opResult = $register->toInt();

                    $this->setAuxiliaryFlagArith(1, $opDest, $opResult);
                    $af = $this->flags->getByName('AF');

                    $x = $opDest + 1 - $id;
                    $y = 1 << (($iwSize << 3) - 1);
                    $of = $x === $y;
                    $this->flags->setByName('OF', $of);

                    $this->output->writeln(sprintf(' -> REG %s add=%d AF=%d OF=%d', $register, $add, $af, $of));
                    break;

                case 5: // INC|DEC|JMP|CALL|PUSH - OpCodes: fe ff
                    $this->debugOp(sprintf('INC|DEC|JMP|CALL|PUSH reg=%d f=%s d=%b', $iReg, $from, $dataByte[0]));
                    switch ($iReg) {
                        case 0:
                        case 1: // INC|DEC [loc]
                            $id = (bool)$iReg;
                            $add = 1 - ($id << 1);

                            if ($from instanceof Register) {
                                $opDest = $from->toInt();
                                $opResult = $from->add($add);
                                $this->output->writeln(sprintf(' -> %s', $from));
                            } elseif ($from instanceof AbsoluteAddress) {
                                $offset = $from->toInt();
                                $data = $this->ram->read($offset, $iwSize);
                                $opDest = DataHelper::arrayToInt($data);
                                $opResult = $opDest + $add;
                                $this->ram->write($opResult, $offset, $iwSize);
                                $this->output->writeln(sprintf(' -> RES %04x', $opResult));
                            }

                            $this->setAuxiliaryFlagArith(1, $opDest, $opResult);
                            $af = $this->flags->getByName('AF');

                            $x = $opDest + 1 - $id;
                            $y = 1 << (($iwSize << 3) - 1);
                            $of = $x === $y;
                            $this->flags->setByName('OF', $of);

                            $this->debugOp(sprintf('%s reg=%d d=%x d0=%08b w=%d AF=%d OF=%d 0x%x', $id ? 'DEC' : 'INC', $iReg, $id, $dataByte[0], $iw, $af, $of, $opResult));

                            // Decode like ADC.
                            $opcodeRaw = 0x10;
                            break;

                        default:
                            throw new NotImplementedException(sprintf('REG %d', $iReg));
                            break;
                    }

                    //if ($iReg < 2) {
                    //} elseif ($iReg != 6) { // JMP|CALL
                    //} else { // PUSH
                    //}
                    //throw new NotImplementedException();
                    break;

                case 3: // PUSH reg - OpCodes: 50 51 52 53 54 55 56 57
                    $register = $this->getRegisterByNumber(true, $iReg4bit);
                    $this->debugOp(sprintf('PUSH %s', $register));
                    $this->pushRegisterToStack($register);
                    break;

                case 4: // POP reg - OpCodes: 58 59 5a 5b 5c 5d 5e 5f
                    $register = $this->getRegisterByNumber(true, $iReg4bit);
                    $stackData = $this->popFromStack(self::SIZE_BYTE);
                    $register->setData($stackData);
                    $this->debugOp(sprintf('POP %s', $register));
                    break;

                case 7: // CMP reg, imm - OpCodes: 04 05 0c 0d 14 15 1c 1d 24 25 2c 2d 34 35 3c 3d
                    $rm = $this->ax;
                    $dataByte[2] = $dataByte[0];
                    $dataWord[2] = $dataWord[0];
                    $iReg = $extra; // Will later be switched back.
                    $this->ip->add(-1);
                // no break

                case 8: // CMP reg, imm - OpCodes: 80 81 82 83
                    $to = $rm;
                    //$this->debugOp(sprintf('CMP f=%s t=%s s=%d', $from, $to, $iwSize));

                    //$this->output->writeln(sprintf(' -> d 1 = %02b', $id));
                    $id |= !$iw;
                    //$this->output->writeln(sprintf(' -> d 2 = %02b', $id));

                    if ($id) {
                        $from = $dataByte[2];
                    } else {
                        $from = $dataWord[2];
                    }

                    //$this->output->writeln(sprintf(' -> from %s', $from));

                    $add = !$id + 1;
                    //$this->output->writeln(sprintf(' -> add %d', $add));
                    $this->ip->add($add);
                    //$this->output->writeln(sprintf(' -> %s', $this->ip));

                    // Decode
                    $opcodeRaw = 0x8 * $iReg;
                    $extra = $this->biosDataTables[self::TABLE_XLAT_SUBFUNCTION][$opcodeRaw];

                //$this->output->writeln(sprintf(' -> CMP %02x %s from=%x', $opcodeRaw, $this->ip, $from));
                // no break

                case 9: // ADD|OR|ADC|SBB|AND|SUB|XOR|CMP|MOV reg, r/m - OpCodes: 00 01 02 03 08 09 0a 0b 10 11 12 13 18 19 1a 1b 20 21 22 23 28 29 2a 2b 30 31 32 33 38 39 3a 3b 88 89 8a 8b
                    switch ($extra) {
                        case 0: // ADD
                            $this->debugOp(sprintf('ADD'));
                            throw new NotImplementedException();
                            break;

                        case 6: // XOR
                            $this->debugOp(sprintf('XOR %s %s', $to, $from));
                            if ($from instanceof Register && $to instanceof Register) {
                                $opResult = $from->toInt() ^ $to->toInt();
                                $to->setData($opResult);
                            } else {
                                throw new NotImplementedException();
                            }
                            break;

                        case 7: // CMP reg, imm
                            $this->debugOp(sprintf('CMP %s %s', $to, $from));

                            if ($from instanceof Register && $to instanceof Register) {
                                $opSource = $from->toInt();
                                $opDest = $to->toInt();
                            } elseif (is_numeric($from) && $to instanceof Register) {
                                $opSource = $from;
                                $opDest = $to->toInt();
                            } elseif (is_numeric($from) && $to instanceof AbsoluteAddress) {
                                $opSource = $from;

                                $offset = $to->toInt();
                                $data = $this->ram->read($offset, $iwSize);
                                $opDest = DataHelper::arrayToInt($data);
                            } else {
                                throw new NotImplementedException();
                            }

                            $opResult = $opDest - $opSource;
                            if ($iw) {
                                $uiOpResult = $opResult & 0xFFFF;
                            } else {
                                $uiOpResult = $opResult & 0xFF;
                            }

                            //$this->output->writeln(sprintf(' -> %b %b => %d/%x (%d/%x)', $opDest, $opSource,
                            //    $opResult, $opResult,
                            //    $uiOpResult, $uiOpResult));

                            //$cf = $opResult > $opDest;
                            //$this->output->writeln(sprintf(' ->  s CF=%d', $cf));
                            $cf = $uiOpResult > $opDest;
                            //$this->output->writeln(sprintf(' -> us CF=%d', $cf));

                            $this->flags->setByName('CF', $cf);
                            break;

                        case 8: // MOV
                            $this->debugOp(sprintf('MOV %s %s', $to, $from));

                            if ($from instanceof AbsoluteAddress && $to instanceof Register) {
                                $offset = $from->toInt();
                                $data = $this->ram->read($offset, $to->getSize());
                                $to->setData($data);
                                $this->output->writeln(sprintf(' -> %s', $to));
                            } elseif ($from instanceof Register && $to instanceof AbsoluteAddress) {
                                $offset = $to->toInt();
                                $data = $from->getData();
                                $this->ram->write($data, $offset, $from->getSize());
                                $this->output->writeln(sprintf(' -> %s', $to));
                            } elseif ($from instanceof Register && $to instanceof Register) {
                                $to->setData($from->getData());
                                $this->output->writeln(sprintf(' -> %s', $to));
                            } else {
                                throw new UnknownTypeException();
                            }
                            break;

                        default:
                            throw new NotImplementedException(sprintf('else %d %b', $extra, $extra));
                    }
                    break;

                case 10: // MOV sreg, r/m | POP r/m | LEA reg, r/m - OpCodes: 8c 8d 8e 8f
                    if (!$iw) {
                        // MOV
                        $iw = true;
                        $iwSize = 2;
                        $iReg += 8;
                        [$rm, $from, $to] = $this->decodeRegisterMemory($iw, $id, $iMod, $segOverrideEn, $segOverride, $iRm, $iReg, $dataWord[1]);
                        $this->debugOp(sprintf('MOV %s %s', $to, $from));

                        if ($from instanceof AbsoluteAddress && $to instanceof Register) {
                            $offset = $from->toInt();
                            $data = $this->ram->read($offset, $to->getSize());
                            $to->setData($data);
                            $this->output->writeln(sprintf(' -> %s', $to));
                        } elseif ($from instanceof Register && $to instanceof AbsoluteAddress) {
                            $offset = $to->toInt();
                            $data = $from->getData();
                            $this->ram->write($data, $offset, $from->getSize());
                            $this->output->writeln(sprintf(' -> %s', $to));
                        } elseif ($from instanceof Register && $to instanceof Register) {
                            $to->setData($from->toInt());
                            $this->output->writeln(sprintf(' -> REG %s', $to));
                        } else {
                            throw new UnknownTypeException();
                        }
                        //$this->output->writeln(sprintf(' -> to=%s', $to));
                    } elseif (!$id) {
                        // LEA
                        $segOverrideEn = 1;
                        $segOverride = 12; // Zero-Register

                        // Since the direction in this case is always false we have to swap $from/$to.
                        [$rm, $to, $from] = $this->decodeRegisterMemory($iw, $id, $iMod, $segOverrideEn, $segOverride, $iRm, $iReg, $dataWord[1]);
                        $this->debugOp(sprintf('LEA to=%s from=%s rm=%s', $to, $from, $rm));

                        $to->setData($from->toInt());
                        $this->output->writeln(sprintf(' -> to=%s', $to));
                    } else {
                        // POP
                        $this->debugOp(sprintf('POP'));
                        throw new NotImplementedException('POP');
                    }
                    break;

                case 11: // MOV AL/AX, [loc] - OpCodes: a0 a1 a2 a3
                    $address = $dataWord[0];
                    $offset = ($defaultSegmentRegister->toInt() << 4) + $address;

                    if ($iw) {
                        $register = $this->ax;
                    } else {
                        $register = $this->ax->getLowRegister();
                    }

                    if ($id) {
                        // Accumulator to Memory.
                        $this->debugOp(sprintf('MOV %x %s', $offset, $register));
                        $data = $register->getData();
                        $this->ram->write($data, $offset, $iwSize);
                    } else {
                        // Memory to Accumulator.
                        $this->debugOp(sprintf('MOV %s %x', $register, $offset));
                        $data = $this->ram->read($offset, $iwSize);
                        $register->setData($data);
                        $this->output->writeln(sprintf(' -> %s', $register));
                    }
                    break;

                case 14: // JMP | CALL short/near - OpCodes: e8 e9 ea eb
                    $this->debugOp(sprintf('JMP'));

                    $this->ip->add(3 - $id);
                    if (!$iw) {
                        if ($id) {
                            // JMP far
                            $this->debugOp(sprintf('JMP far'));
                            $this->ip->setData(0);
                        } else {
                            // CALL
                            $this->debugOp(sprintf('CALL'));
                            $this->debugOp(sprintf(' -> %s', $this->ip));
                            $this->pushRegisterToStack($this->ip);
                        }
                    }

                    if ($id && $iw) {
                        $add = $dataByte[0];
                    } else {
                        $add = $dataWord[0];
                    }

                    $this->debugCsIpRegister();
                    if ($add) {
                        $this->ip->add($add);
                    }
                    $this->debugCsIpRegister();
                    break;

                // NOP|XCHG AX, reg - OpCodes: 90 91 92 93 94 95 96 97
                case 16:
                    // For NOP the source and the destination is AX.
                    // Since AX is mandatory for 'XCHG AX, regs16' (not for 'XCHG reg, r/m'),
                    // NOP is the same as XCHG AX, AX.
                    $iw = true;
                    $iwSize = 2;
                    $from = $this->getRegisterByNumber($iw, $iReg4bit);
                    $to = $this->ax;
                    $this->debugOp(sprintf('NOP %s %s', $to, $from));
                // no break

                case 24: // NOP|XCHG reg, r/m - OpCodes: 86 87
                    $this->debugOp(sprintf('XCHG to=%s from=%s', $to, $from));
                    if ($from instanceof Register && $to instanceof Register) {
                        if ('AX' !== $from->getName()) { // Not NOP
                            // XCHG AX, reg
                            $this->output->writeln(sprintf(' -> OK REG'));

                            $tmp = $from->getData();
                            $from->setData($to->getData());
                            $to->setData($tmp);

                            $this->output->writeln(sprintf(' -> OK REG to=%s from=%s', $to, $from));
                        }
                    } elseif ($from instanceof AbsoluteAddress && $to instanceof Register) {
                        // XCHG reg, r/m
                        $offset = $from->toInt();
                        $length = $to->getSize();
                        $this->output->writeln(sprintf(' -> OK ADDR o=%x l=%d', $offset, $length));

                        $data = $this->ram->read($offset, $length);
                        $this->ram->write($to->getData(), $offset, $length);
                        $to->setData($data);

                        $this->output->writeln(sprintf(' -> OK REG to=%s from=%s', $to, $from));
                    } else {
                        throw new UnknownTypeException();
                    }
                    break;

                case 17: // MOVSx (extra=0)|STOSx (extra=1)|LODSx (extra=2) - OpCodes: a4 a5 aa ab ac ad
                    if ($repOverrideEn) {
                        $j = $this->cx->toInt();
                    } else {
                        $j = 1;
                    }

                    $ax = $this->getRegisterByNumber($iw, 0);
                    $add = (2 * $this->flags->getByName('DF') - 1) * ($iw + 1); // direction flag

                    $this->debugOp(sprintf('MOVSx|STOSx|LODSx w=%d e=%b a=%d', $iw, $extra, $add));

                    for ($i = $j; $i > 0; --$i) {
                        if (1 == $extra) {
                            // Extra 1: AL/AX
                            $from = $ax;
                        } else {
                            // Extra 0, 2: SEG:SI
                            $offset = ($defaultSegmentRegister->toInt() << 4) + $this->si->toInt();
                            $from = new AbsoluteAddress(self::SIZE_BYTE << 1, $offset);
                        }

                        if ($extra < 2) {
                            // Extra 0, 1: ES:DI
                            $to = $this->getEffectiveEsDiAddress();
                        } else {
                            // Extra 2: AL/AX
                            $to = $ax;
                        }

                        //$this->output->writeln(sprintf(' -> INDEX %d', $i));
                        //$this->output->writeln(sprintf(' -> FROM %s', $from));
                        //$this->output->writeln(sprintf(' -> TO   %s', $to));
                        //$this->output->writeln('');

                        if ($from instanceof Register && $to instanceof AbsoluteAddress) {
                            $data = $from->getData();
                            $offset = $to->toInt();
                            $this->ram->write($data, $offset, $from->getSize());
                        } elseif ($from instanceof AbsoluteAddress && $to instanceof Register) {
                            $offset = $from->toInt();
                            $data = $this->ram->read($offset, $to->getSize());
                            $to->setData($data, true);
                        } elseif ($from instanceof AbsoluteAddress && $to instanceof AbsoluteAddress) {
                            $offset = $from->toInt();
                            $data = $this->ram->read($offset, $iwSize);

                            $offset = $to->toInt();
                            $this->ram->write($data, $offset, $iwSize);
                        } else {
                            throw new  UnknownTypeException();
                        }

                        if (1 !== $extra) {
                            $this->si->add(-$add);
                        }
                        if (2 !== $extra) {
                            $this->di->add(-$add);
                        }
                    }

                    // Reset CX on repeat mode.
                    if ($repOverrideEn) {
                        $this->cx->setData(0);
                    }
                    break;

                case 19: // RET|RETF|IRET - OpCodes: c2 c3 ca cb cf
                    $this->debugOp(sprintf('RET %b %s', $extra, $this->ip));

                    // Restore IP register.
                    $data = $this->popFromStack($this->ip->getSize());
                    $this->ip->setData($data);
                    $this->output->writeln(sprintf(' -> POP %s', $this->ip));

                    if ($extra) { // IRET|RETF|RETF imm16
                        // Restore CS register.
                        $data = $this->popFromStack($this->cs->getSize());
                        $this->cs->setData($data);
                        $this->output->writeln(sprintf(' -> POP %s', $this->cs));
                    }

                    if ($extra & 2) { // IRET
                        // Restore Flags.
                        $data = $this->popFromStack($this->flags->getSize());
                        $this->flags->setData($data);
                        $this->output->writeln(sprintf(' -> POP %s', $this->flags));
                    } elseif (!$iw) { // RET|RETF imm16
                        $this->sp->setData($dataWord[0]);
                    }

                    $this->debugCsIpRegister();
                    $this->debugSsSpRegister();
                    break;

                case 20: // MOV r/m, immed - OpCodes: c6 c7
                    $data = $iw ? $dataWord[2] : $dataByte[2];

                    // $id is always true (1100011x) so take $from here.
                    $this->debugOp(sprintf('MOV %s %x', $from, $data));
                    $offset = $from->toInt();
                    $this->ram->write($data, $offset, $iwSize);
                    break;

                case 22: // OUT DX/imm8, AL/AX - OpCodes: e6 e7 ee ef
                    // @link https://pdos.csail.mit.edu/6.828/2010/readings/i386/OUT.htm

                    // AL/AX
                    $ax = $this->getRegisterByNumber($iw, 0);

                    if ($extra) {
                        $scratch = $this->dx->toInt();
                    } else {
                        $scratch = $dataByte[0];
                    }

                    $this->debugOp(sprintf(
                        'OUT word=%d extra=%d AL/AH=%s DX=%s v=%x',
                        $iw,
                        $extra,
                        $ax,
                        $this->dx,
                        $scratch
                    ));

                    // @todo create class to handle shit below
                    // handle Speaker control here
                    // handle PIT rate programming here
                    // handle Graphics here? Hm?
                    // handle Speaker here
                    // handle CRT video RAM start offset
                    // handle CRT cursor position
                    break;

                case 23: // REPxx - OpCodes: f2 f3
                    $repOverrideEn = 2;
                    $repMode = $iw;
                    if ($segOverrideEn) {
                        ++$segOverride;
                    }
                    $this->debugOp(sprintf('REP %d', $repMode));
                    break;

                case 25: // PUSH sreg - OpCodes: 06 0e 16 1e
                    $iReg = ($opcodeRaw >> 3) & 3; // xxx11xxx
                    $register = $this->getSegmentRegisterByNumber($iReg);
                    $this->debugOp(sprintf('PUSH %s', $register));
                    $this->pushRegisterToStack($register);
                    break;

                case 26: // POP sreg - OpCodes: 07 17 1f
                    $iReg = ($opcodeRaw >> 3) & 3; // xxx11xxx
                    if (($iReg + 8) !== $extra) {
                        throw new \RuntimeException(sprintf('In 8086tiny extra is used. %d != %d', $iReg, $extra));
                    }
                    $register = $this->getSegmentRegisterByNumber($iReg);
                    //$register = $this->getRegisterByNumber($iw, $extra);
                    $stackData = $this->popFromStack($register->getSize());
                    $register->setData($stackData);
                    $this->debugOp(sprintf('POP %s %d %d', $register, $iReg, $extra));
                    break;

                case 27: // xS: segment overrides - OpCodes: 26 2e 36 3e
                    $segOverrideEn = 2;
                    $segOverride = $extra;
                    if ($repOverrideEn) {
                        ++$repOverrideEn;
                    }
                    $iReg = ($opcodeRaw >> 3) & 3; // Segment Override Prefix = 001xx110, xx = Register
                    //$segOverride = $iReg;
                    $this->debugOp(sprintf('SEG override: %d %02b [%d %02b]', $extra, $extra, $iReg, $iReg));
                    break;

                case 33: // PUSHF - OpCodes: 9c
                    $this->debugOp(sprintf('PUSHF %s', $this->flags));
                    $data = $this->flags->getData();
                    $size = $this->flags->getSize();
                    $this->pushDataToStack($data, $size);
                    break;

                case 34: // POPF - OpCodes: 9d
                    $data = $this->popFromStack(self::SIZE_BYTE);
                    $i = DataHelper::arrayToInt($data);
                    $this->flags->setIntData($i);
                    $this->debugOp(sprintf('POPF %s', $this->flags));
                    break;

                case 39: // INT imm - OpCodes: cd
                    $this->debugOp(sprintf('INT %x', $dataByte[0]));
                    $this->ip->add(2);
                    $this->output->writeln(sprintf(' -> %s', $this->ip));
                    $this->interrupt($dataByte[0]);
                    break;

                case 44: // XLAT - OpCodes: d7
                    $offset = ($defaultSegmentRegister->toInt() << 4) + $this->bx->toInt() + $this->ax->getLowInt();

                    $data = $this->ram->read($offset, 1); // Read only one byte.

                    $this->debugOp(sprintf('XLAT seg=%s ax=%s offset=%x', $defaultSegmentRegister, $this->ax, $offset));
                    //$this->output->writeln(sprintf(' -> seg1: %x', $defaultSeg->toInt()));
                    //$this->output->writeln(sprintf(' -> seg2: %x', $defaultSeg->toInt() << 4));
                    //$this->output->writeln(sprintf(' -> AL: %x', $this->ax->getLowInt()));
                    //$this->output->writeln(sprintf(' -> AH: %x', $this->ax->getHighInt()));
                    $this->ax->setLowInt((int)$data[0]);
                    $this->output->writeln(sprintf(' -> AL: %x', $this->ax->getLowInt()));
                    //$this->output->writeln(sprintf(' -> AH: %x', $this->ax->getHighInt()));
                    break;

                case 46: // CLC|STC|CLI|STI|CLD|STD - OpCodes: f8 f9 fa fb fc fd
                    $val = $extra & 1;
                    $flagId = ($extra >> 1) & 7; // xxxx111x
                    $realFlagId = $this->biosDataTables[self::TABLE_FLAGS_BITFIELDS][$flagId];
                    $flagName = $this->flags->getName($realFlagId);
                    $this->debugOp(sprintf('CLx %02x (=%d [%08b]) ID=%d/%d v=%d F=%s', $extra, $extra, $extra, $flagId, $realFlagId, $val, $flagName));
                    $this->flags->set($realFlagId, $val);
                    break;

                case 53: // HLT OpCodes: 9b d8 d9 da db dc dd de df f0 f4
                    $this->debugOp('HLT');
                    $this->debugAll();
                    break 2;

                //case 55: // OpCodes: 68 69 6a 6b
                //    $this->debugOp('???');
                //    break;

                default:
                    throw new NotImplementedException(sprintf(
                        'OP 0x%02x (=%d [%08b]) xLatID 0x%02x (=%d [%08b])',
                        $opcodeRaw,
                        $opcodeRaw,
                        $opcodeRaw,
                        $xlatId,
                        $xlatId,
                        $xlatId
                    ));
            } // switch $xlatId

            // Increment instruction pointer by computed instruction length.
            // Tables in the BIOS binary help us here.
            $instSize = $this->biosDataTables[self::TABLE_BASE_INST_SIZE][$opcodeRaw];
            if ($this->biosDataTables[self::TABLE_I_W_SIZE][$opcodeRaw]) {
                $iwAdder = $iwSize;
            } else {
                $iwAdder = 0;
            }

            $add =
                (
                    $iMod * (3 !== $iMod)
                    + 2 * (!$iMod && 6 === $iRm)
                ) * $iModeSize
                + $instSize
                + $iwAdder;
            if ($add) {
                $this->debugCsIpRegister();
                $this->ip->add($add);
                $this->debugCsIpRegister();
            }

            // If instruction needs to update SF, ZF and PF, set them as appropriate.
            $setFlagsType = $this->biosDataTables[self::TABLE_STD_FLAGS][$opcodeRaw];
            if ($setFlagsType & self::FLAGS_UPDATE_SZP) {
                if (null === $opResult) {
                    throw new NotImplementedException('$opResult has not been set, but maybe it needs to be.');
                }

                $sign = $opResult < 0;
                $zero = $opResult == 0;

                // unsigned int. For example, int -42 = unsigned char 214
                // Since we deal with Integer values < 256 we only need a 0xFF-mask.
                $ucOpResult = $opResult & 0xFF;

                if ($ucOpResult < 0 || $ucOpResult > 255) {
                    throw new ValueExceededException(sprintf('ucOpResult is %d (%x, res=%d/%x). Must be >=0 and < 256.', $ucOpResult, $ucOpResult, $opResult, $opResult));
                }

                $pf = $this->biosDataTables[self::TABLE_PARITY_FLAG][$ucOpResult];

                $this->flags->setByName('SF', $sign);
                $this->flags->setByName('ZF', $zero);
                $this->flags->setByName('PF', $pf);

                if ($setFlagsType & self::FLAGS_UPDATE_AO_ARITH) {
                    $this->setAuxiliaryFlagArith($opSource, $opDest, $opResult);
                    $this->setOverflowFlagArith($opSource, $opDest, $opResult, $iw);
                }
                if ($setFlagsType & self::FLAGS_UPDATE_OC_LOGIC) {
                    $this->flags->setByName('CF', false);
                    $this->flags->setByName('OF', false);
                }
            }

            // Debug Flags
            $this->output->writeln(sprintf(' -> %s set=%b', $this->flags, $setFlagsType));

            // Update Instruction counter.
            ++$loop;

            //$int8 = false;
            //if (0 === $cycle % self::KEYBOARD_TIMER_UPDATE_DELAY) {
            //    $int8 = true;
            //}
            //
            //if (0 === $cycle % self::GRAPHICS_UPDATE_DELAY) {
            //    $this->updateGraphics();
            //}
            //
            //if ($trapFlag) {
            //    $this->interrupt(1);
            //}
            //$trapFlag = $this->flags->getByName('TF');

            // @todo interrupt 8
        } // while $opcodeRaw
    } // run()

    /**
     * @deprecated
     */
    private function decodeRegisterMemory(bool $isWord, bool $id, int $iMod, int $segOverrideEn, int $segOverride, int $iRm, int $iReg, int $data): iterable
    {
        $biosDataTableBaseIndex = 0;
        switch ($iMod) {
            case 0:
                $biosDataTableBaseIndex += 4;
            // no break

            case 1:
            case 2:
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
                    + $data * $dispMultiplier;

                $addr2 =
                    ($defaultSegReg->toInt() << 4)
                    + (0xFFFF & $addr1); // cast to "unsigned short".

                $rm = new AbsoluteAddress(self::SIZE_BYTE << 1, $addr2);

                //$this->output->writeln(sprintf(' -> DEF  %s %d', $defaultSegReg, $defaultSegId));
                //$this->output->writeln(sprintf(' -> REG1 %s %d', $register1, $register1Id));
                //$this->output->writeln(sprintf(' -> REG2 %s %d', $register2, $register2Id));
                //$this->output->writeln(sprintf(' -> ADDR1 %x', $addr1));
                //$this->output->writeln(sprintf(' -> ADDR2 %x', $addr2));
                break;

            case 3:
                // if mod = 11 then r/m is treated as a REG field
                $rm = $this->getRegisterByNumber($isWord, $iRm);
                break;

            default:
                throw new NotImplementedException(sprintf('Unhandled mode: %d', $iMod));
        } // switch $iMod

        if (!isset($rm)) {
            throw new \RuntimeException(sprintf('rm variable has not been set yet. mod=%d', $iMod));
        }

        $from = $to = $this->getRegisterByNumber($isWord, $iReg);
        if ($id) {
            $from = $rm;
        } else {
            $to = $rm;
        }

        return [$rm, $from, $to];
    }

    private function interrupt(int $code)
    {
        // Decode like INT
        //$opcodeRaw = 0xCD;
        //$xlatId = $this->biosDataTables[self::TABLE_XLAT_OPCODE][$opcodeRaw];
        //$extra = $this->biosDataTables[self::TABLE_XLAT_SUBFUNCTION][$opcodeRaw];
        //$iModeSize = $this->biosDataTables[self::TABLE_I_MOD_SIZE][$opcodeRaw];

        $this->output->writeln(sprintf(' -> Interrupt %02x', $code));

        // Push Flags.
        $this->output->writeln(sprintf(' -> PUSH %s', $this->flags));
        $this->pushDataToStack($this->flags->getData(), $this->flags->getSize());

        // Push Registers.
        $this->output->writeln(sprintf(' -> PUSH %s', $this->cs));
        $this->pushRegisterToStack($this->cs);
        $this->output->writeln(sprintf(' -> PUSH %s', $this->ip));
        $this->pushRegisterToStack($this->ip);

        // Write CS Register.
        $offset = ($code << 2) + 2;
        $this->output->writeln(sprintf(' -> CS Offset %d/%x', $offset, $offset));
        $this->ram->write($this->cs->getData(), $offset, $this->cs->getSize());

        // Set IP Register.
        $offset = $code << 2;
        $data = $this->ram->read($offset, $this->ip->getSize());
        $this->output->writeln(sprintf(' -> %s Offset %d/%x', $this->ip, $offset, $offset));
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
        throw new NotImplementedException('Update Graphics');
    }

    private function getRegisterByNumber(bool $isWord, int $regId, int $loop = 0): Register
    {
        if ($isWord) {
            return $this->registers[$regId];
        }
        if ($loop >= 2) {
            throw new \RuntimeException('Unhandled recursive call detected.');
        }

        $effectiveRegId = $regId & 3; // x11
        $register = $this->getRegisterByNumber(true, $effectiveRegId, 1 + $loop);

        $isHigh = $regId & 4; // 1xx
        return $register->getChildRegister($isHigh);
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
     * @param int $rm
     * @param int $disp
     * @return AbsoluteAddress
     */
    private function getEffectiveRegisterMemoryAddress(int $rm, int $disp): AbsoluteAddress
    {
        switch ($rm) {
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
                throw new \RuntimeException(sprintf('getEffectiveRegisterMemoryAddress invalid: %d %b', $rm, $rm));
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

    private function setAuxiliaryFlagArith(int $src, int $dest, int $result)
    {
        $x = $dest ^ $result;
        $src ^= $x;
        $af = ($src >> 4) & 0x1;
        $this->flags->setByName('AF', $af);
        //$this->output->writeln(sprintf(' -> AF %d', $af));
    }

    private function setOverflowFlagArith(int $src, int $dest, int $result, bool $isWord)
    {
        if ($result === $dest) {
            $of = 0;
        } else {
            $x = $dest ^ $result;
            $src ^= $x;

            $cf = $this->flags->getByName('CF');
            $topBit = $isWord ? 15 : 7;
            $of = ($cf ^ ($src >> $topBit)) & 1;
        }
        $this->flags->setByName('OF', $of);
        //$this->output->writeln(sprintf(' -> OF %d', $of));
    }

    private function debugSsSpRegister()
    {
        $address = $this->getEffectiveStackPointerAddress();
        $offset = $address->toInt();
        $data = $this->ram->read($offset, self::SIZE_BYTE);

        $this->output->writeln(sprintf(' -> %s %s -> %04x [%020b] -> 0=%02x 1=%02x', $this->ss, $this->sp, $offset, $offset, $data[0], $data[1]));
    }

    private function debugCsIpRegister()
    {
        $this->output->writeln(sprintf(' -> %s %s', $this->cs, $this->ip));
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

        //$data = $this->ram->read(0xf012a, 20)->toArray();
        $data = $this->ram->read(0xf1083, 20)->toArray();
        $l = count($data);
        for ($i = 0; $i < $l; $i += 1) {
            printf(" -> %d  %02x\n", $i, $data[$i]);
        }

        //$data = array_map('chr', $data);
        //$data = join('', $data);
    }
}
