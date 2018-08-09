<?php

/**
 * Create a TTY using socat.
 */

namespace TheFox\I8086emu\Machine;

final class TtyOutputDevice extends OutputDevice
{
    /**
     * @var string
     */
    private $ttyFilePath;

    /**
     * @var string
     */
    private $socatFilePath;

    /**
     * @var string
     */
    private $cmd;

    /**
     * @var array
     */
    private $fileHandles;

    /**
     * @var array
     */
    private $pipes;

    /**
     * @var bool
     */
    private $isRunning;

    /**
     * @var resource
     */
    private $process;

    /**
     * @var string
     */
    private $outputBuffer;

    public function __construct()
    {
        $this->socatFilePath = 'socat';
        $this->fileHandles = [
            0 => ['pipe', 'rb'], // STDIN
            1 => ['pipe', 'wb'], // STDOUT
            2 => ['file', '/tmp/error.log', 'wb'], // STDERR
        ];
        $this->pipes = [];
        $this->isRunning = false;
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

    private function start()
    {
        if (!$this->isRunning) {
            $this->process = proc_open($this->cmd, $this->fileHandles, $this->pipes);
            $this->isRunning = true;
        }
    }

    public function init(): void
    {
        if (null === $this->ttyFilePath) {
            return;
        }

        $this->cmd = sprintf('%s PTY,link=%s,rawer,wait-slave STDIO', $this->socatFilePath, $this->ttyFilePath);
        $this->pipes = [];
        $this->start();
    }

    public function run(): void
    {
        $this->process();
    }

    private function process(): void
    {
        // Write
        $this->write();

        // Read
        $this->read();
    }

    private function write()
    {
        if (!$this->outputBuffer) {
            return;
        }

        $tmpOut = $this->outputBuffer;
        $this->outputBuffer = null;
        fwrite($this->pipes[0], $tmpOut);
        fflush($this->pipes[0]);
    }

    private function read()
    {
        $readHandles = [$this->pipes[1]];
        $writeHandles = [];
        $exceptHandles = [];
        $handlesChanged = stream_select($readHandles, $writeHandles, $exceptHandles, 0);
        printf("streams: %d\n", $handlesChanged);

        if (!$handlesChanged) {
            return;
        }

        printf("changed streams: %d %d\n", count($readHandles), count($writeHandles));

        $buffer = [];
        foreach ($readHandles as $readableHandle) {
            printf(" -> readable\n");
            //$data=stream_socket_recvfrom($readableHandle, 2048);
            $maxLen = 2048;
            $data = fread($readableHandle, 2048);

            for ($i = 0; $i < $maxLen; ++$i) {
                if (!isset($data[$i])) {
                    printf(" -> break\n");
                    break;
                }
                $c = $data[$i];
                printf(" -> 0x%x\n", ord($c));
                $buffer[] = $c;
            }
        }
        // @todo process $buffer. CPU needs it. but how?
    }

    public function putChar(string $char): void
    {
        $this->outputBuffer .= $char;
    }
}
