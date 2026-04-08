<?php

declare(strict_types=1);

namespace PTAdmin\Addon\Service;

class HookDefinition
{
    /** @var string */
    private $event;

    /** @var string */
    private $handler;

    /** @var int */
    private $priority = 0;

    private function __construct(string $event)
    {
        $this->event($event);
    }

    public static function make(string $event): self
    {
        return new self($event);
    }

    public function event(string $event): self
    {
        $this->event = trim($event);

        return $this;
    }

    public function handler(string $handler): self
    {
        $this->handler = trim($handler);

        return $this;
    }

    public function priority(int $priority): self
    {
        $this->priority = $priority;

        return $this;
    }

    public function toArray(): array
    {
        return [
            'event' => $this->event,
            'handler' => $this->handler,
            'priority' => $this->priority,
        ];
    }
}
