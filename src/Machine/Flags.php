<?php

/**
 * @link https://en.wikipedia.org/wiki/Intel_8086#Flags
 */

namespace TheFox\I8086emu\Machine;

use TheFox\I8086emu\Blueprint\FlagsInterface;

class Flags implements FlagsInterface
{
    /**
     * @var array
     */
    private $data;

    public function __construct()
    {
        $this->data = [
            'CF' => false, // carry flag
            'PF' => false, // parity flag
            'AF' => false, // auxiliary carry flag
            'ZF' => false, // zero flag
            'SF' => false, // sign flag
            'TF' => false, // trap flag
            'IF' => false, // interrupt enable flag
            'DF' => false, // direction flag
            'OF' => false, // overflow flag
        ];
    }

    public function get(string $flag)
    {
        $flag = strtoupper($flag);

        if (!array_key_exists($flag, $this->data)) {
            throw new \RangeException(sprintf('Flag %s does not exist.', $flag));
        }

        return $this->data[$flag];
    }

    public function set(string $flag, bool $val = true)
    {
        $flag = strtoupper($flag);
        $this->data[$flag] = (bool)$val;
    }
}
