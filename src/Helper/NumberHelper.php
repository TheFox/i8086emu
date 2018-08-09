<?php

namespace TheFox\I8086emu\Helper;

class NumberHelper
{
    /**
     * Convert Unsigned Integer to Signed Char.
     *
     * @param int $i
     * @return int
     */
    public static function unsignedIntToChar(int $i): int
    {
        $signed = $i & 0x80;

        $x = $i & 0x7F;
        if ($signed) {
            return $x - 0x80;
        }

        return $x;
    }
}
