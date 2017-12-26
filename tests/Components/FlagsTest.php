<?php

namespace TheFox\I8086emu\Test\Components;

use PHPUnit\Framework\TestCase;
use TheFox\I8086emu\Components\Flags;

class FlagsTest extends TestCase
{
    public function testFlag()
    {
        $flags = new Flags();
        $this->assertFalse($flags->get(0)); // CF

        $flags->set(0, true);
        $this->assertTrue($flags->get(0));
        $this->assertTrue($flags->getByName('CF'));
        $this->assertFalse($flags->get(1));

        $flags->setByName('CF', false);
        $this->assertFalse($flags->get(0));
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
