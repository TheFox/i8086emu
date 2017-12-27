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
        $this->assertFalse($flags->get(1));

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
}
