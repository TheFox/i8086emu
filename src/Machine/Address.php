<?php

namespace TheFox\I8086emu\Machine;

use TheFox\I8086emu\Blueprint\AddressInterface;

class Address implements AddressInterface
{
    /**
     * Data as Integer.
     *
     * @var int
     */
    private $i;

    /**
     * @var \SplFixedArray
     */
    private $data;

    /**
     * @var int
     */
    private $size;

    /**
     * @param null|iterable|string|int $data
     * @param int $size
     */
    public function __construct($data = null, int $size = 2)
    {
        $this->i = null;
        $this->data = new \SplFixedArray($size);
        $this->size = $size;

        if (is_iterable($data)) {
            $pos = 0;
            foreach ($data as $c) {
                if (is_string($c)) {
                    $this->data[$pos] = ord($c);
                } else {
                    $this->data[$pos] = $c;
                }

                $pos++;
                if ($pos >= $size) {
                    break;
                }
            }
        } elseif (is_string($data)) {
            $data = str_split($data);
            $data = array_map('ord', $data);
            $this->data = \SplFixedArray::fromArray($data);
        } elseif (is_numeric($data)) {
            $pos = 0;
            while ($data && $pos < $size) {
                $this->data[$pos] = $data & 0xFF;
                $data = $data >> 8;

                $pos++;
            }
        }
    }

    public function __toString()
    {
        $format = sprintf('ADDR[%%0%dx]', $this->getSize() << 1);
        $name = sprintf($format, $this->toInt());
        return $name;
    }

    /**
     * @return int
     */
    public function toInt(): int
    {
        if (null === $this->i) {
            $this->i = 0;
            $bits = 0;
            foreach ($this->data as $n) {
                $this->i += $n << $bits;
                $bits += 8;
            }
        }

        return $this->i;
    }

    /**
     * @deprecated
     */
    public function getLow(): ?int
    {
        throw new \RuntimeException('DEPRECATED');
    }

    /**
     * @return \SplFixedArray
     */
    public function getData(): \SplFixedArray
    {
        return $this->data;
    }

    /**
     * @return int
     */
    public function getSize(): int
    {
        return $this->size;
    }
}
