<?php

namespace TheFox\I8086emu\Blueprint;

interface MachineInterface
{
    public function run();

    public function setBiosFilePath(string $biosFilePath);

    public function setFloppyDiskFilePath(string $filePath);

    public function setHardDiskFilePath(string $filePath);
}
