<?php

namespace TheFox\I8086emu\Test\Components;

use PHPUnit\Framework\TestCase;
use TheFox\I8086emu\Components\ChildRegister;
use TheFox\I8086emu\Components\Register;

class RegisterTest extends TestCase
{
    public function testParent()
    {
        // Setup Parent
        $register1 = new Register(2, [1, 2], 'R1');

        // Setup Children
        $register2 = new ChildRegister($register1, false, 'RL');
        $register3 = new ChildRegister($register1, true, 'RH');

        $this->assertEquals(1, $register2->toInt());
        $this->assertEquals(2, $register3->toInt());
        $data2 = $register2->getData()->toArray();
        $data3 = $register3->getData()->toArray();
        $this->assertEquals([1], $data2);
        $this->assertEquals([2], $data3);
        
        // Set Low child.
        $register2->setData(0x105);

        $this->assertEquals(0x205, $register1->toInt());
        $this->assertEquals(5, $register2->toInt());
        $this->assertEquals(2, $register3->toInt());
        $data2 = $register2->getData()->toArray();
        $data3 = $register3->getData()->toArray();
        $this->assertEquals([5], $data2);
        $this->assertEquals([2], $data3);
        
        // Set High child.
        $register3->setData(0x104);

        $this->assertEquals(0x405, $register1->toInt());
        $this->assertEquals(5, $register2->toInt());
        $this->assertEquals(4, $register3->toInt());
        $data2 = $register2->getData()->toArray();
        $data3 = $register3->getData()->toArray();
        $this->assertEquals([5], $data2);
        $this->assertEquals([4], $data3);
    }
}
