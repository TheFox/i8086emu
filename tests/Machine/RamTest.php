<?php

namespace TheFox\I8086emu\Test\Machine;

use PHPUnit\Framework\TestCase;
use TheFox\I8086emu\Machine\Ram;

class RamTest extends TestCase
{
    /**
     * @var Ram
     */
    protected $ram;

    protected function setUp()
    {
        $this->ram = $this->getNewRam();
    }

    private function getNewRam(int $size = 0x10000)
    {
        return new Ram($size);
    }

    public function testWriteReadArray()
    {
        // Write
        $this->ram->write([1, 2, 3], 0);

        $data = $this->ram->read(0, 1)->toArray();
        $this->assertEquals([1], $data);

        $data = $this->ram->read(0, 2)->toArray();
        $this->assertEquals([1, 2], $data);

        $data = $this->ram->read(0, 3)->toArray();
        $this->assertEquals([1, 2, 3], $data);

        // Read Offset > 0
        $data = $this->ram->read(1, 1)->toArray();
        $this->assertEquals([2], $data);

        $data = $this->ram->read(1, 2)->toArray();
        $this->assertEquals([2, 3], $data);

        // Read untouched RAM Addr.
        $data = $this->ram->read(0, 4)->toArray();
        $this->assertEquals([1, 2, 3, 0], $data);

        $data = $this->ram->read(2, 2)->toArray();
        $this->assertEquals([3, 0], $data);

        // Write Offset > 0
        $this->ram->write([4, 5, 6, 7], 5);

        $data = $this->ram->read(2, 5)->toArray();
        $this->assertEquals([3, 0, 0, 4, 5], $data);

        // Continue writing
        $this->ram->write([8, 9, 10], 9);

        $data = $this->ram->read(0, 12)->toArray();
        $this->assertEquals([1, 2, 3, 0, 0, 4, 5, 6, 7, 8, 9, 10], $data);
    }

    public function testWriteReadInt()
    {
        // Write
        $this->ram->write(0x42, 0);
        $this->ram->write(0x454443, 1);
        
        // Read
        $data = $this->ram->read(0, 4)->toArray();
        $this->assertEquals([0x42,0x43,0x44,0x45], $data);
    }
}
