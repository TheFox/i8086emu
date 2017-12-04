<?php

namespace TheFox\I8086emu\Test\Machine;

use PHPUnit\Framework\TestCase;
use TheFox\I8086emu\Machine\Register;

class RegisterTest extends TestCase
{
    public function testSetDataLowFirst()
    {
        $register = new Register();

        // Set low first.
        $register->setLow('A');

        $low = $register->getLow();
        $high = $register->getHigh();
        $data = $register->getData();
        $this->assertEquals('A', $low);
        $this->assertEquals("\x00", $high);
        $this->assertEquals("A\x00", $data);

        // Then set high.
        $register->setHigh('B');

        $low = $register->getLow();
        $high = $register->getHigh();
        $data = $register->getData();
        $this->assertEquals('A', $low);
        $this->assertEquals('B', $high);
        $this->assertEquals('AB', $data);
    }

    public function testSetDataHighFirst()
    {
        $register = new Register();

        // Set high first.
        $register->setHigh('A');

        $low = $register->getLow();
        $high = $register->getHigh();
        $data = $register->getData();
        $this->assertEquals("\x00", $low);
        $this->assertEquals('A', $high);
        $this->assertEquals("\x00A", $data);

        // Then set low.
        $register->setLow('B');

        $low = $register->getLow();
        $high = $register->getHigh();
        $data = $register->getData();
        $this->assertEquals('B', $low);
        $this->assertEquals('A', $high);
        $this->assertEquals('BA', $data);
    }

    public function testGetData()
    {
        $register = new Register();

        // Initial value.
        $low = $register->getLow();
        $high = $register->getHigh();
        $data = $register->getData();
        $this->assertEquals("\x00", $low);
        $this->assertEquals("\x00", $high);
        $this->assertEquals("\x00\x00", $data);

        // Set Data
        $register->setData('AB');

        $low = $register->getLow();
        $this->assertEquals('A', $low);

        $high = $register->getHigh();
        $this->assertEquals('B', $high);

        $data = $register->getData();
        $this->assertEquals('AB', $data);
    }

    public function testToInt()
    {
        $register = new Register();

        $register->setData("\x01\x02");
        $this->assertEquals(2 * 256 + 1, $register->toInt());
    }
}
