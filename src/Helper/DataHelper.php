<?php

namespace TheFox\I8086emu\Helper;

class DataHelper
{
    public static function arrayToInt(iterable $data)
    {
        $i = 0;
        foreach ($data as $index => $char) {
            $char = intval($char);
            $bits = $index << 3;
            $i += $char << $bits;
        }
        return $i;
    }
}
