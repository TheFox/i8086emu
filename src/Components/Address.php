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

    /**
     * @var int
     */
    private $halfSize;

    /**
     * @var int
     */
    private $halfBits;

    /**
     * Base on the size this holds the maximum Integer value.
     *
     * @var int
     */
    private $maxValue;

    /**
     * @var int
     */
    private $lowMask;

    /**
     * Bit Mask to get Effective High.
     *
     * @var int
     */
    private $effectiveHighMask;

    /**
     * Data as one Integer.
     *
     * @var int
     */
    private $dataInt;

    /**
     * @var int
     */
    private $dataLowInt;

    /**
     * @var int
     */
    private $dataHighInt;

    /**
     * @var int
     */
    private $dataEffectiveHighInt;

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

    public function __toString(): string
    {
        $format = sprintf('ADDR[%%0%dx]', $this->getSize() << 1);
        $s = sprintf($format, $this->toInt());
        return $s;
    }

    public function setSize(int $size = 2): void
    {
        $this->size = $size;
        $this->halfSize = $this->size >> 1;
        if ($this->halfSize < 1) {
            $this->halfSize = 1;
        }

        $this->maxValue = (1 << ($this->size << 3)) - 1;

        $this->halfBits = $this->halfSize << 3; // * 8
        $this->lowMask = (1 << $this->halfBits) - 1;
        $this->effectiveHighMask = $this->lowMask << $this->halfBits;
    }

    /**
     * @return int
     */
    public function getSize(): int
    {
        return $this->size;
    }

    public function getHalfSize(): int
    {
        return $this->halfSize;
    }

    /**
     * @return int
     */
    public function getHalfBits(): int
    {
        return $this->halfBits;
    }

    /**
     * @return int
     */
    public function toInt(): int
    {
        return $this->dataInt;
    }

    public function setLowInt(int $low): void
    {
        $l = $low & $this->lowMask;
        $h = $this->dataEffectiveHighInt;
        $n = $h | $l;
        $this->setData($n);
    }

    public function getLowInt(): int
    {
        return $this->dataLowInt;
    }

    public function setHighInt(int $high): void
    {
        $l = $this->dataLowInt;
        $h = ($high << $this->halfBits) & $this->effectiveHighMask;
        $n = $h | $l;
        $this->setData($n);
    }

    public function getHighInt(): int
    {
        return $this->dataHighInt;
    }

    public function getEffectiveHighInt(): int
    {
        return $this->dataEffectiveHighInt;
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

        $this->dataLowInt = $this->dataInt & $this->lowMask;
        $this->dataHighInt = $this->dataInt >> $this->halfBits;
        $this->dataEffectiveHighInt = $this->dataInt & $this->effectiveHighMask;
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
}
