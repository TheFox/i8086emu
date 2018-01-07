<?php

/**
 * Create a TTY using socat.
 */

namespace TheFox\I8086emu\Machine;

final class TtyGraphic extends Graphic
{
    /**
     * @var string
     */
    private $ttyFilePath;

    /**
     * @var string
     */
    private $socatFilePath;

    public function __construct()
    {
        $this->socatFilePath = 'socat';
    }

    /**
     * @param string $ttyFilePath
     */
    public function setTtyFilePath(string $ttyFilePath): void
    {
        $this->ttyFilePath = $ttyFilePath;
    }

    /**
     * @param string $socatFilePath
     */
    public function setSocatFilePath(string $socatFilePath): void
    {
        $this->socatFilePath = $socatFilePath;
    }
}
