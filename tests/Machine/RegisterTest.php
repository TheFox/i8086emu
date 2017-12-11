<?php

namespace TheFox\I8086emu\Test\Machine;

use PHPUnit\Framework\TestCase;
use TheFox\I8086emu\Machine\Address;
use TheFox\I8086emu\Machine\Register;

class RegisterTest extends TestCase
{
    public function testSize()
    {
        $register = new Register();
        $this->assertEquals(2, $register->getSize());

        $register = new Register(null, null, 3);
        $this->assertEquals(3, $register->getSize());
    }

    /**
     * @return array
     */
    public function toIntDataProvider(): array
    {
        $data = [
            [null, 0],
            ["\x01\x02", 0x0201],
            [["\x02", "\x03"], 0x0302],
            [new Address([1, 2, 3]), 0x030201],
        ];
        return $data;
    }

    /**
     * @dataProvider toIntDataProvider
     * @param string|Address $data
     * @param int $expected
     */
    public function testToInt($data, int $expected)
    {
        $register = new Register(null, $data);
        $this->assertEquals($expected, $register->toInt());
    }

    public function testAdd()
    {
        $register = new Register(null, [1, 2, 3]);
        $register->add(2);
        $this->assertEquals(0x030203, $register->toInt());
    }

    public function testSub()
    {
        $register = new Register(null, [1, 2, 3]);
        $register->add(-2);
        $this->assertEquals(0x0301FF, $register->toInt());
    }

    public function testToAddress()
    {
        $register = new Register(null, new Address(0));
        $address = $register->toAddress();
        $this->assertEquals(0, $address->toInt());

        $register = new Register(null, 'A');
        $address = $register->toAddress();
        $this->assertEquals(65, $address->toInt());
    }
}
