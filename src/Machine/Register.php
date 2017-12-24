<?php

/**
 * This class holds one single CPU register.
 */

namespace TheFox\I8086emu\Machine;

use TheFox\I8086emu\Blueprint\AddressInterface;
use TheFox\I8086emu\Blueprint\RegisterInterface;
use TheFox\I8086emu\Exception\RegisterNegativeValueException;
use TheFox\I8086emu\Exception\RegisterValueExceedException;

class Register implements RegisterInterface, AddressInterface
{
    /**
     * @var null|Register
     */
    private $parent;

    /**
     * @var bool
     */
    private $isParentHigh;

    /**
     * @var string
     */
    private $name;

    /**
     * Data as Integer.
     * Also see $maxValue.
     *
     * @var int
     */
    private $i;

    /**
     * @var \SplFixedArray
     */
    private $data;

    /**
     * Size in Byte.
     *
     * @var int
     */
    private $size;

    /**
     * Base on the size this holds the maximum Integer value.
     *
     * @var int
     */
    private $maxValue;

    /**
     * Register constructor.
     * @param null $name
     * @param int[] $data
     * @param int $size
     */
    public function __construct($name = null, $data = null, int $size = 2)
    {
        $this->name = $name;
        $this->size = $size;

        $this->setData($data);
        $this->calcMaxVal();
    }

    public function __toString(): string
    {
        if ($this->getName()) {
            $name = $this->getName();
        } else {
            $name = 'REG';
        }

        $data = array_reverse($this->data->toArray());
        array_unshift($data, $name);

        $format = str_repeat('%02x', $this->getSize());
        $format = sprintf('%%s[%s]', $format);
        return vsprintf($format, $data);
    }

    /**
     * @param null|Register $parent
     */
    public function setParent(Register $parent = null)
    {
        $this->parent = $parent;
    }

    /**
     * @param bool $isParentHigh
     */
    public function setIsParentHigh(bool $isParentHigh)
    {
        $this->isParentHigh = $isParentHigh;
    }

    /**
     * @return string
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * @param int|string[]|int[]|Register|Address|\SplFixedArray $data
     */
    public function setData($data)
    {
        // Reset Integer value;
        $this->i = null;

        if (null !== $this->parent) {
            if (is_iterable($data)) {
                $data = $data[0];
            }
            if ($this->isParentHigh) {
                $this->parent->setHigh($data);
            } else {
                $this->parent->setLow($data);
            }
        } elseif ($data instanceof \SplFixedArray) {
            $this->data = clone $data;
        } elseif (is_array($data)) {
            $this->data = \SplFixedArray::fromArray($data);
        } elseif (is_string($data)) {
            $data = str_split($data);
            $data = array_map('ord', $data);
            $this->setData($data);
        } elseif (is_numeric($data)) {
            $this->data = new \SplFixedArray($this->getSize());
            $pos = 0;
            $this->i = $data;
            while ($data && $pos < $this->getSize()) {
                $n = $data & 0xFF;
                $this->data[$pos] = $n;
                $data >>= 8;
                $pos++;
            }
        } elseif ($data instanceof RegisterInterface || $data instanceof AddressInterface) {
            $this->setData($data->getData());
        } else {
            $this->data = new \SplFixedArray($this->getSize());
        }
    }

    /**
     * @return \SplFixedArray
     */
    public function getData()
    {
        return $this->data;
    }

    public function setLow(int $data)
    {
        $this->data[0] = $data;
    }

    public function getLow(): ?int
    {
        return $this->data[0];
    }

    public function setHigh(int $data)
    {
        $this->data[1] = $data;
    }

    public function getHigh(): ?int
    {
        return $this->data[1];
    }

    public function getEffectiveHigh()
    {
        return $this->getHigh() << 8;
    }

    /**
     * @return int
     */
    public function getSize(): int
    {
        return $this->size;
    }

    /**
     * Convert Char data to Integer.
     */
    public function toInt(): int
    {
        if (null !== $this->parent) {
            if ($this->isParentHigh) {
                return $this->parent->getEffectiveHigh();
            }
            return $this->parent->getLow();
        } elseif (null === $this->i) {
            if (is_iterable($this->data)) {
                $this->i = 0;
                $pos = 0;
                foreach ($this->data as $n) {
                    $this->i += $n << $pos;
                    $pos += 8;
                }
            }

            $this->checkInt();
        }

        return $this->i;
    }

    public function add(int $i)
    {
        $i += $this->toInt();
        $this->setData($i);
    }

    private function checkInt()
    {
        if ($this->i < 0) {
            throw new RegisterNegativeValueException('Register cannot have a negative value.');
        }

        if ($this->i > $this->maxValue) {
            throw new RegisterValueExceedException(sprintf('Wanted to assign %d to Register. Maximum %d is allowed.', $this->i, $this->maxValue));
        }
    }

    private function calcMaxVal()
    {
        // Calculate effective size.
        $this->maxValue = 0;
        $bits = 0;
        for ($i = $this->getSize(); $i > 0; --$i) {
            $this->maxValue += 0xFF << $bits;
            $bits += 8;
        }
    }
}
