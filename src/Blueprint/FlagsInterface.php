<?php

namespace TheFox\I8086emu\Blueprint;

interface FlagsInterface
{
    public function getSize(): int;

    public function set(int $flagId, bool $val);

    public function setByName(string $name, bool $val);

    public function get(int $flagId): bool;

    public function getByName(string $name): bool;

    public function getName(int $flagId);

    public function getData(): \SplFixedArray;
}
