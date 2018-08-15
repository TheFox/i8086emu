<?php

/**
 * @link https://en.wikipedia.org/wiki/FLAGS_register
 * @link https://en.wikipedia.org/wiki/Intel_8086#Flags
 */

namespace TheFox\I8086emu\Components;

use TheFox\I8086emu\Blueprint\FlagsInterface;

class Flags implements FlagsInterface
{
    public const FLAG_PF = 2;
    public const FLAG_ZF = 6;
    public const FLAG_SF = 7;
    public const FLAG_I = 9;
    public const FLAG_OF = 11;

    private const SIZE = 2;
    public const NAMES = [
        'CF' => 0, // carry flag
        'R1' => 1, // 1 reserved
        'PF' => 2, // parity flag
        'R3' => 3, // 3 reserved

        'AF' => 4, // auxiliary carry flag
        'R5' => 5, // 5 reserved
        'ZF' => 6, // zero flag
        'SF' => 7, // sign flag

        'TF' => 8, // trap flag
        'IF' => 9, // interrupt enable flag
        'DF' => 10, // direction flag
        'OF' => 11, // overflow flag

        'XF' => 12, // 12 reserved
        'R13' => 13, // 13 reserved
        'R14' => 14, // 14 reserved
        'R15' => 15, // 15 reserved
    ];

    /**
     * @var \SplFixedArray
     */
    private $data;

    /**
     * @var array
     */
    private $flippedNames;

    public function __construct()
    {
        /**
         * @link https://en.wikipedia.org/wiki/FLAGS_register
         * Hint for reserved FLAGs 1 and 12 to 15:
         * Regarding to Wikipedia these flags should always be `1`.
         * But since we are using FLAG 12 as a always-zero FLAG we
         * have to set FLAG 12 to `0` here.
         *
         * Always-Zero FLAG is used to calculate JMP.
         */
        $this->data = \SplFixedArray::fromArray([
            false, // carry flag
            true, // 1 reserved
            false, // parity flag
            false, // 3 reserved
            false, // auxiliary carry flag
            false, // 5 reserved
            false, // zero flag
            false, // sign flag
            false, // trap flag
            false, // interrupt enable flag
            false, // direction flag
            false, // overflow flag

            false, // 12 reserved
            true, // 13 reserved
            true, // 14 reserved
            true, // 15 reserved
        ]);

        $this->flippedNames = array_flip(self::NAMES);
    }

    public function __toString(): string
    {
        $a = $this->data->toArray();
        $a = array_reverse($a);
        $n = array_map(function ($f) {
            return $f ? '1' : 0;
        }, $a);
        $s = join('', $n);
        $s = sprintf('FLAGS[%s,%04x]', $s, $this->toInt());
        return $s;
    }

    public function getSize(): int
    {
        return self::SIZE;
    }

    public function set(int $flagId, bool $val): void
    {
        /**
         * @link https://en.wikipedia.org/wiki/FLAGS_register
         */
        if (1 === $flagId || $flagId >= 13) {
            $val = true;
        }
        if (12 === $flagId) {
            throw new \RuntimeException('Since we use FLAG 12 as Always-Zero FLAG we cannot override it.');
        }
        $this->data[$flagId] = $val;
    }

    public function setByName(string $name, bool $val): void
    {
        $this->set(self::NAMES[$name], $val);
    }

    public function get(int $flagId): bool
    {
        $f = boolval($this->data[$flagId]);
        return $f;
    }

    public function getByName(string $name): bool
    {
        return $this->get(self::NAMES[$name]);
    }

    public function getName(int $flagId): string
    {
        return $this->flippedNames[$flagId];
    }

    public function setIntData(int $data): void
    {
        foreach ($this->data as $i => $f) {
            if (12 !== $i) {
                $this->set($i, $data & 1);
            }
            $data >>= 1;
        }
    }

    public function setData(iterable $data): void
    {
        $n = ($data[1] << 8) | $data[0];
        $this->setIntData($n);
    }

    public function getData(): \SplFixedArray
    {
        $data = new \SplFixedArray(self::SIZE);

        for ($j = 0; $j < 2; ++$j) {
            $data[$j] = 0;
            for ($i = 0; $i < 8; ++$i) {
                $n = $this->data[$j * 8 + $i] << $i;
                $data[$j] |= $n;
            }
        }

        return $data;
    }

    public function getStandardizedData(): \SplFixedArray
    {
        $data = $this->getData();
        $data[1] |= 0xF0;
        $data[0] |= 0x2;
        return $data;
    }

    public function toInt(): int
    {
        $i = 0;
        $bits = 0;
        foreach ($this->data as $f) {
            $i |= $f << $bits;
            ++$bits;
        }
        return $i;
    }
}
