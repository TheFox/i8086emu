<?php

namespace TheFox\I8086emu\Machine;

use TheFox\I8086emu\Blueprint\DiskInterface;

class Disk implements DiskInterface
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var string
     */
    private $sourceFilePath;

    public function __construct(?string $name = null)
    {
        $this->name = $name;
    }

    public function __toString()
    {
        if ($this->name) {
            return sprintf('DISK[%s]', $this->name);
        }
        return 'DISK';
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

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
