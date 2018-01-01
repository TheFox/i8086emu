<?php

namespace TheFox\I8086emu\Test\Components;

use PHPUnit\Framework\TestCase;
use TheFox\I8086emu\Components\ChildRegister;
use TheFox\I8086emu\Components\Register;

class ChildRegisterTest extends TestCase
{
    /**
     * @expectedException \RuntimeException
     */
    public function testSetData()
    {
        $parent = new Register();
        $child = new ChildRegister($parent);
        $child->setData([1, 2]);
    }

    public function addDataProvider()
    {
        $data = [
            [0x1234, 1, 0x1235, 0],
            [0x12ff, 2, 0x1201, 0],

            [0x1234, 1, 0x1334, 1],
            [0x12ff, 2, 0x14ff, 1],
            [0xff34, 2, 0x0134, 1],
        ];
        return $data;
    }

    /**
     * @dataProvider addDataProvider
     */
    public function testAdd(int $bi, int $add, int $expected, bool $isHigh)
    {
        $parent = new Register(2, $bi, 'PR');
        $child = new ChildRegister($parent, $isHigh, 'CR');
        $ni1=$child->add($add);

        $ni2 = $parent->toInt();
        $this->assertEquals($expected, $ni1);
        $this->assertEquals($expected, $ni2);
    }
}
