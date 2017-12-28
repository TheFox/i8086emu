<?php

namespace TheFox\I8086emu\Helper;

class DataHelper
{
    public static function arrayToInt(iterable $data)
    {
        $i = 0;
        foreach ($data as $i => $c) {
            $i += $c << ($i << 3);
        }
        return $i;
    }
}
