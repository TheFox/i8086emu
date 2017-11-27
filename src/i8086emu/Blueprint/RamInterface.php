<?php

namespace TheFox\i8086emu\Blueprint;

interface RamInterface
{
    public function write(string $byte, int $pos);
    public function loadFile(string $path, int $offset = null);
}
