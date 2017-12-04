<?php

namespace TheFox\I8086emu\Machine;

use TheFox\I8086emu\Blueprint\FlagInterface;

class Flag implements FlagInterface
{
    private $data;

    public function __construct()
    {
        $this->data=[];
    }

    public function get(string $flag)
    {
        if (array_key_exists($flag,$this->data))
            return $this->data[$flag];

        return false;
    }

    public function set(string $flag, bool $val)
    {
        $this->data[$flag]=$val;
    }
}
