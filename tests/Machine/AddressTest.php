<?php

namespace TheFox\I8086emu\Test\Machine;

use PHPUnit\Framework\TestCase;
use TheFox\I8086emu\Machine\Address;

class AddressTest extends TestCase
{
    public function testToInt()
    {
        $address = new Address(["\x0a", "\x0b"]);
        $this->assertEquals(2826, $address->toInt());

        $address = new Address([255, 255, 255]);
        $this->assertEquals(16777215, $address->toInt());

        $address = new Address("\x0b\x0c");
        $this->assertEquals(3083, $address->toInt());

    }
}
