<?php

namespace TheFox\I8086emu\Test\Components;

use PHPUnit\Framework\TestCase;
use TheFox\I8086emu\Components\Address;

class AddressTest extends TestCase
{
    public function toIntDataProvider()
    {
        $data = [
            [2, null, null, [0, 0, 0, 0]],
            [2, 1, null, [1, 1, 0, 0]],
            [2, 255, null, [255, 255, 0, 0]],
            [2, 257, null, [257, 1, 1, 0x100]],
            [2, [0xA, 0xB], null, [2826, 0xA, 0xB, 0xB00]],
            [2, [255, 255], null, [0xFFFF, 0xFF, 0xFF, 0xFF00]],
            [4, [255, 255, 255], null, [0xFFFFFF, 0xFFFF, 0xFF, 0xFF0000]],
            [2, 0xFF0A, null, [65290, 0xA, 0xFF, 0xFF00]],
            [4, 0x1234ABCD, null, [0x1234ABCD, 0xABCD, 0x1234, 0x12340000]],

            [2, 0xF00A, 0x0F00, [0xFF0A, 0xA, 0xFF, 0xFF00]],
            [4, 0xF000, 0xF000, [0x1E000, 0xE000, 1, 0x10000]],
        ];
        return $data;
    }

    /**
     * @dataProvider toIntDataProvider
     * @param int $size
     * @param $data
     * @param int|null $add
     * @param array $expected
     */
    public function testToInt(int $size, $data, ?int $add, array $expected)
    {
        /**
         * @var int $expectedInt
         * @var int $expectedLowInt
         * @var int $expectedHighInt
         * @var int $expectedEffectiveHighInt
         */
        [
            $expectedInt,
            $expectedLowInt,
            $expectedHighInt,
            $expectedEffectiveHighInt,
        ] = $expected;

        $address = new Address($size, $data);
        if (null !== $add) {
            $address->add($add);
        }

        $this->assertEquals($expectedInt, $address->toInt());
        $this->assertEquals($expectedLowInt, $address->getLowInt());
        $this->assertEquals($expectedHighInt, $address->getHighInt());
        $this->assertEquals($expectedEffectiveHighInt, $address->getEffectiveHighInt());
    }

    /**
     * @expectedException \TheFox\I8086emu\Exception\ValueExceedException
     */
    public function testValueExceedException1()
    {
        new Address(2, [1, 2, 3]);
    }

    /**
     * @expectedException \TheFox\I8086emu\Exception\ValueExceedException
     */
    public function testValueExceedException2()
    {
        new Address(1, 256);
    }

    /**
     * @expectedException \TheFox\I8086emu\Exception\NegativeValueException
     */
    public function testNegativeValueException1()
    {
        new Address(1, -1);
    }
}
