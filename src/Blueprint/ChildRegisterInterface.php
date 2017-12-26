<?php

namespace TheFox\I8086emu\Blueprint;

use TheFox\I8086emu\Components\Register;

interface ChildRegisterInterface
{
    public function setParent(Register $parent): void;

    public function isParentHigh(): bool;

    public function setIsParentHigh(bool $isParentHigh = true): void;
}
