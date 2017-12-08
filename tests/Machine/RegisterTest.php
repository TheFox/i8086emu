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
        $this->assertEquals(2,$register->getSize());

        $register = new Register(null,3);
        $this->assertEquals(3,$register->getSize());
    }
    /**
     * @return array
     */
    public function toIntDataProvider(): array
    {
        $rv = [
            [null, 0],
            ["\x01\x02", 2 * 256 + 1],
            [new Address([1, 2, 3]), 3 * 256 * 256 + 2 * 256 + 1],
        ];
        return $rv;
    }

    /**
     * @dataProvider toIntDataProvider
     * @param string|Address $data
     * @param int $expected
     */
    public function testToInt($data, int $expected)
    {
        $register = new Register($data);
        $this->assertEquals($expected, $register->toInt());
    }

    public function testAdd()
    {
        $register = new Register([1, 2, 3]);
        $register->add(2);
        $this->assertEquals(3 * 256 * 256 + 2 * 256 + 3, $register->toInt());
    }
}
