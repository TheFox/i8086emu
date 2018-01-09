<?php

namespace TheFox\I8086emu\Machine;

class Disk implements DiskInterface{

private $sourceFilePath;

public function setSourceFilePath(string $sourceFilePath){
    $this->sourceFilePath=$sourceFilePath;
}
}
