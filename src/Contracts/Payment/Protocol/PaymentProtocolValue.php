<?php

declare(strict_types=1);

namespace PTAdmin\Addon\Contracts\Payment\Protocol;

use InvalidArgumentException;

abstract class PaymentProtocolValue
{
    /** @return array<int, string> */
    abstract public static function values(): array;

    public static function isValid(string $value): bool
    {
        return in_array($value, static::values(), true);
    }

    public static function assert(string $value, string $field = 'value'): string
    {
        if (!static::isValid($value)) {
            throw new InvalidArgumentException(sprintf('Payment protocol %s [%s] is not supported.', $field, $value));
        }

        return $value;
    }
}
