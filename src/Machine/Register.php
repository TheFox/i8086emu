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
     * @var null|int[]|Address
     */
    private $data;

    /**
     * Size in Byte.
     *
     * @var int
     */
    private $size;

    public function __construct($data = null, int $size = 2)
    {
        $this->setData($data);
        $this->size = $size;
    }

    public function setData($data)
    {
        if (is_array($data)) {
            foreach ($data as $c) {
                if (is_string($c)) {
                    $this->data[] = ord($c);
                } else {
                    $this->data[] = $c;
                }
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
        if ($this->data instanceof AddressInterface) {
            return $this->data->toInt();
        } elseif (is_array($this->data)) {
            $i = 0;
            $pos = 0;
            foreach ($this->data as $n) {
                $i += $n << $pos;
                $pos += 8;
            }

            return $i;
        }

        return 0;
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

        $this->data = $data;
    }
}
