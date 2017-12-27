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
}
