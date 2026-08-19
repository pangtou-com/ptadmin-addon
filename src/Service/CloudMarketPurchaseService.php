<?php

declare(strict_types=1);

namespace PTAdmin\Addon\Service;

use PTAdmin\Addon\AddonApi;

final class CloudMarketPurchaseService
{
    /** @return array<string, mixed> */
    public function createOrder(
        string $code,
        int $addonVersionId,
        string $idempotencyKey
    ): array {
        return AddonApi::createCloudPurchaseOrder([
            'code' => $code,
            'addon_version_id' => $addonVersionId,
            'idempotency_key' => $idempotencyKey,
        ]);
    }

    /** @return array<string, mixed> */
    public function createPayment(string $orderNumber, string $channel): array
    {
        return AddonApi::createCloudPurchasePayment([
            'order_no' => $orderNumber,
            'channel' => $channel,
        ]);
    }

    /** @return array<string, mixed> */
    public function queryOrder(string $orderNumber): array
    {
        return AddonApi::queryCloudPurchaseOrder($orderNumber);
    }

    /** @return array<string, mixed> */
    public function closeOrder(string $orderNumber): array
    {
        return AddonApi::closeCloudPurchaseOrder($orderNumber);
    }
}
