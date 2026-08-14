<?php

declare(strict_types=1);

namespace PTAdmin\Addon\Service;

use PTAdmin\Addon\Contracts\Payment\ClosablePaymentInterface;
use PTAdmin\Addon\Contracts\Payment\Data\AcknowledgePaymentNotifyRequest;
use PTAdmin\Addon\Contracts\Payment\Data\AcknowledgePaymentNotifyResult;
use PTAdmin\Addon\Contracts\Payment\Data\ClosePaymentRequest;
use PTAdmin\Addon\Contracts\Payment\Data\ClosePaymentResult;
use PTAdmin\Addon\Contracts\Payment\Data\CreatePaymentRequest;
use PTAdmin\Addon\Contracts\Payment\Data\CreatePaymentResult;
use PTAdmin\Addon\Contracts\Payment\Data\ParsePaymentNotifyRequest;
use PTAdmin\Addon\Contracts\Payment\Data\ParsePaymentNotifyResult;
use PTAdmin\Addon\Contracts\Payment\Data\PreparePaymentRequest;
use PTAdmin\Addon\Contracts\Payment\Data\PreparePaymentResult;
use PTAdmin\Addon\Contracts\Payment\Data\QueryPaymentRequest;
use PTAdmin\Addon\Contracts\Payment\Data\QueryPaymentResult;
use PTAdmin\Addon\Contracts\Payment\Data\QueryRefundRequest;
use PTAdmin\Addon\Contracts\Payment\Data\QueryRefundResult;
use PTAdmin\Addon\Contracts\Payment\Data\RefundPaymentRequest;
use PTAdmin\Addon\Contracts\Payment\Data\RefundPaymentResult;
use PTAdmin\Addon\Contracts\Payment\PaymentInterface;
use PTAdmin\Addon\Contracts\Payment\PreparablePaymentInterface;
use PTAdmin\Addon\Contracts\Payment\Protocol\PaymentCapabilityReference;
use PTAdmin\Addon\Contracts\Payment\Protocol\PaymentDefinition;
use PTAdmin\Addon\Contracts\Payment\Protocol\PaymentInteraction;
use PTAdmin\Addon\Contracts\Payment\Protocol\PaymentInteractionResult;
use PTAdmin\Addon\Contracts\Payment\Protocol\PaymentInput;
use PTAdmin\Addon\Contracts\Payment\Protocol\PaymentOperation;
use PTAdmin\Addon\Contracts\Payment\Protocol\PaymentRefundStatus;
use PTAdmin\Addon\Contracts\Payment\Protocol\PaymentStatus;
use PTAdmin\Addon\Contracts\Payment\Protocol\PaymentSceneDefinition;
use PTAdmin\Addon\Exception\AddonException;

final class PaymentGateway
{
    /** @var PaymentCapabilityReference */
    private $reference;

    public function __construct(PaymentCapabilityReference $reference)
    {
        $this->reference = $reference;
    }

    public function reference(): PaymentCapabilityReference
    {
        return $this->reference;
    }

    public function definition(): array
    {
        return $this->resolveDefinition();
    }

    public function prepare($payload = []): PreparePaymentResult
    {
        $this->assertOperation(PaymentOperation::PREPARE);
        $request = $this->withPaymentContext($this->normalizeRequest(PreparePaymentRequest::class, $payload));
        $instance = $this->paymentInstance();
        if (!$instance instanceof PreparablePaymentInterface) {
            throw $this->unsupportedOperation(PaymentOperation::PREPARE);
        }

        $result = $this->normalizeResult(PreparePaymentResult::class, $instance->prepare($request));
        if (PaymentDefinition::PROTOCOL_VERSION !== $result->get('protocol_version') || !is_bool($result->get('ready'))) {
            throw new AddonException('Payment prepare result must contain protocol_version 2 and a boolean ready value.');
        }
        if (null !== $result->get('interaction')) {
            $this->validateInteraction((array) $result->get('interaction'));
        }

        return $result;
    }

    public function create($payload = []): CreatePaymentResult
    {
        $this->assertOperation(PaymentOperation::CREATE);
        $request = $this->normalizeRequest(CreatePaymentRequest::class, $payload);
        $this->validateCreateRequest($request);
        $request = $this->withPaymentContext($request);
        $result = $this->normalizeResult(CreatePaymentResult::class, $this->paymentInstance()->create($request));
        $this->validateCreateResult($result);

        return $result;
    }

    public function query($payload = []): QueryPaymentResult
    {
        $this->assertOperation(PaymentOperation::QUERY);
        $request = $this->withPaymentContext($this->normalizeRequest(QueryPaymentRequest::class, $payload));
        $result = $this->normalizeResult(QueryPaymentResult::class, $this->paymentInstance()->query($request));
        $this->validatePaymentStatus($result->get('status'));

        return $result;
    }

    public function close($payload = []): ClosePaymentResult
    {
        $this->assertOperation(PaymentOperation::CLOSE);
        $request = $this->withPaymentContext($this->normalizeRequest(ClosePaymentRequest::class, $payload));
        $instance = $this->paymentInstance();
        if (!$instance instanceof ClosablePaymentInterface) {
            throw $this->unsupportedOperation(PaymentOperation::CLOSE);
        }

        $result = $this->normalizeResult(ClosePaymentResult::class, $instance->close($request));
        $this->validatePaymentStatus($result->get('status'));

        return $result;
    }

    public function refund($payload = []): RefundPaymentResult
    {
        $this->assertOperation(PaymentOperation::REFUND);
        $request = $this->normalizeRequest(RefundPaymentRequest::class, $payload);
        $this->rejectLegacyMeta($request->meta(), ['amount']);
        $request = $this->withPaymentContext($request);
        $result = $this->normalizeResult(RefundPaymentResult::class, $this->paymentInstance()->refund($request));
        $this->validateRefundStatus($result->get('status'));

        return $result;
    }

    public function queryRefund($payload = []): QueryRefundResult
    {
        $this->assertOperation(PaymentOperation::QUERY_REFUND);
        $request = $this->withPaymentContext($this->normalizeRequest(QueryRefundRequest::class, $payload));
        $result = $this->normalizeResult(QueryRefundResult::class, $this->paymentInstance()->queryRefund($request));
        $this->validateRefundStatus($result->get('status'));

        return $result;
    }

    public function parseNotify($payload = []): ParsePaymentNotifyResult
    {
        $this->assertOperation(PaymentOperation::PARSE_NOTIFY);
        $request = $this->withPaymentContext($this->normalizeRequest(ParsePaymentNotifyRequest::class, $payload));
        $result = $this->normalizeResult(ParsePaymentNotifyResult::class, $this->paymentInstance()->parseNotify($request));
        $status = $result->get('status');
        if (!is_string($status) || (!PaymentStatus::isValid($status) && !PaymentRefundStatus::isValid($status))) {
            throw new AddonException('Payment protocol v2 notify result status is invalid.');
        }

        return $result;
    }

    public function acknowledgeNotify($payload = []): AcknowledgePaymentNotifyResult
    {
        $this->assertOperation(PaymentOperation::ACKNOWLEDGE_NOTIFY);
        $request = $this->withPaymentContext($this->normalizeRequest(AcknowledgePaymentNotifyRequest::class, $payload));

        return $this->normalizeResult(AcknowledgePaymentNotifyResult::class, $this->paymentInstance()->acknowledgeNotify($request));
    }

    private function paymentInstance(): PaymentInterface
    {
        $definition = $this->resolveDefinition();
        $instance = app((string) ($definition['class'] ?? ''));
        if (!$instance instanceof PaymentInterface) {
            throw new AddonException('Payment capability handler must implement PaymentInterface.');
        }

        return $instance;
    }

    private function resolveDefinition(): array
    {
        $definition = AddonInjectsManage::getInstance()->getDefinitionByAddonAndCode(
            'payment',
            $this->reference->addonCode(),
            $this->reference->capabilityCode()
        );
        if (!isset($definition['payment_definition'])) {
            throw new AddonException('Payment capability does not declare payment protocol v2.');
        }

        return $definition;
    }

    private function sceneDefinition(): PaymentSceneDefinition
    {
        $definition = PaymentDefinition::fromArray((array) $this->resolveDefinition()['payment_definition']);
        $scene = $definition->scene($this->reference->scene());
        if (null === $scene) {
            throw new AddonException('Payment capability does not support the referenced scene.');
        }

        return $scene;
    }

    private function assertOperation(string $operation): void
    {
        if (!in_array($operation, $this->sceneDefinition()->operations(), true)) {
            throw $this->unsupportedOperation($operation);
        }
    }

    private function withPaymentContext($request)
    {
        return $request->with([
            'meta' => array_merge($request->meta(), ['payment_context' => $this->reference->toArray()]),
        ]);
    }

    private function validateCreateResult(CreatePaymentResult $result): void
    {
        if (PaymentDefinition::PROTOCOL_VERSION !== $result->get('protocol_version')) {
            throw new AddonException('Payment create result protocol_version must be 2.');
        }
        $this->validatePaymentStatus($result->get('status'));
        if ($this->reference->scene() !== $result->get('scene')) {
            throw new AddonException('Payment create result scene does not match the resolved capability reference.');
        }
        $this->rejectLegacyMeta($result->meta(), ['action', 'payload', 'display']);
        $this->validateInteraction((array) $result->get('interaction'));
    }

    private function validateCreateRequest(CreatePaymentRequest $request): void
    {
        $this->rejectLegacyMeta($request->meta(), ['scene', 'amount', 'open_id']);
        $requiredInputs = array_unique(array_merge([
            PaymentInput::ORDER_NO,
            PaymentInput::AMOUNT_MINOR,
            PaymentInput::CURRENCY,
            PaymentInput::SUBJECT,
            PaymentInput::NOTIFY_URL,
        ], $this->sceneDefinition()->requiredInputs()));
        foreach ($requiredInputs as $input) {
            $value = $request->get($input);
            if (null === $value || (is_string($value) && '' === trim($value))) {
                throw new AddonException(sprintf('Payment create request requires input [%s].', $input));
            }
        }
        $amount = $request->get(PaymentInput::AMOUNT_MINOR);
        if (null !== $amount && (!is_int($amount) || 0 >= $amount)) {
            throw new AddonException('Payment amount_minor must be a positive integer.');
        }
        $currency = $request->get(PaymentInput::CURRENCY);
        if (null !== $currency && (!is_string($currency) || 1 !== preg_match('/\A[A-Z]{3}\z/', $currency))) {
            throw new AddonException('Payment currency must be an uppercase ISO 4217 code.');
        }
        $supportedCurrencies = $this->sceneDefinition()->currencies();
        if (is_string($currency) && [] !== $supportedCurrencies && !in_array($currency, $supportedCurrencies, true)) {
            throw new AddonException(sprintf('Payment currency [%s] is not declared for the resolved scene.', $currency));
        }
        foreach ([PaymentInput::NOTIFY_URL, PaymentInput::RETURN_URL] as $urlInput) {
            $url = $request->get($urlInput);
            if (null !== $url && (!is_string($url) || false === filter_var($url, FILTER_VALIDATE_URL))) {
                throw new AddonException(sprintf('Payment input [%s] must be a valid URL.', $urlInput));
            }
        }
    }

    /**
     * @param array<string, mixed> $meta
     * @param array<int, string>   $legacyFields
     */
    private function rejectLegacyMeta(array $meta, array $legacyFields): void
    {
        foreach ($legacyFields as $field) {
            if (array_key_exists($field, $meta)) {
                throw new AddonException(sprintf('Legacy payment field [%s] is not supported.', $field));
            }
        }
    }

    private function validateInteraction(array $interaction): void
    {
        try {
            $result = PaymentInteractionResult::fromArray($interaction);
        } catch (\InvalidArgumentException $exception) {
            throw new AddonException($exception->getMessage(), 0, $exception);
        }

        $scene = $this->sceneDefinition();
        if (!in_array($result->type(), $scene->interactions(), true)) {
            throw new AddonException('Payment result interaction was not declared for the resolved scene.');
        }
        if (PaymentInteraction::CLIENT_INVOKE !== $result->type()) {
            return;
        }

        $executor = (string) ($result->payload()['executor'] ?? '');
        $version = (string) ($result->payload()['version'] ?? '');
        foreach ($scene->executors() as $declared) {
            if ($declared->code() === $executor && $declared->version() === $version) {
                return;
            }
        }

        throw new AddonException('Payment result client executor was not declared for the resolved scene.');
    }

    private function validatePaymentStatus($status): void
    {
        if (!is_string($status) || !PaymentStatus::isValid($status)) {
            throw new AddonException('Payment protocol v2 status is invalid.');
        }
    }

    private function validateRefundStatus($status): void
    {
        if (!is_string($status) || !PaymentRefundStatus::isValid($status)) {
            throw new AddonException('Payment protocol v2 refund status is invalid.');
        }
    }

    private function unsupportedOperation(string $operation): AddonException
    {
        return new AddonException(sprintf(
            'Payment operation [%s] is not supported by capability [%s:%s:%s].',
            $operation,
            $this->reference->addonCode(),
            $this->reference->capabilityCode(),
            $this->reference->scene()
        ));
    }

    private function normalizeRequest(string $class, $payload)
    {
        return $payload instanceof $class ? $payload : $class::fromArray((array) $payload);
    }

    private function normalizeResult(string $class, $result)
    {
        return $result instanceof $class ? $result : $class::fromArray((array) $result);
    }
}
