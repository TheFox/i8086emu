<?php

namespace TheFox\I8086emu\Blueprint;

interface RegisterInterface
{
    public function getSize(): int;
    public function toInt(): int;
}
