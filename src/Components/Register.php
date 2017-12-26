<?php

/**
 * This class holds one single CPU register.
 */

namespace TheFox\I8086emu\Components;

use TheFox\I8086emu\Blueprint\RegisterInterface;

final class Register extends Address implements RegisterInterface
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var null|Register
     */
    private $parent;

    /**
     * @var bool
     */
    private $isParentHigh;

    public function __construct(int $size = 2, ?iterable $data = null, ?string $name = null)
    {
        parent::__construct($size, $data);

        $this->setName($name);
        $this->parent = null;
        $this->isParentHigh = false;
    }

    public function __toString()
    {
        return 'TODO';
    }

    public function setData($data): void
    {
        //if (null !== $this->parent)
        //    if ($this->isParentHigh)
        //        $this->
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getParent(): ?Register
    {
        return $this->parent;
    }

    public function setParent(?Register $parent): void
    {
        $this->parent = $parent;
    }

    /**
     * @return bool
     */
    public function isParentHigh(): bool
    {
        return $this->isParentHigh;
    }

    /**
     * @param bool $isParentHigh
     */
    public function setIsParentHigh(bool $isParentHigh = true): void
    {
        $this->isParentHigh = $isParentHigh;
    }
}
