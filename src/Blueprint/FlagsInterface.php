<?php

namespace TheFox\I8086emu\Blueprint;

interface FlagsInterface
{
    public function getSize(): int;

    public function set(int $flagId, bool $val): void;

    public function setByName(string $name, bool $val): void;

    public function get(int $flagId): bool;

    public function getByName(string $name): bool;

    public function getName(int $flagId): string;

    public function getData(): \SplFixedArray;
}
