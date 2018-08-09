<?php

namespace TheFox\I8086emu\Blueprint;

interface AddressInterface
{
    public function setSize(int $size = 2): void;

    public function getSize(): int;

    public function getHalfSize(): int;

    public function getHalfBits(): int;

    public function toInt(): int;

    public function setData($data, bool $reset = true): void;

    public function getData(): \SplFixedArray;

    public function add(int $i): int;

    public function sub(int $i): int;

    public function setLowInt(int $low): int;

    public function getLowInt(): int;

    public function setHighInt(int $high): int;

    public function getHighInt(): int;

    public function getEffectiveHighInt(): int;
}
