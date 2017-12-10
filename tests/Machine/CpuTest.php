<?php

namespace TheFox\I8086emu\Test\Machine;

use PHPUnit\Framework\TestCase;

class CpuTest extends TestCase
{
    /**
     * @return array
     */
    public function idDataProvider()
    {
        $data = [
            [0, 0], // AL
            [1, 0], // CL
            [2, 1], // DL <-
            [3, 1], // BL <-
            [4, 0], // AH
            [5, 0], // CH
            [6, 1], // DH <-
            [7, 1], // BH <-
        ];
        return $data;
    }

    /**
     * @dataProvider idDataProvider
     * @param int $iReg4bit
     * @param int $expectedId
     */
    public function testId(int $iReg4bit, int $expectedId)
    {
        $id1 = $iReg4bit / 2 & 1;
        $this->assertEquals($expectedId, $id1);

        $id2 = $iReg4bit >> 1 & 1;
        $this->assertEquals($expectedId, $id2);
    }

    /**
     * @return array
     */
    public function iRegDataProvider()
    {
        $data = [
            [0, 0],
            [9, 1],
            [0x38, 7],
            [0x39, 7],
            [0xFFFF, 7],
        ];
        return $data;
    }

    /**
     * @dataProvider iRegDataProvider
     * @param int $iData0
     * @param int $expected
     */
    public function testIreg(int $iData0, int $expected)
    {
        $iReg1 = $iData0 / 8 & 7;
        $this->assertEquals($expected, $iReg1);

        $iReg2 = $iData0 >> 3 & 7;
        $this->assertEquals($expected, $iReg2);

        $iReg3 = ($iData0 & 0x38) >> 3; // xx111xxx
        $this->assertEquals($expected, $iReg3);
    }
}
