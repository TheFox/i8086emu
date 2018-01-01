<?php

namespace TheFox\I8086emu\Components;

use TheFox\I8086emu\Blueprint\ChildRegisterInterface;

final class ChildRegister extends Register implements ChildRegisterInterface
{
    /**
     * @var Register
     */
    private $parent;

    /**
     * @var bool
     */
    private $isParentHigh;

    public function __construct(Register $parent, bool $isParentHigh = false, ?string $name = null)
    {
        $this->isParentHigh = $isParentHigh;
        $size = $parent->getHalfSize(); // We take it from parent.
        $data = null;

        parent::__construct($size, $data, $name);

        $this->parent = $parent;
    }

    public function setParent(Register $parent): void
    {
        $this->parent = $parent;
        $this->setSize($this->getHalfSize());
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

    public function setData($data, bool $reset = true): void
    {
        if (null === $this->parent) {
            // Skip setData while __construct().
            return;
        }

        if (is_iterable($data)) {
            $data = $data[0];
        }
        //if (!is_numeric($data)) {
        //    throw new \RuntimeException('Only numeric supported.');
        //}
        $data = intval($data);

        $data &= 0xFF;
        if ($this->isParentHigh) {
            $low = $this->parent->getLowInt();
            $bits = $this->parent->getHalfBits();
            $high = $data << $bits;
        } else {
            $low = $data;
            $high = $this->parent->getEffectiveHighInt();
        }
        $newData = $high | $low;

        $this->parent->setData($newData);
    }

    /**
     * @return \SplFixedArray
     */
    public function getData(): \SplFixedArray
    {
        $halfSize = $this->getHalfSize();
        $dataArray = $this->parent->getData()->toArray();

        if ($this->isParentHigh) {
            $data = array_slice($dataArray, $halfSize);
        } else {
            $data = array_slice($dataArray, 0, $halfSize);
        }

        return \SplFixedArray::fromArray($data);
    }

    /**
     * @return int
     */
    public function toInt(): int
    {
        if ($this->isParentHigh) {
            return $this->parent->getHighInt();
        }

        return $this->parent->getLowInt();
    }

    public function add(int $i): int
    {
        $endVal = $this->toInt() + $i;

        if ($this->isParentHigh) {
            return $this->parent->setHighInt($endVal);
        }

        return $this->parent->setLowInt($endVal);
    }
}
