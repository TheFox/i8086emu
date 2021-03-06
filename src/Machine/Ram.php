<?php

/**
 * This is the class for managing and simulating the Random-Access-Memory.
 */

namespace TheFox\I8086emu\Machine;

use TheFox\I8086emu\Blueprint\RamInterface;
use TheFox\I8086emu\Exception\UnknownTypeException;

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
     * @param int $length
     */
    public function write($data, int $offset, int $length): void
    {
        $pos = $offset;
        $maxPos = $pos + $length;

        if (is_iterable($data)) {
            foreach ($data as $c) {
                $this->data[$pos] = intval($c) & 0xFF;

                ++$pos;
                if ($pos === $maxPos) {
                    break;
                }
            }
        } elseif (is_numeric($data)) {
            for (; $data > 0 && $pos < $maxPos; ++$pos, $data >>= 8) {
                $this->data[$pos] = $data & 0xFF;
            }
        } elseif (is_string($data)) {
            $data = str_split($data);
            $data = array_map('ord', $data);
            $this->write($data, $offset, $length);
        } else {
            throw new UnknownTypeException();
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
