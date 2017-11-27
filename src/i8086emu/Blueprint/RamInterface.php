<?php

namespace TheFox\i8086emu\Blueprint;

interface RamInterface
{
    public function loadFile(string $path, int $offset = null);
}
