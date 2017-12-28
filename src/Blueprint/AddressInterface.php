<?php

namespace TheFox\I8086emu\Blueprint;

interface AddressInterface
{
    public function toInt(): int;

    public function setData($data, bool $reset = true): void;

    public function getData(): \SplFixedArray;

    public function add(int $i): void;

    public function getLowInt(): int;

    public function getHighInt(): int;

    public function getEffectiveHighInt(): int;
}
