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
        // signed char is from -127 to +127.
        if ($i > 127 || $i < -128) {
            //$r = $i % 256;
            $r = $i & 0xFF;

            if ($r > 127) {
                $r -= 0x100;
            }
            return $r;
        }

        return $i;
    }
}
