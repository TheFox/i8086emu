<?php

namespace TheFox\I8086emu\Test\Components;

use PHPUnit\Framework\TestCase;
use TheFox\I8086emu\Components\Flags;

class FlagsTest extends TestCase
{
    public function testFlag()
    {
        $flags = new Flags();

        // Defaults
        $this->assertFalse($flags->get(0)); // CF

        // Set 1
        $flags->set(0, true);
        $this->assertTrue($flags->get(0));
        $this->assertTrue($flags->getByName('CF'));
        $this->assertTrue($flags->get(1)); // Default true
        $this->assertFalse($flags->get(3)); // Default false

        // Set 2
        $flags->setByName('CF', false);
        $this->assertFalse($flags->get(0));

        // Name
        $name = $flags->getName(0);
        $this->assertEquals('CF', $name);
    }

    /**
     * @expectedException \TypeError
     */
    public function testException()
    {
        $flags = new Flags();
        $flags->get('INVALID');
    }

    public function testSetIntData()
    {
        $flags = new Flags();
        $flags->setIntData(0xFFAA); // 1111 1111 1010 1010

        $this->assertFalse($flags->get(0));
        $this->assertTrue($flags->get(1));
        $this->assertFalse($flags->get(2));
        $this->assertTrue($flags->get(3));

        $this->assertFalse($flags->get(4));
        $this->assertTrue($flags->get(5));
        $this->assertFalse($flags->get(6));
        $this->assertTrue($flags->get(7));

        $this->assertTrue($flags->get(8));
        $this->assertTrue($flags->get(9));
        $this->assertTrue($flags->get(10));
        $this->assertTrue($flags->get(11));

        $this->assertFalse($flags->get(12));
        $this->assertTrue($flags->get(13));
        $this->assertTrue($flags->get(14));
        $this->assertTrue($flags->get(15));

        $data = $flags->getData()->toArray();
        $this->assertEquals([0xAA, 0xEF], $data);
    }
}
