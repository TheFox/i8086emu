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

    public function __construct(int $size = 2, string $name = null)
    {
        $this->size = $size;
        $this->name = $name;
    }

    public function getSize(): ?int
    {
        return $this->size;
    }

    public function getName(): ?string
    {
        return $this->name;
    }
}
