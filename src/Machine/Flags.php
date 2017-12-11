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
            false, // carry flag
            false, // parity flag
            false, // auxiliary carry flag
            false, // zero flag
            false, // sign flag
            false, // trap flag
            false, // interrupt enable flag
            false, // direction flag
            false, // overflow flag
        ];
    }

    //public function get(string $flag)
    //{
    //    $flag = strtoupper($flag);
    //
    //    if (!array_key_exists($flag, $this->data)) {
    //        throw new \RangeException(sprintf('Flag %s does not exist.', $flag));
    //    }
    //
    //    return $this->data[$flag];
    //}
    //
    //public function set(string $flag, bool $val = true)
    //{
    //    $flag = strtoupper($flag);
    //    $this->data[$flag] = (bool)$val;
    //}

    public function set(int $flagId, bool $val)
    {
        $this->data[$flagId] = $val;
    }

    public function get(int $flagId)
    {
        return $this->data[$flagId];
    }
}
