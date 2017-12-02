<?php

namespace TheFox\I8086emu\Blueprint;

interface RegisterInterface
{
    public function getSize(): ?int;
    public function getName(): ?string;

    public function setData(string $data);
    public function getData(): ?string;

    public function setLow(string $low);
    public function getLow(): ?string;

    public function setHigh(string $low);
    public function getHigh(): ?string;
}
