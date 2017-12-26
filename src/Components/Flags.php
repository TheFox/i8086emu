<?php

/**
 * @link https://en.wikipedia.org/wiki/Intel_8086#Flags
 */

namespace TheFox\I8086emu\Components;

use TheFox\I8086emu\Blueprint\FlagsInterface;

class Flags implements FlagsInterface
{
    public const NAMES = [
        'CF' => 0, // carry flag
        'R1' => 1, // 1 reserved
        'PF' => 2, // parity flag
        'R3' => 3, // 3 reserved
        'AF' => 4, // auxiliary carry flag
        'R5' => 5, // 5 reserved
        'ZF' => 6, // zero flag
        'SF' => 7, // sign flag
        'TF' => 8, // trap flag
        'IF' => 9, // interrupt enable flag
        'DF' => 10, // direction flag
        'OF' => 11, // overflow flag
        'XF' => 12, // 12 reserved
        'R13' => 13, // 13 reserved
        'R14' => 14, // 14 reserved
        'R15' => 15, // 15 reserved
    ];

    /**
     * @var \SplFixedArray
     */
    private $data;

    /**
     * @var array
     */
    private $flippedNames;

    public function __construct()
    {
        $this->data = \SplFixedArray::fromArray([
            false, // carry flag
            false, // 1 reserved
            false, // parity flag
            false, // 3 reserved
            false, // auxiliary carry flag
            false, // 5 reserved
            false, // zero flag
            false, // sign flag
            false, // trap flag
            false, // interrupt enable flag
            false, // direction flag
            false, // overflow flag
            false, // 12 reserved
            false, // 13 reserved
            false, // 14 reserved
            false, // 15 reserved
        ]);

        $this->flippedNames = array_flip(self::NAMES);
    }

    public function set(int $flagId, bool $val)
    {
        $this->data[$flagId] = $val;
    }

    public function setByName(string $name, bool $val)
    {
        $this->set(self::NAMES[$name], $val);
    }

    public function get(int $flagId): bool
    {
        return $this->data[$flagId];
    }

    public function getByName(string $name): bool
    {
        return $this->get(self::NAMES[$name]);
    }

    public function getName(int $flagId)
    {
        return $this->flippedNames[$flagId];
    }
}
