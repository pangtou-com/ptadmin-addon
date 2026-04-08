<?php

declare(strict_types=1);

namespace PTAdmin\Addon\Service;

use Illuminate\Support\Str;

class HookPayload implements \ArrayAccess
{
    /** @var array */
    private $payload;

    private function __construct(array $payload = [])
    {
        $this->payload = $payload;
    }

    public static function make(array $payload = []): self
    {
        return new self($payload);
    }

    public function __call($name, $arguments)
    {
        $name = lcfirst(Str::afterLast($name, 'get'));

        return $this->get($name, $arguments[0] ?? null);
    }

    public function get(string $key, $default = null)
    {
        return $this->payload[$key] ?? $default;
    }

    public function set(string $key, $value): self
    {
        $this->payload[$key] = $value;

        return $this;
    }

    public function all(): array
    {
        return $this->payload;
    }

    public function offsetExists($offset): bool
    {
        return null !== $this->get($offset);
    }

    public function offsetGet($offset)
    {
        return $this->get($offset);
    }

    public function offsetSet($offset, $value): void
    {
        $this->set((string) $offset, $value);
    }

    public function offsetUnset($offset): void
    {
        unset($this->payload[$offset]);
    }
}
