<?php

namespace TheFox\I8086emu\Test\Helper;

use PHPUnit\Framework\TestCase;
use TheFox\I8086emu\Helper\NumberHelper;

class NumberHelperTest extends TestCase
{
    public function unsignedIntToCharDataProvider()
    {
        $data = [
            [0, 0],
            [1, 1],
            [126, 126],
            [127, 127],

            [255, -1],
            [256, 0],

            [217, -39],
            [45273, -39],

            [-39, -39],
            [-126, -126],
            [-127, -127],
            [-128, -128],
            [-129, 127],

            [-45273, 39],
        ];
        return $data;
    }

    /**
     * @dataProvider unsignedIntToCharDataProvider
     */
    public function testUnsignedIntToChar(int $i, int $e)
    {
        $x = NumberHelper::unsignedIntToChar($i);
        $this->assertEquals($e, $x);
    }
}
