<?php

/**
 * @link https://en.wikipedia.org/wiki/Intel_8086#Flags
 */

namespace TheFox\I8086emu\Machine;

use TheFox\I8086emu\Blueprint\FlagsInterface;

class Flags implements FlagsInterface
{
    private const NAMES = [
        'cf' => 0,
        'pf' => 1,
        'af' => 2,
        'zf' => 3,
        'sf' => 4,
        'tf' => 5,
        'if' => 6,
        'df' => 7,
        'of' => 8,
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

    public function get(int $flagId): bool
    {
        return $this->data[$flagId];
    }

    public function getByName(string $name): bool
    {
        $name = strtolower($name);
        return $this->get(self::NAMES[$name]);
    }
}
