<?php

/**
 * This class holds one single CPU register.
 */

namespace TheFox\I8086emu\Machine;

use TheFox\I8086emu\Blueprint\AddressInterface;
use TheFox\I8086emu\Blueprint\RegisterInterface;

class Register implements RegisterInterface, AddressInterface
{
    /**
     * @var string
     */
    private $name;

    /**
     * Data as Integer.
     *
     * @var int
     */
    private $i;

    /**
     * @var null|int[]|Address
     */
    private $data;

    /**
     * Size in Byte.
     *
     * @var int
     */
    private $size;

    public function __construct($name = null, $data = [0, 0], int $size = 2)
    {
        $this->name = $name;
        $this->setData($data);
        $this->size = $size;
    }

    public function __toString()
    {
        $data = [$this->data[1], $this->data[0]];
        if ($this->name) {
            array_unshift($data, $this->name);
        } else {
            array_unshift($data, 'REG');
        }
        return vsprintf('%s[%02x%02x]', $data);
    }

    public function setData($data)
    {
        // Reset Integer value;
        $this->i = null;

        if (is_array($data)) {
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
            $this->data = $data;
        } else {
            $this->data = $data;
        }
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
            if ($this->data instanceof AddressInterface) {
                $this->i = $this->data->toInt();
            } elseif (is_array($this->data)) {
                $i = 0;
                $pos = 0;
                foreach ($this->data as $n) {
                    $i += $n << $pos;
                    $pos += 8;
                }

                $this->i = $i;
            } else {
                throw new \RuntimeException('Unknown data type.');
            }
        }

        return $this->i;
    }

    public function toAddress(): Address
    {
        if ($this->data instanceof AddressInterface) {
            return $this->data;
        }

        $address = new Address($this->toInt());
        return $address;
    }

    public function add(int $i)
    {
        $i += $this->toInt();

        $data = [];

        $pos = 0;
        while ($i > 0 && $pos < 16) {
            $data[$pos] = $i & 0xFF;
            $i = $i >> 8;

            $pos += 1;
        }

        $this->i = null;
        $this->data = $data;
    }
}
