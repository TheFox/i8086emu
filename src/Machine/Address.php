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
     * @param null|string|array $data
     */
    public function __construct($data = null)
    {
        if (is_array($data)) {
            foreach ($data as $c) {
                if (is_numeric($c)) {
                    $this->data[] = chr($c);
                } else {
                    $this->data[] = $c;
                }
            }
        } elseif (is_string($data)) {
            $dataLen = strlen($data);
            for ($i = 0; $i < $dataLen; $i++) {
                $c = $data[$i];
                $this->data[] = $c;
            }
        } else {
            $this->data = [];
        }
    }

    public function toInt(): int
    {
        $i = 0;
        $pos = 0;
        foreach ($this->data as $c) {
            $n = ord($c);
            #$e = pow(256, $pos);
            //$i += $n * $e;
            //$pos++;

            $i += $n << $pos;
            $pos += 8;
        }

        return $i;
    }
}
