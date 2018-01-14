<?php

namespace TheFox\I8086emu\Machine;

use TheFox\I8086emu\Blueprint\DiskInterface;

class Disk implements DiskInterface
{
    private $sourceFilePath;

    public function setSourceFilePath(string $sourceFilePath): void
    {
        $this->sourceFilePath = $sourceFilePath;
    }

    public function getContent(?int $length = null): \SplFixedArray
    {
        $content = file_get_contents($this->sourceFilePath, false, null, 0, $length);
        $data = str_split($content);
        unset($content);
        $data = array_map('ord', $data);
        $data = \SplFixedArray::fromArray($data);
        return $data;
    }
}
