<?php

namespace TheFox\I8086emu\Machine;

use TheFox\I8086emu\Blueprint\RamInterface;

class Ram implements RamInterface
{
    /**
     * @var int
     */
    private $writePointer;

    /**
     * @var array
     */
    private $data;

    public function __construct()
    {
        $this->writePointer = 0;
    }

    public function write(string $byte, int $offset = null)
    {
        if (null === $offset) {
            $offset = $this->writePointer;
        }

        $byteLen = strlen($byte);
        for ($i = 0; $i < $byteLen; $i++, $offset++) {
            $this->data[$offset] = $byte[$i];
        }

        $this->writePointer = $offset;
    }

    public function read(int $offset, int $length)
    {
        $contentAr = array_slice($this->data, $offset, $length);
        $contentStr = join('', $contentAr);
        return $contentStr;
    }

    public function loadFile(string $path, int $offset = null)
    {
        $content = file_get_contents($path);
        $this->write($content, $offset);
    }
}
