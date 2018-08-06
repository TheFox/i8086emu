<?php

namespace TheFox\I8086emu\Blueprint;

interface DiskInterface
{
    public function setName(string $name): void;

    public function setFilePath(string $sourceFilePath): void;

    public function getContent(?int $length = null): \SplFixedArray;

    public function getFd();
}
