<?php

namespace TheFox\I8086emu\Test\Machine;

use PHPUnit\Framework\TestCase;
use TheFox\I8086emu\Machine\Address;
use TheFox\I8086emu\Machine\Register;

class RegisterTest extends TestCase
{
    public function toStringDataProvider()
    {
        $data = [
            [null, null, 2, 'REG[0000]'],
            ['TT', null, 2, 'TT[0000]'],
            ['TT', [1, 2], 2, 'TT[0201]'],
            ['TT', [3], 1, 'TT[03]'],
        ];
        return $data;
    }

    /**
     * @dataProvider toStringDataProvider
     * @param null|string $name
     * @param array|null $data
     * @param int $size
     * @param string $expected
     */
    public function testToString(?string $name, ?array $data, int $size, string $expected)
    {
        $register = new Register($name, $data, $size);

        $this->assertEquals($expected, (string)$register);
    }

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
            [[2, 3], 0x0302],
            [new Address([1, 2, 3]), 0x0201], // Index 2 will be ignored by Address.
        ];
        return $data;
    }

    /**
     * @dataProvider toIntDataProvider
     * @param string|array|Address $data
     * @param int $expected
     */
    public function testToInt($data, int $expected)
    {
        $register = new Register(null, $data, 3);
        $i = $register->toInt();
        $l = $register->getLow();
        $h = $register->getHigh();
        $this->assertEquals($expected, $i);
        $this->assertEquals($expected & 0xFF, $l);
        $this->assertEquals(($expected >> 8), $h);
    }

    public function testAdd()
    {
        $register = new Register(null, [1, 2, 3], 3);
        $register->add(2);
        $this->assertEquals(0x030203, $register->toInt());
    }

    public function testSub()
    {
        $register = new Register(null, [1, 2, 3], 3);
        $register->add(-2);
        $this->assertEquals(0x0301FF, $register->toInt());
    }

    /**
     * @expectedException \TheFox\I8086emu\Exception\RegisterNegativeValueException
     */
    public function testCheckIntException1()
    {
        $register = new Register('TT', [-1, 0]);
        $register->toInt();
    }

    /**
     * @expectedException \TheFox\I8086emu\Exception\RegisterValueExceedException
     */
    public function testCheckIntException2()
    {
        $register = new Register('TT', [256, 256]);
        $register->toInt();
    }

    public function testSetGetLowHigh()
    {
        $register = new Register('TT');
        $this->assertNull($register->getLow());
        $this->assertNull($register->getHigh());

        $register->setLow(1);
        $register->setHigh(2);
        $l = $register->getLow();
        $h = $register->getHigh();
        $eh = $register->getEffectiveHigh();
        $this->assertEquals(1, $l);
        $this->assertEquals(2, $h);
        $this->assertEquals(512, $eh);
    }

    public function testParent()
    {
        $register1 = new Register('T1');
        $register1->setData([1, 2]);

        $register2 = new Register('T2');
        $this->assertEquals(0, $register2->toInt());

        $register2->setParent($register1);
        $this->assertEquals(1, $register2->toInt());

        $register2->setIsParentHigh(true);
        $this->assertEquals(512, $register2->toInt());

        $register2->setData([3, 4]);
        $this->assertEquals(3, $register1->getHigh());

        $register2->setData(5);
        $this->assertEquals(5, $register1->getHigh());

        $register2->setIsParentHigh(false);

        $register2->setData([6, 7]);
        $this->assertEquals(5, $register1->getHigh());
        $this->assertEquals(6, $register1->getLow());

        $register2->setData(8);
        $this->assertEquals(5, $register1->getHigh());
        $this->assertEquals(8, $register1->getLow());
    }
}
