<?php

namespace TheFox\I8086emu\Components;

use TheFox\I8086emu\Blueprint\AddressInterface;
use TheFox\I8086emu\Exception\NegativeValueException;
use TheFox\I8086emu\Exception\ValueExceedException;

class Address implements AddressInterface
{
    /**
     * @var int
     */
    private $size;

    private $halfSize;

    /**
     * Base on the size this holds the maximum Integer value.
     *
     * @var int
     */
    private $maxValue;

    /**
     * Data as one Integer.
     *
     * @var int
     */
    private $dataInt;

    /**
     * Data as Integer array.
     *
     * @var \SplFixedArray
     */
    private $dataBytes;

    public function __construct(int $size = 2, $data = null)
    {
        $this->setSize($size);
        $this->setData($data);
    }

    public function __toString()
    {
        return 'TODO';
    }

    private function setSize(int $size = 2): void
    {
        $this->size = $size;
        $this->halfSize = $this->size >> 1;
        $this->maxValue = pow(256, $this->size) - 1;
    }

    /**
     * @return int
     */
    public function toInt(): int
    {
        return $this->dataInt;
    }

    /**
     * @param iterable|int $data
     */
    public function setData($data): void
    {
        if (is_iterable($data)) {
            $this->dataInt = 0;
            $this->dataBytes = new \SplFixedArray($this->size);

            if (count($data) > $this->size) {
                throw new ValueExceedException();
            }

            $bits = 0;
            $i = 0; // Index
            foreach ($data as $c) {
                $this->dataInt += $c << $bits;
                $this->dataBytes[$i] = $c;

                $bits += 8;
                ++$i;
            }
        } elseif (is_numeric($data)) {
            if ($data < 0) {
                throw new NegativeValueException();
            }
            if ($data > $this->maxValue) {
                // Reset internal data.
                $this->dataInt = 0;
                $this->dataBytes = new \SplFixedArray($this->size);

                throw new ValueExceedException();
            }

            $this->dataInt = $data;
            $this->dataBytes = new \SplFixedArray($this->size);

            $i = 0; // Index
            while (0 !== $data && $i < $this->size) {
                $this->dataBytes[$i] = $data & 0xFF;
                //$this->dataInt += $this->dataBytes[$i];

                $data >>= 8;
                ++$i;
            }
        } else {
            $this->dataInt = 0;
            $this->dataBytes = new \SplFixedArray($this->size);
        }
    }

    /**
     * @return \SplFixedArray
     */
    public function getData(): \SplFixedArray
    {
        return $this->dataBytes;
    }

    public function add(int $i): void
    {
        $endVal = $this->dataInt + $i;
        $this->setData($endVal);
    }

    public function getLowInt(): int
    {
        $mask = pow(256, $this->halfSize) - 1;
        $low = $this->dataInt & $mask;
        return $low;
    }

    public function getHighInt(): int
    {
        $bits = $this->halfSize << 3; // * 8
        $high = $this->dataInt >> $bits;
        return $high;
    }

    public function getEffectiveHighInt(): int
    {
        $bits = $this->halfSize << 3; // * 8
        $mask = pow(256, $this->halfSize) - 1;
        $mask <<= $bits;
        $high = $this->dataInt & $mask;
        return $high;
    }
}
