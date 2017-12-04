<?php

namespace TheFox\I8086emu\Blueprint;

interface RegisterInterface
{
    public function setData(string $data);
    public function getData(): ?string;

    public function setLow(string $low);
    public function getLow(): ?string;

    public function setHigh(string $low);
    public function getHigh(): ?string;

    public function getSize(): int;

    public function toInt(): int;
}
