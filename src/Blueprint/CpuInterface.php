<?php

namespace TheFox\I8086emu\Blueprint;

interface CpuInterface
{
    public function setRam(RamInterface $ram);

    public function run();
}
