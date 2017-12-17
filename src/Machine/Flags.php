<?php

/**
 * @link https://en.wikipedia.org/wiki/Intel_8086#Flags
 */

namespace TheFox\I8086emu\Machine;

use TheFox\I8086emu\Blueprint\FlagsInterface;

class Flags implements FlagsInterface
{
    private const NAMES = [
        'CF' => 0,
        'PF' => 1, // parity flag
        'AF' => 2,
        'ZF' => 3, // zero flag
        'SF' => 4, // sign flag
        'TF' => 5, // trap flag
        'IF' => 6,
        'DF' => 7,
        'OF' => 8,
    ];

    /**
     * @var \SplFixedArray
     */
    private $data;

    public function __construct()
    {
        $this->data = \SplFixedArray::fromArray([
            false, // carry flag
            false, // parity flag
            false, // auxiliary carry flag
            false, // zero flag
            false, // sign flag
            false, // trap flag
            false, // interrupt enable flag
            false, // direction flag
            false, // overflow flag
        ]);
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
}
