<?php

namespace TheFox\I8086emu\Blueprint;

interface FlagsInterface
{
    public function get(string $flag);
    public function set(string $flag, bool $val);
}
