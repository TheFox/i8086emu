<?php

namespace TheFox\i8086emu\Machine;

use TheFox\i8086emu\Blueprint\RamInterface;

class Ram implements RamInterface
{
    private $data;

    public function write(string $byte, int $pos)
    {
    }

    public function loadFile(string $path, int $offset = null)
    {
    }
}
