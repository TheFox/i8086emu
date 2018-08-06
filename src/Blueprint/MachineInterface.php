<?php

namespace TheFox\I8086emu\Blueprint;

interface MachineInterface
{
    public function run(): void;

    public function setTty(OutputDeviceInterface $tty): void;

    public function getDiskByNum(int $diskId):DiskInterface;
}
