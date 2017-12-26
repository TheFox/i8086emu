<?php

namespace TheFox\I8086emu\Blueprint;

use TheFox\I8086emu\Components\Register;

interface RegisterInterface
{
    public function getName(): string;

    public function setName(string $name): void;
}
