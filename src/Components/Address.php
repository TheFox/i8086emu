<?php

namespace TheFox\I8086emu\Components;

use TheFox\I8086emu\Blueprint\AddressInterface;
use TheFox\I8086emu\Exception\ValueExceededException;

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
        if ($size > PHP_INT_SIZE) {
            throw new ValueExceededException(sprintf('Size cannot exceed PHP_INT_SIZE. (%d)', PHP_INT_SIZE));
        }

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

    public function setLowInt(int $low): int
    {
        $l = $low & $this->lowMask;
        $h = $this->dataEffectiveHighInt;
        $n = $h | $l;
        $this->setData($n);

        return $n;
    }

    public function getLowInt(): int
    {
        return $this->dataLowInt;
    }

    public function setHighInt(int $high): int
    {
        $l = $this->dataLowInt;
        $h = ($high << $this->halfBits) & $this->effectiveHighMask;
        $n = $h | $l;
        $this->setData($n);

        return $n;
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
     * Overwrite all fields.
     *
     * @param iterable|int $data
     * @param bool $reset Keeps the original data of the fields that has not been touched when false.
     */
    public function setData($data, bool $reset = true): void
    {
        $this->dataInt = 0;
        if ($reset) {
            $this->dataBytes = new \SplFixedArray($this->size);
        }

        if (is_iterable($data)) {
            $length = count($data);
            $bits = 0;
            $offset = 0;
            while ($offset < $this->size && $offset < $length) {
                $c = $data[$offset];
                $this->dataInt += $c << $bits;
                $this->dataBytes[$offset] = $c;

                ++$offset;
                $bits += 8;
            }
        } elseif (is_numeric($data)) {
            //if ($data < 0) {
            //    throw new NegativeValueException();
            //}

            $this->dataInt = $data & $this->maxValue;
            //$debug=sprintf("data int: %08x\n", $this->dataInt);

            $offset = 0;
            while (0 !== $data && $offset < $this->size) {
                $this->dataBytes[$offset] = $data & 0xFF;

                $data >>= 8;
                ++$offset;
            }
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

    public function add(int $i): int
    {
        $endVal = $this->dataInt + $i;
        $this->setData($endVal);

        return $this->dataInt;
    }
}
