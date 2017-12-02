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
        $this->data = [];
    }

    public function write(string $byte, int $offset = null, int $length = null)
    {
        if (null === $offset) {
            $offset = $this->writePointer;
        }

        if (null === $length) {
            $writeLen = strlen($byte);
        } else {
            $writeLen = $length;
        }

        // Write to RAM.
        for ($i = 0; $i < $writeLen; $i++, $offset++) {
            if (isset($byte[$i])) {
                $char = $byte[$i];
            } else {
                $char = "\x00";
            }

            $this->data[$offset] = $char;
        }

        $this->writePointer = $offset;
    }

    public function read(int $offset, int $length)
    {
        $contentAr = array_slice($this->data, $offset, $length);
        $contentStr = join('', $contentAr);
        return $contentStr;
    }

    public function loadFile(string $path, int $offset = null, int $length = null)
    {
        $content = file_get_contents($path, false, null, 0, $length);
        $this->write($content, $offset, $length);
    }
}
