<?php

declare(strict_types=1);

namespace PTAdmin\Addon\Contracts\Payment\Protocol;

use InvalidArgumentException;

final class PaymentExecutor implements \JsonSerializable
{
    /** @var string */
    private $code;

    /** @var string */
    private $version;

    private function __construct(string $code, string $version)
    {
        if (1 !== preg_match('/\A[a-z][a-z0-9._-]{0,99}\z/', $code)) {
            throw new InvalidArgumentException('Payment executor code is invalid.');
        }
        if ('' === trim($version)) {
            throw new InvalidArgumentException('Payment executor version is required.');
        }
        $this->code = $code;
        $this->version = trim($version);
    }

    public static function make(string $code, string $version): self
    {
        return new self($code, $version);
    }

    public static function fromArray(array $data): self
    {
        return self::make((string) ($data['executor'] ?? $data['code'] ?? ''), (string) ($data['version'] ?? ''));
    }

    public function code(): string
    {
        return $this->code;
    }

    public function version(): string
    {
        return $this->version;
    }

    /** @return array{executor:string, version:string} */
    public function toArray(): array
    {
        return ['executor' => $this->code, 'version' => $this->version];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
