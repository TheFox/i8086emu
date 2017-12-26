<?php

/**
 * This class holds one single CPU register.
 */

namespace TheFox\I8086emu\Components;

use TheFox\I8086emu\Blueprint\RegisterInterface;

class Register extends Address implements RegisterInterface
{
    /**
     * @var string
     */
    private $name;

    public function __construct(int $size = 2, $data = null, ?string $name = null)
    {
        parent::__construct($size, $data);

        $this->setName($name);
    }

    public function __toString(): string
    {
        $format = sprintf('%s[%%0%dx]', $this->getName(), $this->getSize()<<1);
        $s = sprintf($format, $this->toInt());
        return $s;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getName(): string
    {
        return $this->name;
    }
}
