<?php

declare(strict_types=1);

namespace PTAdmin\Addon\Contracts\Payment\Protocol;

use InvalidArgumentException;

final class PaymentReadinessResult implements \JsonSerializable
{
    /** @var bool */ private $ready;
    /** @var string|null */ private $reasonCode;
    /** @var string|null */ private $message;

    private function __construct(bool $ready, ?string $reasonCode, ?string $message)
    {
        $this->ready = $ready;
        $this->reasonCode = $ready ? null : $reasonCode;
        $this->message = $message;
    }

    public static function ready(?string $message = null): self
    {
        return new self(true, null, $message);
    }

    public static function notReady(string $reasonCode, string $message): self
    {
        if (1 !== preg_match('/\A[a-z][a-z0-9_]{0,99}\z/', $reasonCode)) {
            throw new InvalidArgumentException('Payment readiness reason code is invalid.');
        }

        return new self(false, trim($reasonCode), $message);
    }

    public static function fromArray(array $data): self
    {
        return true === ($data['ready'] ?? false) ? self::ready(isset($data['message']) ? (string) $data['message'] : null) : self::notReady((string) ($data['reason_code'] ?? 'not_ready'), (string) ($data['message'] ?? 'Payment capability is not ready.'));
    }

    public function isReady(): bool { return $this->ready; }
    public function reasonCode(): ?string { return $this->reasonCode; }
    public function message(): ?string { return $this->message; }

    /** @return array{ready:bool, reason_code:string|null, message:string|null} */
    public function toArray(): array
    {
        return ['ready' => $this->ready, 'reason_code' => $this->reasonCode, 'message' => $this->message];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
