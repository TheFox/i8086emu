<?php

/**
 * This is the class for managing and simulating the Random-Access-Memory.
 */

namespace TheFox\I8086emu\Machine;

use TheFox\I8086emu\Blueprint\AddressInterface;
use TheFox\I8086emu\Blueprint\RamInterface;
use TheFox\I8086emu\Blueprint\RegisterInterface;

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

        $pos = 0;

        // Write to RAM.
        for ($i = 0; $i < $writeLen; $i++) {
            $pos = $offset + $i;

            if (isset($byte[$i])) {
                $char = $byte[$i];
            } else {
                $char = "\x00";
            }

            if ('' === $char) {
                throw new \RuntimeException(sprintf('Cannot write empty character at %04x.', $pos));
            }

            $this->data[$pos] = $char;

            //printf("wram: %08x %02x\n", $pos, ord($char));
            //printf(" %02x", ord($char));
        }

        $this->writePointer = $pos + 1;
    }

    public function loadFromFile(string $path, int $offset = null, int $length = null)
    {
        $content = file_get_contents($path, false, null, 0, $length);
        $this->write($content, $offset, $length);
    }

    public function read(int $offset, int $length): string
    {
        $keys = array_keys($this->data);

        $minMemoryAddr = min($keys);
        if ($offset < $minMemoryAddr) {
            throw new \RangeException(sprintf('Out of range. Want to access %04x but minimum address is at %04x', $offset, $minMemoryAddr));
        }

        $maxMemoryAddr = max($keys);
        if ($offset > $maxMemoryAddr) {
            throw new \RangeException(sprintf('Out of range. Want to access %04x but maximum address is at %04x', $offset, $maxMemoryAddr));
        }

        //printf("rram: %08x %08x\n", $offset, $mm);

        $contentStr = '';
        for ($i = 0; $i < $length; $i++) {
            $pos = $offset + $i;
            $contentStr .= $this->data[$pos];
        }

        //$contentAr = array_slice($this->data, $offset, $length);
        //$contentStr = join('', $contentAr);
        return $contentStr;
    }

    public function readAddress(int $offset, int $length): AddressInterface
    {
        //if ($offset instanceof AddressInterface) {
        //    $offset = $offset->toInt();
        //}

        $data = $this->read($offset, $length);
        $address = new Address($data);
        return $address;
    }

    public function readFromRegister(RegisterInterface $register): string
    {
        $offset = $register->toInt();
        $length = $register->getSize();
        return $this->read($offset, $length);
    }
}
