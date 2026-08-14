<?php

declare(strict_types=1);

namespace PTAdmin\Addon\Contracts\Payment\Protocol;

use InvalidArgumentException;

final class PaymentInteractionResult implements \JsonSerializable
{
    /** @var string */ private $type;
    /** @var array<string, mixed> */ private $payload;

    private function __construct(string $type, array $payload)
    {
        $this->type = PaymentInteraction::assert($type, 'interaction');
        $this->payload = $payload;
        $this->validatePayload();
    }

    public static function make(string $type, array $payload = []): self
    {
        return new self($type, $payload);
    }

    public static function fromArray(array $data): self
    {
        return self::make((string) ($data['type'] ?? ''), (array) ($data['payload'] ?? []));
    }

    public function type(): string { return $this->type; }
    /** @return array<string, mixed> */ public function payload(): array { return $this->payload; }

    /** @return array{type:string, payload:array<string, mixed>} */
    public function toArray(): array
    {
        return ['type' => $this->type, 'payload' => $this->payload];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    private function validatePayload(): void
    {
        $this->assertNoExecutableContent($this->payload);

        if (PaymentInteraction::NONE === $this->type) {
            if ([] !== $this->payload) throw new InvalidArgumentException('none interaction payload must be empty.');
            return;
        }
        if (PaymentInteraction::QR_CODE === $this->type) {
            $this->requiredString('content');
            return;
        }
        if (PaymentInteraction::REDIRECT === $this->type) {
            $this->requiredUrl('url');
            return;
        }
        if (PaymentInteraction::FORM_SUBMIT === $this->type) {
            $this->requiredUrl('url');
            $method = strtoupper((string) ($this->payload['method'] ?? ''));
            if (!in_array($method, ['GET', 'POST'], true)) throw new InvalidArgumentException('form_submit method must be GET or POST.');
            if (!isset($this->payload['fields']) || !is_array($this->payload['fields'])) throw new InvalidArgumentException('form_submit fields are required.');
            foreach ($this->payload['fields'] as $value) if (!is_scalar($value) && null !== $value) throw new InvalidArgumentException('form_submit fields must contain scalar values.');
            return;
        }
        $this->requiredString('executor');
        $this->requiredString('version');
        if (!isset($this->payload['parameters']) || !is_array($this->payload['parameters'])) throw new InvalidArgumentException('client_invoke parameters are required.');
    }

    private function requiredString(string $key): void
    {
        if (!isset($this->payload[$key]) || !is_string($this->payload[$key]) || '' === trim($this->payload[$key])) throw new InvalidArgumentException(sprintf('Payment interaction payload [%s] is required.', $key));
    }

    private function requiredUrl(string $key): void
    {
        $this->requiredString($key);
        if (false === filter_var($this->payload[$key], FILTER_VALIDATE_URL)) throw new InvalidArgumentException('Payment interaction URL is invalid.');
    }

    /** @param mixed $value */
    private function assertNoExecutableContent($value): void
    {
        if (is_array($value)) {
            foreach ($value as $item) {
                $this->assertNoExecutableContent($item);
            }

            return;
        }
        if (!is_string($value)) {
            return;
        }
        if (preg_match('/<\/?[a-z!][^>]*>|<\?php|javascript:|\{!!|{{|@php\b/i', $value)) {
            throw new InvalidArgumentException('Payment interaction payload cannot contain HTML, Blade, PHP, or JavaScript.');
        }
    }
}
