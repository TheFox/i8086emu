<?php

namespace TheFox\I8086emu\Machine;

use TheFox\I8086emu\Blueprint\RegisterInterface;

class Register implements RegisterInterface
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var array
     */
    private $data;

    /**
     * Size in Byte.
     *
     * @var int
     */
    private $size;

    public function __construct(string $name = null, array $data = ["\x00", "\x00"], int $size = 2)
    {
        $this->name = $name;
        $this->data = $data;
        $this->size = $size;
    }

    public function setData(string $data)
    {
        $this->data = [$data[0], $data[1]];
    }

    public function getData(): ?string
    {
        $data = join('', $this->data);
        return $data;
    }

    public function setLow(string $low)
    {
        $this->data[0] = $low;
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
