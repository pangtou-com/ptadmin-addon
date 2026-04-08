<?php

declare(strict_types=1);

namespace PTAdmin\Addon\Support;

use PTAdmin\Addon\Contracts\ArrayDataInterface;

abstract class ArrayData implements ArrayDataInterface, \ArrayAccess, \JsonSerializable
{
    /** @var array */
    protected $attributes = [];

    final public function __construct(array $attributes = [])
    {
        $this->attributes = static::normalize($attributes);
    }

    final public static function fromArray(array $data): ArrayDataInterface
    {
        return new static($data);
    }

    final public function toArray(): array
    {
        return $this->attributes;
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function get(string $key, $default = null)
    {
        return $this->attributes[$key] ?? $default;
    }

    public function meta(): array
    {
        $meta = $this->get('meta', []);

        return \is_array($meta) ? $meta : [];
    }

    public function with(array $data): ArrayDataInterface
    {
        return new static(array_merge($this->attributes, $data));
    }

    public function offsetExists($offset): bool
    {
        return null !== $this->get((string) $offset);
    }

    public function offsetGet($offset)
    {
        return $this->get((string) $offset);
    }

    public function offsetSet($offset, $value): void
    {
        $attributes = $this->attributes;
        $attributes[(string) $offset] = $value;
        $this->attributes = static::normalize($attributes);
    }

    public function offsetUnset($offset): void
    {
        $attributes = $this->attributes;
        unset($attributes[(string) $offset]);
        $this->attributes = static::normalize($attributes);
    }

    abstract protected static function defaults(): array;

    protected static function normalize(array $data): array
    {
        $defaults = static::defaults();
        $known = array_intersect_key($data, $defaults);
        $attributes = array_merge($defaults, $known);

        if (array_key_exists('meta', $defaults)) {
            $extra = array_diff_key($data, $defaults);
            $meta = $attributes['meta'] ?? [];
            if (!\is_array($meta)) {
                $meta = [];
            }
            $attributes['meta'] = array_merge($meta, $extra);
        }

        return $attributes;
    }
}
