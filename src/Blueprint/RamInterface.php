<?php

namespace TheFox\I8086emu\Blueprint;

interface RamInterface
{
    public function write(string $byte, int $offset = null, int $length = null);

    public function read(int $offset, int $length);

    //public function loadFile(string $path, int $offset = null);
}
