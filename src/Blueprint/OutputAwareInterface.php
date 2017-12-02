<?php

namespace TheFox\I8086emu\Blueprint;

use Symfony\Component\Console\Output\OutputInterface;

interface OutputAwareInterface
{
    public function setOutput(OutputInterface $output);
}
