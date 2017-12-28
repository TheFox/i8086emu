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

            [2, 0x1234AB, null, [0x34AB, null, null, null]],
            [2, [255, 255, 255], null, [0xFFFF, null, null, null]],

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
         * @var int|null $expectedLowInt
         * @var int|null $expectedHighInt
         * @var int|null $expectedEffectiveHighInt
         */
        [
            $expectedInt,
            $expectedLowInt,
            $expectedHighInt,
            $expectedEffectiveHighInt,
        ] = $expected;

        $address = new Address();
        $address->setSize($size);
        $address->setData($data);
        if (null !== $add) {
            $address->add($add);
        }

        $this->assertEquals($expectedInt, $address->toInt());
        if (null !== $expectedLowInt) {
            $this->assertEquals($expectedLowInt, $address->getLowInt());
        }
        if (null !== $expectedHighInt) {
            $this->assertEquals($expectedHighInt, $address->getHighInt());
        }
        if (null !== $expectedEffectiveHighInt) {
            $this->assertEquals($expectedEffectiveHighInt, $address->getEffectiveHighInt());
        }
    }

    public function testSetLowHighBit16()
    {
        $address = new Address();
        $address->setSize(2);
        $address->setData([1, 2]);
        $data = $address->getData()->toArray();
        $this->assertEquals([1, 2], $data);

        $address->setLowInt(3);
        $data = $address->getData()->toArray();
        $this->assertEquals([3, 2], $data);

        $address->setHighInt(4);
        $data = $address->getData()->toArray();
        $this->assertEquals([3, 4], $data);

        $address->setLowInt(0x1234);
        $data = $address->getData()->toArray();
        $this->assertEquals([0x34, 4], $data);
    }

    public function testSetLowHighBit48()
    {
        $address = new Address();
        $address->setSize(6);
        $address->setData([0, 1, 2, 3, 4, 5]);
        $data = $address->getData()->toArray();
        $this->assertEquals([0, 1, 2, 3, 4, 5], $data);

        $address->setLowInt(3);
        $data = $address->getData()->toArray();
        $this->assertEquals([3, 0, 0, 3, 4, 5], $data);

        $address->setLowInt(0x123456);
        $data = $address->getData()->toArray();
        $this->assertEquals([0x56, 0x34, 0x12, 3, 4, 5], $data);
    }

    /**
     * @expectedException \TheFox\I8086emu\Exception\NegativeValueException
     */
    public function testNegativeValueException1()
    {
        new Address(1, -1);
    }
}
