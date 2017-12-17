<?php

namespace TheFox\I8086emu\Test\Machine;

use PHPUnit\Framework\TestCase;
use TheFox\I8086emu\Machine\Ram;
use TheFox\I8086emu\Machine\Register;

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

    public function testWriteRead()
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

    public function testWriteRaw()
    {
        $this->ram->writeRaw(1, 0);
        $this->ram->writeRaw(2, 0);
        $this->ram->writeRaw(3, 1);

        $data = $this->ram->read(0, 2)->toArray();
        $this->assertEquals([2, 3], $data);
    }

    public function testWriteStr()
    {
        $this->ram->writeStr("\x00\x01\x02\x03", 0);

        $data = $this->ram->read(0, 4)->toArray();
        $this->assertEquals([0, 1, 2, 3], $data);
    }

    public function testWriteBigStr()
    {
        $allocStr = 'ABCD';
        $size = 0x40000;

        $this->ram = $this->getNewRam(strlen($allocStr) * $size);

        $s = str_repeat($allocStr, $size);
        $this->ram->writeStr($s, 0);

        $data = $this->ram->read(0, 4)->toArray();
        $this->assertEquals([65, 66, 67, 68], $data);
    }

    public function testWriteRegister()
    {
        $register = new Register('TT', [1, 2]);

        $this->ram->writeRegister($register, 0);

        $data = $this->ram->read(0, 2)->toArray();
        $this->assertEquals([1, 2], $data);
    }

    public function testLoadFromFile()
    {
        $file = sprintf('%s/../Resource/data/test1.txt', __DIR__);
        $realFile = realpath($file);

        $this->ram->loadFromFile($realFile, 2, 3);

        $data = $this->ram->read(0, 5)->toArray();
        $this->assertEquals([null, null, 65, 66, 67], $data);
    }

    public function testReadAddress()
    {
        $this->ram->writeStr("\x01\x02\x03\x04", 0);

        $address = $this->ram->readAddress(1, 3);
        $data = $address->getData()->toArray();
        $this->assertEquals([2, 3], $data);
    }

    public function testReadFromRegister()
    {
        $this->ram->writeStr("\x01\x02\x03\x04", 0);

        $register = new Register('TT', [2, 0]);

        $data = $this->ram->readFromRegister($register)->toArray();
        $this->assertEquals([3, 4], $data);
    }

    public function testSetGetLowHigh()
    {
        $register = new Register('TT');
        $this->assertNull($register->getLow());
        $this->assertNull($register->getHigh());

        $register->setLow(1);
        $register->setHigh(2);
        $l = $register->getLow();
        $h = $register->getHigh();
        $eh = $register->getEffectiveHigh();
        $this->assertEquals(1, $l);
        $this->assertEquals(2, $h);
        $this->assertEquals(512, $eh);
    }

    public function testParent()
    {
        $register1 = new Register('T1');
        $register1->setData([1, 2]);

        $register2 = new Register('T2');
        $this->assertEquals(0, $register2->toInt());

        $register2->setParent($register1);
        $this->assertEquals(1, $register2->toInt());

        $register2->setIsParentHigh(true);
        $this->assertEquals(512, $register2->toInt());

        $register2->setData([3, 4]);
        $this->assertEquals(3, $register1->getHigh());

        $register2->setData(5);
        $this->assertEquals(5, $register1->getHigh());

        $register2->setIsParentHigh(false);

        $register2->setData([6, 7]);
        $this->assertEquals(5, $register1->getHigh());
        $this->assertEquals(6, $register1->getLow());

        $register2->setData(8);
        $this->assertEquals(5, $register1->getHigh());
        $this->assertEquals(8, $register1->getLow());
    }
}
