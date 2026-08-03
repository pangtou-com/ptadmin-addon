<?php

declare(strict_types=1);

namespace PTAdmin\Addon\Contracts;

use PTAdmin\Addon\Service\InjectPayload;

/**
 * 能力运行前的可用性检查。
 *
 * 插件应在这里检查启用状态和必要配置，不执行远程业务请求。
 */
interface CapabilityReadinessInterface
{
    public function ready(InjectPayload $context): bool;
}
