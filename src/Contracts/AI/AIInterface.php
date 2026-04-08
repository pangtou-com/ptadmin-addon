<?php

declare(strict_types=1);

namespace PTAdmin\Addon\Contracts\AI;

use PTAdmin\Addon\Contracts\CapabilityInterface;
use PTAdmin\Addon\Service\InjectPayload;

/**
 * AI 能力接口。
 *
 * 适用于对话、文本生成、图片生成、向量化等模型服务实现。
 *
 * 输入字段固定，模型差异统一通过 meta 透传。
 * 输出字段固定，模型原始响应统一放入 raw，模型扩展信息统一放入 meta。
 */
interface AIInterface extends CapabilityInterface
{
    /**
     * 发起对话请求。
     *
     * 输入字段约定：
     * - model: 模型标识
     * - messages: 对话消息列表
     * - stream: 是否流式
     * - temperature: 采样温度，不需要时传 null
     * - meta: 模型专属扩展参数
     *
     * @return array{
     *     id:?string,
     *     model:string,
     *     content:string,
     *     items:array,
     *     usage:array,
     *     finish_reason:?string,
     *     meta:array,
     *     raw:mixed
     * }
     */
    public function chat(InjectPayload $payload): array;

    /**
     * 发起生成请求。
     *
     * 输入字段约定：
     * - model: 模型标识
     * - prompt: 提示词
     * - format: 输出格式
     * - options: 生成参数数组
     * - meta: 模型专属扩展参数
     *
     * @return array{
     *     id:?string,
     *     model:string,
     *     content:string,
     *     items:array,
     *     usage:array,
     *     finish_reason:?string,
     *     meta:array,
     *     raw:mixed
     * }
     */
    public function generate(InjectPayload $payload): array;

    /**
     * 文本向量化。
     *
     * 输入字段约定：
     * - model: 模型标识
     * - input: 待向量化内容，可以是字符串或字符串数组
     * - meta: 模型专属扩展参数
     *
     * @return array{
     *     id:?string,
     *     model:string,
     *     vectors:array,
     *     usage:array,
     *     meta:array,
     *     raw:mixed
     * }
     */
    public function embedding(InjectPayload $payload): array;
}
