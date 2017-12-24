<?php

namespace TheFox\I8086emu\Test\Machine;

use PHPUnit\Framework\TestCase;
use TheFox\I8086emu\Machine\Address;

class AddressTest extends TestCase
{
    public function toIntDataProvider()
    {
        $data = [
            [null, 0],
            [1, 1],
            [255, 255],
            [257, 257],
            ["\x0a\x0b", 2826],
            ["\x0b\x0c", 3083],
            [["\x0a", "\x0b"], 2826],
            [[255, 255, 255], 0xFFFF], // Index 2 will be ignored.
            [0xFF0A, 65290],
        ];
        return $data;
    }

    /**
     * @dataProvider toIntDataProvider
     * @param null|string|string[]|int[] $data
     * @param int $expectedInt
     */
    public function testToInt($data, int $expectedInt)
    {
        $address = new Address($data);
        $this->assertEquals($expectedInt, $address->toInt());
    }
}
