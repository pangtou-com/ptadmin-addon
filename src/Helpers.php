<?php

declare(strict_types=1);

/**
 *  ============================================================================
 *  ******************************【PTAdmin/Addon】******************************
 *  ============================================================================
 *  Copyright (c) 2022-2026 【重庆胖头网络技术有限公司】，并保留所有权利。
 *  ============================================================================
 *  站点首页:  https://www.pangtou.com
 *  文档地址:  https://docs.pangtou.com
 *  联系邮箱:  vip@pangtou.com
 */

use PTAdmin\Addon\Contracts\RuntimeContextNormalizerInterface;
use PTAdmin\Addon\Contracts\RuntimeContextProviderInterface;
use PTAdmin\Addon\Service\DirectivesDTO;

/**
 * 验证插件是否存在.
 *
 * @param $code
 *
 * @return bool
 */
function has_addon($code): bool
{
    return \PTAdmin\Addon\Addon::hasAddon($code);
}

/**
 * 验证插件版本.
 *
 * @param string $code
 * @param mixed  $version
 *
 * @return bool
 */
function has_addon_version(string $code, $version): bool
{
    return \PTAdmin\Addon\Addon::checkAddonVersion($code, $version);
}

/**
 * 插件目录.
 *
 * @param $code
 * @param null $path
 *
 * @return string
 */
function addon_path($code, $path = null): string
{
    return \PTAdmin\Addon\Addon::getAddonPath($code, $path);
}

/**
 * 获取插件的命名空间.
 *
 * @param $code
 * @param $namespace
 *
 * @return string
 */
function addon_namespace($code, $namespace = null): string
{
    $addon = \PTAdmin\Addon\Addon::getAddon($code);

    return $addon->getAddonNamespace($namespace);
}

/**
 * 插件的静态资源访问路径.
 *
 * @param mixed $addon_code 所属插件code
 * @param mixed $path       资源路径
 * @param bool  $force      是否强制更新
 * @param mixed $secure     设置是否生成安全访问地址
 *
 * @return string
 */
function addon_asset($addon_code, $path, bool $force = false, $secure = null): string
{
    $path = ltrim($path, '/');
    // 当不启用debug模式时，直接返回资源地址
    if (true !== config('app.debug') && false === $force) {
        return asset("addons/{$addon_code}/{$path}", $secure);
    }
    // 判断应用目录下是否存在资源信息
    $addon_path = addon_path($addon_code, 'Assets'.\DIRECTORY_SEPARATOR.$path);
    if (!file_exists($addon_path)) {
        throw new \PTAdmin\Addon\Exception\AddonException("Addon [{$addon_code}] Assets [{$path}] Not Found.");
    }
    // 拷贝资源至访问目录
    $addon_storage_path = storage_path('app'.\DIRECTORY_SEPARATOR.'addons'.\DIRECTORY_SEPARATOR."{$addon_code}".\DIRECTORY_SEPARATOR."{$path}");
    $storageExists = file_exists($addon_storage_path);
    if (!$storageExists || (filemtime($addon_path) > filemtime($addon_storage_path))) {
        $filesystem = new \Illuminate\Filesystem\Filesystem();
        $filesystem->ensureDirectoryExists($filesystem->dirname($addon_storage_path));
        $filesystem->copy($addon_path, $addon_storage_path);
    }

    return asset("addons/{$addon_code}/{$path}?a=".time(), $secure);
}

/**
 * 判断当前是否在插件中运行.
 *
 * @return bool
 * @throws \Psr\Container\ContainerExceptionInterface
 * @throws \Psr\Container\NotFoundExceptionInterface
 */
function is_addon_running(): bool
{
    $addon = request()->get('__addon__', null);

    return null !== $addon;
}

/**
 * 获取当前插件信息.
 *
 * @param null $key
 * @param null $default
 *
 * @return null|array|mixed
 * @throws \Psr\Container\ContainerExceptionInterface
 * @throws \Psr\Container\NotFoundExceptionInterface
 */
function get_running_addon_info($key = null, $default = null)
{
    $addon = request()->get('__addon__');
    if (null !== $key) {
        return data_get($addon, $key, $default);
    }

    return $addon;
}

/**
 * 解析版本.需要版本格式必须为 v1.1.1 解析为数组格式.
 *
 * @param string $version
 *
 * @return array
 */
function version_to_arr(string $version): array
{
    $v = mb_substr($version, 0, 1);
    if ('v' === $v) {
        $version = mb_substr($version, 1, mb_strlen($version) - 1);
    }

    return explode('.', $version);
}

/**
 * 判断版本是否允许安装.
 *
 * @param string $ver     要求版本
 * @param string $version 对比版本
 *
 * @return bool
 */
function version_if(string $ver, string $version): bool
{
    $ver = version_to_arr($ver);
    $version = version_to_arr($version);
    $res = true;
    for ($i = 0; $i < 3; ++$i) {
        $v = (int) $ver[$i] ?? 0;
        $v1 = (int) $version[$i] ?? 0;
        if ($v < $v1) {
            $res = false;

            break;
        }
    }

    return $res;
}

/**
 * 获取时间格式.
 *
 * @param $time
 *
 * @return string
 */
function date_format_before($time): string
{
    if (!class_exists(Carbon\Carbon::class) || blank($time)) {
        return $time;
    }
    /** @var mixed $now */
    $now = Carbon\Carbon::now();
    $standardFormat = format_date($time);
    $diffInSeconds = $now->diffInSeconds($standardFormat);
    $timeUnits = [
        31536000 => ['Years', '年前'],
        2419200 => ['Months', '个月前'],
        604800 => ['Weeks', '周前'],
        86400 => ['Days', '天前'],
        3600 => ['Hours', '小时前'],
        60 => ['Minutes', '分钟前'],
        1 => ['Seconds', '秒前'],
    ];

    foreach ($timeUnits as $seconds => [$unit, $description]) {
        if ($diffInSeconds < $seconds) {
            continue;
        }

        $diff = $now->{'diffIn'.$unit}($standardFormat);

        return $diff.$description;
    }

    return '刚刚';
}

/**
 * 时间格式化.
 *
 * @param $time
 * @param string $format
 *
 * @return string
 */
function format_date($time, string $format = 'Y-m-d H:i:s'): ?string
{
    if (!class_exists(Carbon\Carbon::class) || blank($time)) {
        return $time;
    }
    if ($time instanceof \DateTime || $time instanceof \DateTimeImmutable) {
        $standardFormat = Carbon\Carbon::instance($time);
    } elseif (is_numeric($time)) {
        $standardFormat = Carbon\Carbon::createFromTimestamp($time);
    } elseif (is_string($time)) {
        $standardFormat = Carbon\Carbon::parse($time);
    } else {
        return $time;
    }

    return $standardFormat->format($format);
}

/**
 * 将金额转换为中文大写.
 * todo 待处理.
 *
 * @param $amount
 *
 * @return mixed
 */
function money_to_zh($amount)
{
    return $amount;
}

/**
 * 写入运行时上下文。
 *
 * @param array<string, mixed> $context
 */
function runtime_context_put(array $context): void
{
    app(RuntimeContextProviderInterface::class)->put($context);
}

/**
 * 递归合并运行时上下文。
 *
 * @param array<string, mixed> $context
 */
function runtime_context_merge(array $context): void
{
    app(RuntimeContextProviderInterface::class)->merge($context);
}

/**
 * 整份替换运行时上下文。
 *
 * @param array<string, mixed> $context
 */
function runtime_context_replace(array $context): void
{
    app(RuntimeContextProviderInterface::class)->replace($context);
}

/**
 * 获取当前请求中的标准上下文。
 *
 * @return array<string, mixed>
 */
function runtime_context_current(): array
{
    return app(RuntimeContextProviderInterface::class)->current();
}

/**
 * 优先从指令 DTO 中读取上下文，不存在时退回当前请求上下文。
 *
 * @return array<string, mixed>
 */
function runtime_context_from_dto(?DirectivesDTO $dto = null): array
{
    return app(RuntimeContextProviderInterface::class)->fromDto($dto);
}

/**
 * 读取运行时上下文。
 *
 * 未传入 key 时返回整份上下文，传入 key 时按点路径读取字段。
 *
 * @param mixed $default
 *
 * @return mixed
 */
function runtime_context(?string $key = null, $default = null)
{
    $context = runtime_context_current();
    if (null === $key || '' === $key) {
        return $context;
    }

    return data_get($context, $key, $default);
}

/**
 * 清空当前请求中的运行时上下文。
 */
function runtime_context_forget(): void
{
    app(RuntimeContextProviderInterface::class)->clear();
}

/**
 * 将任意上下文标准化为统一结构。
 *
 * @param array<string, mixed> $context
 *
 * @return array<string, mixed>
 */
function runtime_context_normalize(array $context): array
{
    return app(RuntimeContextNormalizerInterface::class)->normalize($context);
}

/**
 * 根据页面入口数据构建标准上下文。
 *
 * @param array<string, mixed> $payload
 *
 * @return array<string, mixed>
 */
function runtime_context_page(array $payload): array
{
    return app(RuntimeContextNormalizerInterface::class)->page($payload);
}

/**
 * 将当前循环项压入指令运行上下文栈。
 *
 * @param mixed $item
 */
function pt_directive_context_push(string $key, $item): void
{
    $key = trim($key);
    if ('' === $key) {
        return;
    }

    $context = runtime_context_current();
    $stack = data_get($context, $key, []);
    if (!\is_array($stack)) {
        $stack = [];
    }

    $stack[] = $item;
    data_set($context, $key, $stack);
    runtime_context_replace($context);
}

/**
 * 从指令运行上下文栈移除当前循环项。
 */
function pt_directive_context_pop(string $key): void
{
    $key = trim($key);
    if ('' === $key) {
        return;
    }

    $context = runtime_context_current();
    $stack = data_get($context, $key, []);
    if (!\is_array($stack) || [] === $stack) {
        return;
    }

    array_pop($stack);
    data_set($context, $key, array_values($stack));
    runtime_context_replace($context);
}
