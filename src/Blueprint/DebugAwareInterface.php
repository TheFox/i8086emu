<?php

namespace TheFox\I8086emu\Blueprint;

use Symfony\Component\Console\Output\OutputInterface;

interface DebugAwareInterface
{
    public function setOutput(OutputInterface $output): void;
}
