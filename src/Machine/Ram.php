<?php

/**
 * This is the class for managing and simulating the Random-Access-Memory.
 */

namespace TheFox\I8086emu\Machine;

use TheFox\I8086emu\Blueprint\RamInterface;
use TheFox\I8086emu\Blueprint\RegisterInterface;

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
     * @param int[]|\SplFixedArray $data
     * @param int $offset
     */
    public function write($data, int $offset)
    {
        if (!is_iterable($data)) {
            throw new \RuntimeException('Not iterable.');
        }

        $pos = $offset;
        foreach ($data as $c) {
            $this->data[$pos] = $c;
            $pos++;
        }
    }

    public function writeRaw(int $char, int $pos)
    {
        $this->data[$pos] = $char;
    }

    public function writeStr(string $str, int $offset)
    {
        $data = str_split($str);
        $data = array_map('ord', $data);

        $this->write($data, $offset);
    }

    public function writeRegister(Register $register, int $offset)
    {
        $this->write($register->getData(), $offset);
    }

    public function loadFromFile(string $path, int $offset = null, int $length = null)
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
        //if ($offset < 0) {
        //    throw new \RangeException(sprintf('Want to access %04x but minimum address is at 0', $offset));
        //}
        //if ($length <= 0) {
        //    throw new \RangeException('Length cannot be negative.');
        //}

        //$data = array_slice($this->data, $offset, $length);

        $data = new \SplFixedArray($length);
        $maxPos = $offset + $length;
        for ($pos = $offset, $i = 0; $pos < $maxPos; ++$pos, ++$i) {
            $data[$i] = $this->data[$pos];
        }

        return $data;
    }

    /**
     * @param int $offset
     * @param int $length
     * @return Address
     */
    public function readAddress(int $offset, int $length): Address
    {
        $data = $this->read($offset, $length);
        $address = new Address($data);
        return $address;
    }

    /**
     * @param RegisterInterface $register
     * @return \SplFixedArray
     */
    public function readFromRegister(RegisterInterface $register): \SplFixedArray
    {
        $offset = $register->toInt();
        $length = $register->getSize();
        $data = $this->read($offset, $length);
        return $data;
    }
}
