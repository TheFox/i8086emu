<?php

namespace TheFox\I8086emu\Test\Machine;

use PHPUnit\Framework\TestCase;
use TheFox\I8086emu\Machine\Ram;

class RamTest extends TestCase
{
    private function getNewRam(int $size = 0x10000)
    {
        return new Ram($size);
    }

    public function testWriteRead()
    {
        $ram = $this->getNewRam();

        // Write
        $ram->write([1, 2, 3], 0);

        $data = $ram->read(0, 1)->toArray();
        $this->assertEquals([1], $data);

        $data = $ram->read(0, 2)->toArray();
        $this->assertEquals([1, 2], $data);

        $data = $ram->read(0, 3)->toArray();
        $this->assertEquals([1, 2, 3], $data);

        // Read Offset > 0
        $data = $ram->read(1, 1)->toArray();
        $this->assertEquals([2], $data);

        $data = $ram->read(1, 2)->toArray();
        $this->assertEquals([2, 3], $data);

        // Read untouched RAM Addr.
        $data = $ram->read(0, 4)->toArray();
        $this->assertEquals([1, 2, 3, 0], $data);

        $data = $ram->read(2, 2)->toArray();
        $this->assertEquals([3, 0], $data);

        // Write Offset > 0
        $ram->write([4, 5, 6, 7], 5);

        $data = $ram->read(2, 5)->toArray();
        $this->assertEquals([3, 0, 0, 4, 5], $data);

        // Continue writing
        $ram->write([8, 9, 10], 9);

        $data = $ram->read(0, 12)->toArray();
        $this->assertEquals([1, 2, 3, 0, 0, 4, 5, 6, 7, 8, 9, 10], $data);
    }

    public function testWriteStr()
    {
        $ram = $this->getNewRam();

        $ram->writeStr("\x00\x01\x02\x03", 0);

        $data = $ram->read(0, 4)->toArray();
        $this->assertEquals([0, 1, 2, 3], $data);
    }

    public function testWriteBigStr()
    {
        $allocStr = 'ABCD';
        $size = 0x40000;

        $ram = $this->getNewRam(strlen($allocStr) * $size);

        $ram->writeStr(str_repeat($allocStr, $size), 0);

        $data = $ram->read(0, 4)->toArray();
        $this->assertEquals([65, 66, 67, 68], $data);
    }

    /**
     * @expectedException \RangeException
     */
    //public function testReadException1()
    //{
    //    $ram = new Ram(1);
    //    $ram->read(-1, 1);
    //}

    /**
     * @expectedException \RangeException
     */
    //public function testReadException2()
    //{
    //    $ram = new Ram(1);
    //    $ram->read(0, 0);
    //}
}
