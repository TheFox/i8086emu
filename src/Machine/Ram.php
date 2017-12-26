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
     * @param iterable|int $data
     * @param int $offset
     */
    public function write($data, int $offset)
    {
        $pos = $offset;

        if (is_iterable($data)) {
            foreach ($data as $c) {
                $this->data[$pos] = $c & 0xFF;
                ++$pos;
            }
        } elseif (is_numeric($data)) {
            for ($i = 0; $i < 16 && $data > 0; ++$pos, ++$i, $data >>= 8) {
                $this->data[$pos] = $data & 0xFF;
            }
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
