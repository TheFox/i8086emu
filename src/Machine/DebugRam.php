<?php

namespace TheFox\I8086emu\Machine;

use TheFox\I8086emu\Components\Event;

class DebugRam extends Ram
{
    public const EVENT_WRITE_PRE = 100;
    public const EVENT_WRITE_POST = 150;
    public const EVENT_READ_PRE = 200;
    public const EVENT_READ_POST = 250;

    /**
     * @var array
     */
    private $events;

    public function __construct(int $size = 0x10000)
    {
        parent::__construct($size);

        $this->events = [];
    }

    public function write($data, int $offset, int $length): void
    {
        $eventData = [
            'data' => $data,
            'offset' => $offset,
            'length' => $length,
        ];
        $this->execEvents(self::EVENT_WRITE_PRE, $eventData);

        parent::write($data, $offset, $length);

        $this->execEvents(self::EVENT_WRITE_POST, $eventData);
    }

    public function read(int $offset, int $length): \SplFixedArray
    {
        $eventData = [
            'offset' => $offset,
            'length' => $length,
        ];
        $this->execEvents(self::EVENT_READ_PRE,$eventData);

        $rv = parent::read($offset, $length);

        $this->execEvents(self::EVENT_READ_POST,$eventData);

        return $rv;
    }

    public function addEvent(Event $event)
    {
        $type = $event->getType();
        if (array_key_exists($type, $this->events)) {
            $this->events[$type][] = $event;
        } else {
            $this->events[$type] = [$event];
        }
    }

    private function execEvents(int $type, array $data = [])
    {
        if (!array_key_exists($type, $this->events)) {
            return;
        }

        /** @var Event[] $events */
        $events = $this->events[$type];
        foreach ($events as $event) {
            $event->exec($data);
        }
    }
}
