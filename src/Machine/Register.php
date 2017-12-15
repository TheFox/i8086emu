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
     * @var null|int[]
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
        if ($this->name) {
            $name = $this->name;
        } else {
            $name = 'REG';
        }

        if (is_iterable($this->data)) {
            $data = [$name, $this->data[1], $this->data[0]];
            return vsprintf('%s[%02x%02x]', $data);
        } elseif (null === $this->data) {
            return sprintf('%s[NULL]', $name);
        } else {
            throw new \RuntimeException('Unknown data type.');
        }
    }

    /**
     * @param string[]|int[]|Register|Address|\SplFixedArray $data
     */
    public function setData($data)
    {
        // Reset Integer value;
        $this->i = null;

        $this->data = new \SplFixedArray($this->getSize());

        if (is_iterable($data)) {
            $pos = 0;
            foreach ($data as $c) {
                if (is_string($c)) {
                    $this->data[$pos] = ord($c);
                } else {
                    $this->data[$pos] = $c;
                }

                $pos++;
            }
        } elseif (is_string($data)) {
            $data = str_split($data);
            $data = array_map('ord', $data);
            $this->data = \SplFixedArray::fromArray($data);
        } elseif ($data instanceof RegisterInterface || $data instanceof AddressInterface) {
            $this->setData($data->getData());
        } else {
            $this->data = $data;
        }
    }

    /**
     * @return int[]|null|\ArrayAccess
     */
    public function getData()
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

    /**
     * Convert Char data to Integer.
     */
    public function toInt(): int
    {
        if (null === $this->i) {
            if (is_iterable($this->data)) {
                $i = 0;
                $pos = 0;
                foreach ($this->data as $n) {
                    $i += $n << $pos;
                    $pos += 8;
                }

                $this->i = $i;
            } elseif (null === $this->data) {
                $this->i = 0;
            } else {
                throw new \RuntimeException('Unknown data type.');
            }

            $this->checkInt();
        }

        return $this->i;
    }

    public function toAddress(): Address
    {
        $address = new Address($this->toInt());
        return $address;
    }

    public function add(int $i): int
    {
        $i += $this->toInt();
        $this->i = $i;
        $this->checkInt();

        $data = new \SplFixedArray($this->getSize());

        $pos = 0;
        while ($i > 0 && $pos < 2) {
            $data[$pos] = $i & 0xFF;
            $i = $i >> 8;

            $pos += 1;
        }

        $this->data = $data;

        return $this->i;
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
