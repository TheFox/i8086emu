<?php

namespace TheFox\I8086emu\Test\Machine;

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

    public function testWrite2()
    {
        $ram = new Ram();
        $ram->write('ABC',0,4);

        $this->assertEquals("ABC\x00", $ram->read(0, 5));
    }

    public function testRead1()
    {
        $ram = new Ram();
        $ram->write("\x00\x00",0);

        $data=$ram->read(0, 2);
        $this->assertEquals("\x00\x00",$data);
    }

    public function testWriteRead1()
    {
        $ram = new Ram();

        $ram->write('ABCD', 0xf);
        $data=$ram->read(0xf,4);

        $this->assertEquals('ABCD',$data);
    }
}
