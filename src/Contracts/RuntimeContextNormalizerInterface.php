<?php

declare(strict_types=1);

namespace PTAdmin\Addon\Contracts;

/**
 * 运行时上下文标准化接口。
 *
 * 负责把不同来源的数据收敛为统一的标准协议结构。
 */
interface RuntimeContextNormalizerInterface
{
    /**
     * 将任意上下文数据标准化为统一结构。
     *
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function normalize(array $context): array;

    /**
     * 根据页面入口数据构建标准上下文。
     *
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    public function page(array $payload): array;

    /**
     * 返回空的标准上下文结构。
     *
     * @return array<string, mixed>
     */
    public function empty(): array;
}
