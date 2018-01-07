<?php

namespace TheFox\I8086emu\Blueprint;

interface MachineInterface
{
    public function run(): void;

    public function setBiosFilePath(string $biosFilePath): void;

    public function setFloppyDiskFilePath(string $filePath): void;

    public function setHardDiskFilePath(string $filePath): void;

    public function setGraphic(GraphicInterface $graphic): void;
}
