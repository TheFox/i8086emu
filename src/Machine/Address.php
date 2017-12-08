<?php

namespace TheFox\I8086emu\Machine;

use TheFox\I8086emu\Blueprint\AddressInterface;

class Address implements AddressInterface
{
    /**
     * @var array
     */
    private $data;

    /**
     * Address constructor.
     * @param null|string|string[]|int[] $data
     */
    public function __construct($data = null)
    {
        $this->data=[];

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
        } elseif (is_numeric($data)) {
            $pos = 0;
            while ($data && $pos < 16) {
                $this->data[] = $data & 0xFF;
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
        $i = 0;
        $pos = 0;
        foreach ($this->data as $n) {
            $i += $n << $pos;
            $pos += 8;
        }

        return $i;
    }

    public function getLow():int
    {
        return $this->data[0];
    }
}