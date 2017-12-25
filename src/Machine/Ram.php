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
