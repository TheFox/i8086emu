<?php

/**
 * This is the class for managing and simulating the Random-Access-Memory.
 */

namespace TheFox\I8086emu\Machine;

use TheFox\I8086emu\Blueprint\AddressInterface;
use TheFox\I8086emu\Blueprint\RamInterface;
use TheFox\I8086emu\Blueprint\RegisterInterface;

class Ram implements RamInterface
{
    /**
     * @var int
     */
    private $size;

    /**
     * @var int
     */
    private $writePointer;

    /**
     * @var array
     */
    private $data;

    public function __construct(int $size = 0x10000)
    {
        $this->size = $size;
        $this->writePointer = 0;
        $this->data = array_fill(0, $this->size, 0);
    }

    public function write(array $data, int $offset = null)
    {
        if (null === $offset) {
            $offset = $this->writePointer;
        }

        $pos = $offset;
        foreach ($data as $c) {
            $this->data[$pos] = $c;
            $pos++;
        }

        $this->writePointer = $pos;
    }

    public function writeStr(string $str, int $offset = null)
    {
        $data = str_split($str);
        $data = array_map('ord', $data);

        $this->write($data, $offset);
    }

    public function loadFromFile(string $path, int $offset = null, int $length = null)
    {
        $content = file_get_contents($path, false, null, 0, $length);
        $this->writeStr($content, $offset);
    }

    /**
     * @param int $offset
     * @param int $length
     * @return int[]
     */
    public function read(int $offset, int $length): array
    {
        if ($offset < 0) {
            throw new \RangeException(sprintf('Want to access %04x but minimum address is at 0', $offset));
        }
        if ($length <= 0) {
            throw new \RangeException('Length cannot be negative.');
        }

        $data = array_slice($this->data, $offset, $length);

        return $data;
    }

    /**
     * @param int $offset
     * @param int $length
     * @return AddressInterface
     */
    public function readAddress(int $offset, int $length): AddressInterface
    {
        $data = $this->read($offset, $length);
        $address = new Address($data);
        return $address;
    }

    /**
     * @param RegisterInterface $register
     * @return array|int[]
     */
    public function readFromRegister(RegisterInterface $register): array
    {
        $offset = $register->toInt();
        $length = $register->getSize();
        $data = $this->read($offset, $length);
        return $data;
    }
}
