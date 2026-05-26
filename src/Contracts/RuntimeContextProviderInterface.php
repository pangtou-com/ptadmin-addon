<?php

declare(strict_types=1);

namespace PTAdmin\Addon\Contracts;

use PTAdmin\Addon\Service\DirectivesDTO;

/**
 * 运行时上下文提供器接口。
 *
 * 管理当前请求的标准上下文，为指令与普通运行时场景提供读取入口。
 */
interface RuntimeContextProviderInterface
{
    /**
     * 写入运行时上下文。
     *
     * 用于首次注入或直接覆盖已有同名字段的场景。
     *
     * @param array<string, mixed> $context
     */
    public function put(array $context): void;

    /**
     * 递归合并运行时上下文。
     *
     * 用于只补充局部字段而不替换整份上下文的场景。
     *
     * @param array<string, mixed> $context
     */
    public function merge(array $context): void;

    /**
     * 整份替换当前运行时上下文。
     *
     * @param array<string, mixed> $context
     */
    public function replace(array $context): void;

    /**
     * 获取当前请求中的标准上下文。
     *
     * @return array<string, mixed>
     */
    public function current(): array;

    /**
     * 优先从指令 DTO 中读取上下文，不存在时退回当前请求上下文。
     *
     * @return array<string, mixed>
     */
    public function fromDto(?DirectivesDTO $dto = null): array;

    /**
     * 读取指定路径的上下文字段。
     *
     * @param mixed $default
     *
     * @return mixed
     */
    public function get(string $key, $default = null);

    /**
     * 判断指定路径的上下文字段是否存在。
     */
    public function has(string $key): bool;

    /**
     * 清空当前请求中的运行时上下文。
     */
    public function clear(): void;
}
