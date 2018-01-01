<?php

namespace TheFox\I8086emu\Helper;

class DataHelper
{
    public static function arrayToInt(iterable $data)
    {
        $i = 0;
        foreach ($data as $index => $char) {
            $char=intval($char);
            $bits=$index<<3;
            //printf("i = %d\n", $index);
            //printf("c = %d\n", $char);
            //printf("b = %d\n\n", $bits);
            $i += $char << $bits;
        }
        return $i;
    }
}
