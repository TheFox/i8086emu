<?php

/**
 * Machine create RAM, CPU, etc. and connects the components.
 */

namespace TheFox\I8086emu\Machine;

use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use TheFox\I8086emu\Blueprint\CpuInterface;
use TheFox\I8086emu\Blueprint\GraphicInterface;
use TheFox\I8086emu\Blueprint\MachineInterface;
use TheFox\I8086emu\Blueprint\OutputAwareInterface;
use TheFox\I8086emu\Blueprint\RamInterface;
use TheFox\I8086emu\Exception\NoBiosException;
use TheFox\I8086emu\Exception\NoCpuException;
use TheFox\I8086emu\Exception\NoRamException;

final class Machine implements MachineInterface, OutputAwareInterface
{
    /**
     * @var string
     */
    private $biosFilePath;

    /**
     * @var Disk
     */
    private $bios;

    /**
     * @var string
     */
    private $floppyDiskFilePath;

    /**
     * @var Disk
     */
    private $floppyDisk;

    /**
     * @var string
     */
    private $hardDiskFilePath;

    /**
     * @var Disk
     */
    private $hardDisk;

    /**
     * @var Ram
     */
    private $ram;

    /**
     * @var Cpu
     */
    private $cpu;

    /**
     * @var Graphic
     */
    private $graphic;

    /**
     * @var OutputInterface
     */
    private $output;

    public function __construct()
    {
        $this->ram = new Ram(0x00100000); // 1 MB
        $this->cpu = new Cpu();
        $this->graphic = new NullGraphic();

        $this->output = new NullOutput();
    }

    public function run(): void
    {
        if (!$this->ram || !$this->ram instanceof RamInterface) {
            throw new NoRamException();
        }

        if (!$this->cpu || !$this->cpu instanceof CpuInterface) {
            throw new NoCpuException();
        }

        if (!$this->biosFilePath) {
            throw new NoBiosException();
        }

        // Load BIOS into RAM.
        $biosOffset = (0xF000 << 4) + 0x0100;
        $biosLen = 0xFF00;
        $this->output->writeln(sprintf("bios start %08x", $biosOffset));
        $this->output->writeln(sprintf("bios end   %08x", $biosOffset + $biosLen));
        $this->writeRamFromFile($this->biosFilePath, $biosOffset, $biosLen);

        // Setup CPU.
        $this->output->writeln('set ram');
        $this->cpu->setRam($this->ram);

        // Setup Graphic.
        $this->cpu->setGraphic($this->graphic);

        // Run the CPU.
        $this->cpu->run();
    }

    /**
     * @deprecated
     * @param string $biosFilePath
     */
    public function setBiosFilePath(string $biosFilePath): void
    {
        $this->biosFilePath = $biosFilePath;
    }

    /**
     * @param Disk $bios
     */
    public function setBios(Disk $bios): void
    {
        $this->bios = $bios;
    }

    /**
     * @deprecated
     * @param string $filePath
     */
    public function setFloppyDiskFilePath(string $filePath): void
    {
        $this->floppyDiskFilePath = $filePath;
    }

    /**
     * @param Disk $floppyDisk
     */
    public function setFloppyDisk(Disk $floppyDisk): void
    {
        $this->floppyDisk = $floppyDisk;
    }

    /**
     * @deprecated
     * @param string $filePath
     */
    public function setHardDiskFilePath(string $filePath): void
    {
        $this->hardDiskFilePath = $filePath;
    }

    /**
     * @param Disk $hardDisk
     */
    public function setHardDisk(Disk $hardDisk): void
    {
        $this->hardDisk = $hardDisk;
    }

    /**
     * @param GraphicInterface $graphic
     */
    public function setGraphic(GraphicInterface $graphic): void
    {
        $this->graphic = $graphic;
    }

    /**
     * @param OutputInterface $output
     */
    public function setOutput(OutputInterface $output): void
    {
        $this->output = $output;

        $this->cpu->setOutput($this->output);
    }

    private function writeRamFromFile(string $path, int $offset, int $length): void
    {
        $content = file_get_contents($path, false, null, 0, $length);
        $data = str_split($content);
        $data = array_map('ord', $data);
        $this->ram->write($data, $offset, $length);
    }
}
