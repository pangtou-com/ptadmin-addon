<?php

declare(strict_types=1);

namespace PTAdmin\Addon\Service;

use PTAdmin\Addon\Contracts\Payment\Data\AcknowledgePaymentNotifyRequest;
use PTAdmin\Addon\Contracts\Payment\Data\AcknowledgePaymentNotifyResult;
use PTAdmin\Addon\Contracts\Payment\Data\CreatePaymentRequest;
use PTAdmin\Addon\Contracts\Payment\Data\CreatePaymentResult;
use PTAdmin\Addon\Contracts\Payment\Data\ParsePaymentNotifyRequest;
use PTAdmin\Addon\Contracts\Payment\Data\ParsePaymentNotifyResult;
use PTAdmin\Addon\Contracts\Payment\Data\QueryPaymentRequest;
use PTAdmin\Addon\Contracts\Payment\Data\QueryPaymentResult;
use PTAdmin\Addon\Contracts\Payment\Data\QueryRefundRequest;
use PTAdmin\Addon\Contracts\Payment\Data\QueryRefundResult;
use PTAdmin\Addon\Contracts\Payment\Data\RefundPaymentRequest;
use PTAdmin\Addon\Contracts\Payment\Data\RefundPaymentResult;
use PTAdmin\Addon\Contracts\Payment\PaymentInterface;
use PTAdmin\Addon\Exception\AddonException;

class PaymentGateway
{
    /** @var string|null */
    private $addonCode;

    /** @var string|null */
    private $code;

    /** @var string|null */
    private $channel;

    public function __construct(?string $addonCode = null, ?string $code = null, ?string $channel = null)
    {
        $this->addonCode = blank($addonCode) ? null : trim($addonCode);
        $this->code = blank($code) ? null : trim($code);
        $this->channel = blank($channel) ? null : trim($channel);
    }

    public function addonCode(): ?string
    {
        return $this->addonCode;
    }

    public function code(): ?string
    {
        return $this->code;
    }

    public function definition(): array
    {
        return $this->resolveDefinition();
    }

    public function channels(): array
    {
        return $this->definition()['type'] ?? [];
    }

    public function channel(?string $channel): self
    {
        return new self($this->addonCode, $this->code, $channel);
    }

    public function create($payload = [], ?string $channel = null): CreatePaymentResult
    {
        $request = $this->normalizeRequest(CreatePaymentRequest::class, $payload);
        $channel = blank($channel) ? $this->channel : trim((string) $channel);
        $this->assertChannelSupported($channel);
        if (!blank($channel) && blank($request->get('scene'))) {
            $request = $request->with(['scene' => $channel]);
        }

        return $this->normalizeResult(
            CreatePaymentResult::class,
            $this->invoke('create', $request)
        );
    }

    public function query($payload = []): QueryPaymentResult
    {
        return $this->normalizeResult(
            QueryPaymentResult::class,
            $this->invoke('query', $this->normalizeRequest(QueryPaymentRequest::class, $payload))
        );
    }

    public function refund($payload = [], ?string $channel = null): RefundPaymentResult
    {
        $request = $this->normalizeRequest(RefundPaymentRequest::class, $payload);
        $channel = blank($channel) ? $this->channel : trim((string) $channel);
        $this->assertChannelSupported($channel);
        if (!blank($channel)) {
            $request = $request->with([
                'meta' => array_merge($request->meta(), ['channel' => $channel]),
            ]);
        }

        return $this->normalizeResult(
            RefundPaymentResult::class,
            $this->invoke('refund', $request)
        );
    }

    public function queryRefund($payload = []): QueryRefundResult
    {
        return $this->normalizeResult(
            QueryRefundResult::class,
            $this->invoke('queryRefund', $this->normalizeRequest(QueryRefundRequest::class, $payload))
        );
    }

    public function parseNotify($payload = []): ParsePaymentNotifyResult
    {
        return $this->normalizeResult(
            ParsePaymentNotifyResult::class,
            $this->invoke('parseNotify', $this->normalizeRequest(ParsePaymentNotifyRequest::class, $payload))
        );
    }

    public function acknowledgeNotify($payload = []): AcknowledgePaymentNotifyResult
    {
        return $this->normalizeResult(
            AcknowledgePaymentNotifyResult::class,
            $this->invoke('acknowledgeNotify', $this->normalizeRequest(AcknowledgePaymentNotifyRequest::class, $payload))
        );
    }

    private function invoke(string $method, $payload)
    {
        $definition = $this->resolveDefinition();
        $instance = app($definition['class']);
        if (!$instance instanceof PaymentInterface) {
            throw new AddonException(__('ptadmin-addon::messages.definition.payment_interface_required', ['target' => 'payment:'.$definition['addon_code']]));
        }
        if (!$instance->supports($method)) {
            throw new AddonException(__('ptadmin-addon::messages.definition.payment_method_unsupported', [
                'target' => 'payment:'.$definition['addon_code'],
                'method' => $method,
            ]));
        }

        return $instance->{$method}($payload);
    }

    private function resolveDefinition(): array
    {
        $manager = AddonInjectsManage::getInstance();
        if (!blank($this->addonCode) && !blank($this->code)) {
            return $manager->getDefinitionByAddonAndCode('payment', $this->addonCode, $this->code);
        }

        if (!blank($this->addonCode)) {
            $definitions = $manager->getDefinitionsByAddonCode('payment', $this->addonCode);
            if ([] === $definitions) {
                throw new AddonException(__('ptadmin-addon::messages.definition.payment_missing', ['target' => 'payment:'.$this->addonCode]));
            }

            return $definitions[0];
        }

        if (!blank($this->code)) {
            foreach ($manager->getDefinitionsByGroup('payment') as $definition) {
                if (($definition['code'] ?? null) === $this->code) {
                    return $definition;
                }
            }
        }

        $definitions = $manager->getDefinitionsByGroup('payment');
        if ([] === $definitions) {
            throw new AddonException(__('ptadmin-addon::messages.definition.payment_none'));
        }

        $configured = config('addon.defaults.payment');
        if (!blank($configured)) {
            foreach ($definitions as $definition) {
                if (($definition['addon_code'] ?? null) === $configured) {
                    return $definition;
                }
            }
        }

        return $definitions[0];
    }

    private function assertChannelSupported(?string $channel): void
    {
        if (blank($channel)) {
            return;
        }

        $supported = $this->channels();
        if ([] !== $supported && !\in_array($channel, $supported, true)) {
            $addonCode = $this->resolveDefinition()['addon_code'] ?? 'unknown';
            $code = $this->resolveDefinition()['code'] ?? 'unknown';
            throw new AddonException(__('ptadmin-addon::messages.definition.payment_channel_unsupported', [
                'target' => $addonCode.':'.$code,
                'channel' => $channel,
            ]));
        }
    }

    private function normalizeRequest(string $class, $payload)
    {
        if ($payload instanceof $class) {
            return $payload;
        }

        return $class::fromArray((array) $payload);
    }

    private function normalizeResult(string $class, $result)
    {
        if ($result instanceof $class) {
            return $result;
        }

        return $class::fromArray((array) $result);
    }
}
