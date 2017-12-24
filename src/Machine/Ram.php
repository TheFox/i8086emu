<?php

/**
 * This is the class for managing and simulating the Random-Access-Memory.
 */

namespace TheFox\I8086emu\Machine;

use TheFox\I8086emu\Blueprint\RamInterface;

class Ram implements RamInterface
{
    /**
     * @var int
     */
    private $size;

    /**
     * @var \SplFixedArray
     */
    private $data;

    public function __construct(int $size = 0x10000)
    {
        $this->size = $size;
        $this->data = new \SplFixedArray($this->size);
    }

    /**
     * @param iterable $data
     * @param int $offset
     */
    public function write(iterable $data, int $offset)
    {
        $pos = $offset;
        foreach ($data as $c) {
            $this->data[$pos] = $c;
            $pos++;
        }
    }

    /**
     * @deprecated
     * @param int $char
     * @param int $pos
     */
    public function writeRaw(int $char, int $pos)
    {
        $this->data[$pos] = $char;
    }

    /**
     * @deprecated
     * @param string $str
     * @param int $offset
     */
    public function writeStr(string $str, int $offset)
    {
        $data = str_split($str);
        $data = array_map('ord', $data);

        $this->write($data, $offset);
    }

    /**
     * @deprecated Move to Ram
     */
    public function writeRegister(Register $register, int $offset)
    {
        $data = $register->getData();
        $this->write($data, $offset);
    }

    /**
     * @deprecated Move to Ram
     */
    public function writeRegisterToAddress(Register $register, Address $address)
    {
        $offset = $address->toInt();
        $this->writeRegister($register, $offset);
    }

    /**
     * @deprecated Remove from Ram. Maybe move to Machine?
     */
    public function loadFromFile(string $path, int $offset, int $length = null)
    {
        $content = file_get_contents($path, false, null, 0, $length);
        $this->writeStr($content, $offset);
    }

    /**
     * @param int $offset
     * @param int $length
     * @return \SplFixedArray
     */
    public function read(int $offset, int $length): \SplFixedArray
    {
        $data = new \SplFixedArray($length);
        $maxPos = $offset + $length;
        for ($pos = $offset, $i = 0; $pos < $maxPos; ++$pos, ++$i) {
            $data[$i] = $this->data[$pos];
        }

        return $data;
    }
}
