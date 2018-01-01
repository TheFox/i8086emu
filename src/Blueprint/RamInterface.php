<?php

namespace TheFox\I8086emu\Blueprint;

interface RamInterface
{
    public function write($data, int $offset, int $length);

    public function read(int $offset, int $length): \SplFixedArray;
}
