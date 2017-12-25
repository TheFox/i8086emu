<?php

namespace TheFox\I8086emu\Machine;

class AbsoluteAddress extends Address
{
    /**
     * AbsoluteAddress constructor.
     * @param null|iterable|string|int $data
     * @param int $size Needs to be at least 3 bytes to address maximum 20 bits.
     */
    public function __construct($data = null, int $size = 3)
    {
        parent::__construct($data, $size);
    }
}
