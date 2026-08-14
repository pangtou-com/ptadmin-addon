<?php

declare(strict_types=1);

namespace PTAdmin\Addon\Service;

use InvalidArgumentException;
use PTAdmin\Addon\Contracts\Payment\PaymentReadinessInterface;
use PTAdmin\Addon\Contracts\Payment\Protocol\PaymentCapabilityReference;
use PTAdmin\Addon\Contracts\Payment\Protocol\PaymentDefinition;
use PTAdmin\Addon\Contracts\Payment\Protocol\PaymentReadinessResult;
use PTAdmin\Addon\Contracts\Payment\Protocol\PaymentRequirements;
use PTAdmin\Addon\Contracts\Payment\Protocol\PaymentSceneDefinition;
use PTAdmin\Addon\Exception\AddonException;
use Throwable;

final class PaymentCatalog
{
    /**
     * 返回符合调用方明确目标的技术能力声明，不读取平台业务绑定，也不自动选择候选项。
     *
     * @return array<int, array<string, mixed>>
     */
    public function discover(PaymentRequirements $requirements): array
    {
        if (null === $requirements->targetValue()) {
            throw new InvalidArgumentException('Payment discovery requires an explicit target.');
        }

        $methods = [];
        foreach ($this->definitions() as $registered) {
            if (!isset($registered['payment_definition'])) {
                throw new AddonException('Payment capability does not declare payment protocol v2.');
            }
            $definition = PaymentDefinition::fromArray((array) $registered['payment_definition']);
            foreach ($definition->scenes() as $scene) {
                if (!$scene->matches($requirements)) {
                    continue;
                }
                $reference = new PaymentCapabilityReference(
                    (string) ($registered['addon_code'] ?? ''),
                    $definition->code(),
                    $requirements->profileValue() ?? 'default',
                    $scene->scene(),
                    $definition->protocolVersion()
                );
                $methods[] = $this->publicDefinition($reference, $definition, $scene);
            }
        }

        return $methods;
    }

    public function readiness(PaymentCapabilityReference $reference): PaymentReadinessResult
    {
        $registered = $this->resolveRegisteredDefinition($reference);

        try {
            $instance = app((string) ($registered['class'] ?? ''));
            if ($instance instanceof PaymentReadinessInterface) {
                return $instance->paymentReadiness($reference);
            }
            return PaymentReadinessResult::ready();
        } catch (Throwable $throwable) {
            return PaymentReadinessResult::notReady('readiness_check_failed', 'Payment readiness check failed.');
        }
    }

    public function gateway(PaymentCapabilityReference $reference): PaymentGateway
    {
        $this->resolveRegisteredDefinition($reference);

        return new PaymentGateway($reference);
    }

    /** @return array<int, array<string, mixed>> */
    private function definitions(): array
    {
        return AddonInjectsManage::getInstance()->getDefinitionsByGroup('payment');
    }

    /** @return array<string, mixed> */
    private function resolveRegisteredDefinition(PaymentCapabilityReference $reference): array
    {
        $registered = AddonInjectsManage::getInstance()->getDefinitionByAddonAndCode('payment', $reference->addonCode(), $reference->capabilityCode());
        if (!isset($registered['payment_definition'])) {
            throw new AddonException('The selected payment capability does not declare payment protocol v2.');
        }
        $definition = PaymentDefinition::fromArray((array) $registered['payment_definition']);
        if (null === $definition->scene($reference->scene())) {
            throw new AddonException('The selected payment capability does not support the referenced scene.');
        }

        return $registered;
    }

    /** @return array<string, mixed> */
    private function publicDefinition(PaymentCapabilityReference $reference, PaymentDefinition $definition, PaymentSceneDefinition $scene): array
    {
        return $reference->toArray() + [
            'title' => $definition->titleValue(),
            'interactions' => $scene->interactions(),
            'operations' => $scene->operations(),
            'required_inputs' => $scene->requiredInputs(),
            'currencies' => $scene->currencies(),
            'executors' => array_map(static function ($executor): array {
                return $executor->toArray();
            }, $scene->executors()),
        ];
    }
}
