<?php

namespace TheFox\I8086emu\Test;

use PHPUnit\Framework\TestCase;
use TheFox\I8086emu\Machine\Ram;

class RamTest extends TestCase
{
    public function testWrite1()
    {
        $ram = new Ram();
        $ram->write('A');
        $ram->write('B');
        $ram->write('C');
        $ram->write('D');
        $ram->write('X', 2);

        $this->assertEquals('ABXD', $ram->read(0, 4));
        $this->assertEquals('ABXD', $ram->read(0, 5));
        $this->assertEquals('BXD', $ram->read(1, 5));
        $this->assertEquals('BX', $ram->read(1, 2));
        $this->assertEquals('B', $ram->read(1, 1));
        $this->assertEquals('', $ram->read(1, 0));
    }

    //public function testWrite2()
    //{
    //    $ram = new Ram();
    //    $ram->write(0x0102);
    //    $this->assertEquals("\x02\x01", $ram->read(0, 2));
    //}
}
