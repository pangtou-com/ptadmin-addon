<?php

declare(strict_types=1);

namespace PTAdmin\Addon\Contracts\Payment\Protocol;

use InvalidArgumentException;

final class PaymentRequirements
{
    /** @var string|null */
    private $target;
    /** @var array<int, string> */
    private $scenes = [];
    /** @var array<int, string> */
    private $interactions = [];
    /** @var array<int, string> */
    private $operations = [];
    /** @var string|null */
    private $currency;
    /** @var string|null */
    private $profileCode;

    public static function make(): self
    {
        return new self();
    }

    public function target(?string $target): self
    {
        $this->target = null === $target || '' === trim($target) ? null : PaymentTarget::assert(trim($target), 'target');

        return $this;
    }

    public function scenes(array $scenes): self
    {
        $this->scenes = $this->validatedList($scenes, PaymentScene::class, 'scene');

        return $this;
    }

    public function interactions(array $interactions): self
    {
        $this->interactions = $this->validatedList($interactions, PaymentInteraction::class, 'interaction');

        return $this;
    }

    public function operations(array $operations): self
    {
        $this->operations = $this->validatedList($operations, PaymentOperation::class, 'operation');

        return $this;
    }

    public function currency(?string $currency): self
    {
        if (null !== $currency && !preg_match('/\A[A-Z]{3}\z/', $currency)) {
            throw new InvalidArgumentException('Payment currency must be an uppercase ISO 4217 code.');
        }
        $this->currency = $currency;

        return $this;
    }

    public function profileCode(?string $profileCode): self
    {
        if (null !== $profileCode && '' !== trim($profileCode) && 1 !== preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9._-]{0,99}\z/', trim($profileCode))) {
            throw new InvalidArgumentException('Payment profile code is invalid.');
        }
        $this->profileCode = null === $profileCode || '' === trim($profileCode) ? null : trim($profileCode);

        return $this;
    }

    public function targetValue(): ?string { return $this->target; }
    /** @return array<int, string> */
    public function sceneValues(): array { return $this->scenes; }
    /** @return array<int, string> */
    public function interactionValues(): array { return $this->interactions; }
    /** @return array<int, string> */
    public function operationValues(): array { return $this->operations; }
    public function currencyValue(): ?string { return $this->currency; }
    public function profileValue(): ?string { return $this->profileCode; }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'target' => $this->target,
            'scenes' => $this->scenes,
            'interactions' => $this->interactions,
            'operations' => $this->operations,
            'currency' => $this->currency,
            'profile_code' => $this->profileCode,
        ];
    }

    /** @param array<int, mixed> $values */
    private function validatedList(array $values, string $class, string $field): array
    {
        $result = [];
        foreach ($values as $value) {
            if (!is_string($value)) {
                throw new InvalidArgumentException(sprintf('Payment protocol %s must contain strings.', $field));
            }
            $result[] = $class::assert($value, $field);
        }

        return array_values(array_unique($result));
    }
}
