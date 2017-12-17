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
     * @param null|iterable|string|int $data
     */
    public function __construct($data = null)
    {
        $this->i = null;
        $this->data = new \SplFixedArray(2);

        if (is_iterable($data)) {
            $pos = 0;
            foreach ($data as $c) {
                if (is_string($c)) {
                    $this->data[$pos] = ord($c);
                } else {
                    $this->data[$pos] = $c;
                }

                $pos++;
                if ($pos >= 2) {
                    break;
                }
            }
        } elseif (is_string($data)) {
            $data = str_split($data);
            $data = array_map('ord', $data);
            $this->data = \SplFixedArray::fromArray($data);
        } elseif (is_numeric($data)) {
            $pos = 0;
            while ($data && $pos < 16) {
                $this->data[$pos] = $data & 0xFF;
                $data = $data >> 8;

                $pos++;
            }
        }
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

    public function getLow(): ?int
    {
        return $this->data[0];
    }

    /**
     * @return \SplFixedArray
     */
    public function getData(): \SplFixedArray
    {
        return $this->data;
    }
}
