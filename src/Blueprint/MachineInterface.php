<?php

namespace TheFox\I8086emu\Blueprint;

use TheFox\I8086emu\Machine\OutputDevice;

interface MachineInterface
{
    public function run(): void;

    public function setTty(OutputDeviceInterface $tty): void;

    public function getDiskByNum(int $diskId): DiskInterface;

    public function getTty(): ?OutputDeviceInterface;
}
