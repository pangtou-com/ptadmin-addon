<?php

declare(strict_types=1);

namespace PTAdmin\Addon\Support;

/**
 * 运行时上下文键名定义。
 *
 * 集中管理平台上下文使用的内部键，避免散落魔法字符串。
 */
final class RuntimeContextKeys
{
    public const REQUEST_ATTRIBUTE = '__pt_runtime_context';

    public const DTO_ATTRIBUTE = '__pt_context';

    private function __construct()
    {
    }
}
