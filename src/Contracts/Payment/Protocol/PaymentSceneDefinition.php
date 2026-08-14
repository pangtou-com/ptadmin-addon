<?php

declare(strict_types=1);

namespace PTAdmin\Addon\Contracts\Payment\Protocol;

use InvalidArgumentException;

final class PaymentSceneDefinition
{
    /** @var string */ private $scene;
    /** @var array<int, string> */ private $targets;
    /** @var array<int, string> */ private $interactions;
    /** @var array<int, string> */ private $operations;
    /** @var array<int, string> */ private $requiredInputs;
    /** @var array<int, string> */ private $currencies;
    /** @var array<int, PaymentExecutor> */ private $executors;

    private function __construct(string $scene, array $targets, array $interactions, array $operations, array $requiredInputs, array $currencies, array $executors)
    {
        $this->scene = PaymentScene::assert($scene, 'scene');
        $this->targets = $this->list($targets, PaymentTarget::class, 'target');
        $this->interactions = $this->list($interactions, PaymentInteraction::class, 'interaction');
        $this->operations = $this->list($operations, PaymentOperation::class, 'operation');
        $this->requiredInputs = $this->list($requiredInputs, PaymentInput::class, 'required_input');
        if ([] === $this->targets || [] === $this->interactions || [] === $this->operations) {
            throw new InvalidArgumentException('Payment scene targets, interactions, and operations cannot be empty.');
        }
        $this->currencies = [];
        foreach ($currencies as $currency) {
            if (!is_string($currency) || !preg_match('/\A[A-Z]{3}\z/', $currency)) {
                throw new InvalidArgumentException('Payment currencies must be uppercase ISO 4217 codes.');
            }
            $this->currencies[] = $currency;
        }
        $this->currencies = array_values(array_unique($this->currencies));
        $this->executors = [];
        foreach ($executors as $executor) {
            $this->executors[] = $executor instanceof PaymentExecutor ? $executor : PaymentExecutor::fromArray((array) $executor);
        }
        if (in_array(PaymentInteraction::CLIENT_INVOKE, $this->interactions, true) && [] === $this->executors) {
            throw new InvalidArgumentException('client_invoke requires at least one executor declaration.');
        }
    }

    public static function make(string $scene, array $targets, array $interactions, array $operations, array $requiredInputs = [], array $currencies = [], array $executors = []): self
    {
        return new self($scene, $targets, $interactions, $operations, $requiredInputs, $currencies, $executors);
    }

    public static function fromArray(array $data): self
    {
        return self::make((string) ($data['scene'] ?? ''), (array) ($data['targets'] ?? []), (array) ($data['interactions'] ?? []), (array) ($data['operations'] ?? []), (array) ($data['required_inputs'] ?? []), (array) ($data['currencies'] ?? []), (array) ($data['executors'] ?? []));
    }

    public function scene(): string { return $this->scene; }
    /** @return array<int, string> */ public function targets(): array { return $this->targets; }
    /** @return array<int, string> */ public function interactions(): array { return $this->interactions; }
    /** @return array<int, string> */ public function operations(): array { return $this->operations; }
    /** @return array<int, string> */ public function requiredInputs(): array { return $this->requiredInputs; }
    /** @return array<int, string> */ public function currencies(): array { return $this->currencies; }
    /** @return array<int, PaymentExecutor> */ public function executors(): array { return $this->executors; }

    public function matches(PaymentRequirements $requirements): bool
    {
        if (null !== $requirements->targetValue() && !in_array($requirements->targetValue(), $this->targets, true)) return false;
        if ([] !== $requirements->sceneValues() && !in_array($this->scene, $requirements->sceneValues(), true)) return false;
        if ([] !== $requirements->interactionValues() && [] === array_intersect($requirements->interactionValues(), $this->interactions)) return false;
        if ([] !== $requirements->operationValues() && [] !== array_diff($requirements->operationValues(), $this->operations)) return false;
        if (null !== $requirements->currencyValue() && [] !== $this->currencies && !in_array($requirements->currencyValue(), $this->currencies, true)) return false;

        return true;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['scene' => $this->scene, 'targets' => $this->targets, 'interactions' => $this->interactions, 'operations' => $this->operations, 'required_inputs' => $this->requiredInputs, 'currencies' => $this->currencies, 'executors' => array_map(static function (PaymentExecutor $executor): array { return $executor->toArray(); }, $this->executors)];
    }

    private function list(array $values, string $class, string $field): array
    {
        $result = [];
        foreach ($values as $value) {
            if (!is_string($value)) throw new InvalidArgumentException(sprintf('Payment protocol %s must contain strings.', $field));
            $result[] = $class::assert($value, $field);
        }
        return array_values(array_unique($result));
    }
}
