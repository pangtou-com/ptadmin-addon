<?php

declare(strict_types=1);

namespace PTAdmin\Addon\Contracts;

interface ArrayDataInterface
{
    public static function fromArray(array $data): ArrayDataInterface;

    public function toArray(): array;

    public function get(string $key, $default = null);

    public function with(array $data): self;
}
