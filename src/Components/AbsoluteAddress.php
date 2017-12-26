<?php

namespace TheFox\I8086emu\Components;

class AbsoluteAddress extends Address
{
    /**
     * AbsoluteAddress constructor.
     * @param int $size Needs to be at least 3 bytes to address maximum 20 bits. To be correct with low/high use the next even number, 4.
     * @param null|iterable|string|int $data
     */
    public function __construct(int $size = 4, $data = null)
    {
        parent::__construct($size, $data);
    }
}
