<?php

declare(strict_types=1);

namespace PTAdmin\Addon\Contracts\Storage;

use PTAdmin\Addon\Contracts\CapabilityInterface;
use PTAdmin\Addon\Service\InjectPayload;

/**
 * 文件存储能力接口。
 *
 * 适用于本地存储、OSS、COS、S3、MinIO 等对象存储实现。
 *
 * 输入字段固定，渠道差异统一通过 meta 透传。
 * 输出字段固定，渠道原始响应统一放入 raw，渠道扩展信息统一放入 meta。
 */
interface StorageInterface extends CapabilityInterface
{
    /**
     * 上传文件或内容。
     *
     * 输入字段约定：
     * - disk: 存储驱动标识
     * - bucket: 存储桶，不需要时传 null
     * - path: 对象路径
     * - content: 字符串内容，不使用时传 null
     * - stream: 流资源或流对象，不使用时传 null
     * - visibility: 可见性，如 public、private
     * - meta: 渠道专属扩展参数
     *
     * @return array{
     *     disk:string,
     *     bucket:?string,
     *     path:string,
     *     url:?string,
     *     size:int|null,
     *     mime_type:?string,
     *     etag:?string,
     *     meta:array,
     *     raw:mixed
     * }
     */
    public function upload(InjectPayload $payload): array;

    /**
     * 删除对象。
     *
     * 输入字段约定：
     * - disk: 存储驱动标识
     * - bucket: 存储桶，不需要时传 null
     * - path: 对象路径
     * - meta: 渠道专属扩展参数
     */
    public function delete(InjectPayload $payload): bool;

    /**
     * 判断对象是否存在。
     *
     * 输入字段约定：
     * - disk: 存储驱动标识
     * - bucket: 存储桶，不需要时传 null
     * - path: 对象路径
     * - meta: 渠道专属扩展参数
     */
    public function exists(InjectPayload $payload): bool;

    /**
     * 生成临时访问地址。
     *
     * 输入字段约定：
     * - disk: 存储驱动标识
     * - bucket: 存储桶，不需要时传 null
     * - path: 对象路径
     * - expires_in: 有效期秒数
     * - disposition: 下载头，不需要时传 null
     * - meta: 渠道专属扩展参数
     *
     * @return array{
     *     url:string,
     *     expires_at:?string,
     *     meta:array,
     *     raw:mixed
     * }
     */
    public function temporaryUrl(InjectPayload $payload): array;
}
