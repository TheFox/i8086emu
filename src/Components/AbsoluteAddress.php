<?php

namespace TheFox\I8086emu\Components;

use TheFox\I8086emu\Blueprint\AbsoluteAddressInterface;

class AbsoluteAddress extends Address implements AbsoluteAddressInterface
{
    /**
     * @param int $size Needs to be at least 3 bytes to address maximum 20 bits. To be correct with low/high use the next even number, 4.
     * @param null|iterable|string|int $data
     */
    public function __construct(int $size = 4, $data = null)
    {
        parent::__construct($size, $data);
    }

    public function __toString(): string
    {
        $s = sprintf('ABS_%s', parent::__toString());
        return $s;
    }
}
