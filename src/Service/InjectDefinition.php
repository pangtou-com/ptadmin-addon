<?php

declare(strict_types=1);

namespace PTAdmin\Addon\Service;

use InvalidArgumentException;
use PTAdmin\Addon\Contracts\Payment\Protocol\PaymentDefinition;

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

    /** @var PaymentDefinition|null */
    private $paymentDefinition;

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
        if (null !== $this->paymentDefinition && [] !== $types) {
            throw new InvalidArgumentException('Payment scenes must be declared through PaymentDefinition, not types().');
        }
        $this->types = array_values($types);

        return $this;
    }

    public function handler(string $handler): self
    {
        $this->handler = trim($handler);

        return $this;
    }

    public function paymentDefinition(PaymentDefinition $definition): self
    {
        if ($definition->code() !== $this->code) {
            throw new InvalidArgumentException('Payment definition code must match the inject definition code.');
        }
        if ([] !== $this->types) {
            throw new InvalidArgumentException('Payment scenes must be declared through PaymentDefinition, not types().');
        }
        $this->paymentDefinition = $definition;

        return $this;
    }

    public function toArray(): array
    {
        $result = [
            'code' => $this->code,
            'class' => $this->handler,
        ];
        if (null === $this->paymentDefinition) {
            $result['type'] = $this->types;
        }
        if (!blank($this->title)) {
            $result['title'] = $this->title;
        }
        if (null !== $this->paymentDefinition) {
            $result['payment_definition'] = $this->paymentDefinition->toArray();
        }

        return $result;
    }
}
