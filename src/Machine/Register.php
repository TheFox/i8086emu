<?php

namespace TheFox\I8086emu\Machine;

use TheFox\I8086emu\Blueprint\RegisterInterface;

class Register implements RegisterInterface
{
    /**
     * Size in Byte.
     *
     * @var int
     */
    private $size;

    /**
     * @var string
     */
    private $name;

    /**
     * @var string
     */
    private $data;

    public function __construct(int $size = 2, string $name = null, string $data = null)
    {
        $this->size = $size;
        $this->name = $name;
        $this->data = $data;
    }

    public function getSize(): ?int
    {
        return $this->size;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setData(string $data)
    {
        $this->data = $data;
    }

    public function getData(): ?string
    {
        return $this->data;
    }

    public function setLow(string $low)
    {
        $this->data[0];
    }

    public function getLow(): ?string
    {
        return $this->data[0];
    }

    public function setHigh(string $low)
    {
        $this->data[1] = $low;
    }

    public function getHigh(): ?string
    {
        return $this->data[1];
    }
}
