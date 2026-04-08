<?php

declare(strict_types=1);

namespace PTAdmin\Addon\Service;

class InjectDefinition
{
    /** @var string */
    private $code;

    /** @var string|null */
    private $title;

    /** @var array */
    private $types = [];

    /** @var string */
    private $handler;

    private function __construct(string $code)
    {
        $this->code($code);
    }

    public static function make(string $code): self
    {
        return new self($code);
    }

    public function code(string $code): self
    {
        $this->code = trim($code);

        return $this;
    }

    public function title(string $title): self
    {
        $this->title = trim($title);

        return $this;
    }

    public function types(array $types): self
    {
        $this->types = array_values($types);

        return $this;
    }

    public function handler(string $handler): self
    {
        $this->handler = trim($handler);

        return $this;
    }

    public function toArray(): array
    {
        $result = [
            'code' => $this->code,
            'type' => $this->types,
            'class' => $this->handler,
        ];
        if (!blank($this->title)) {
            $result['title'] = $this->title;
        }

        return $result;
    }
}
