<?php

namespace TheFox\I8086emu\Components;

class Event
{
    /**
     * @var int
     */
    private $type;

    /**
     * @var \Closure
     */
    private $callback;

    public function __construct(int $type, \Closure $callback)
    {
        $this->type = $type;
        $this->callback = $callback;
    }

    /**
     * @return int
     */
    public function getType(): int
    {
        return $this->type;
    }

    public function exec(array $data)
    {
        $cbf = $this->callback;
        $rv = $cbf($data);
        return $rv;
    }
}
