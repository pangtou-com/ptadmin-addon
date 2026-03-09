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

if (!function_exists('has_addon')) {
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
}

if (!function_exists('has_addon_version')) {
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
}

if (!function_exists('addon_path')) {
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
}

if (!function_exists('addon_namespace')) {
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
}

if (!function_exists('addon_asset')) {
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
}

if (!function_exists('is_addon_running')) {
    /**
     * 判断当前是否在插件中运行.
     *
     * @return bool
     */
    function is_addon_running(): bool
    {
        $addon = request()->get('__addon__', null);

        return null !== $addon;
    }
}

if (!function_exists('get_running_addon_info')) {
    /**
     * 获取当前插件信息.
     *
     * @param $key
     * @param $default
     *
     * @return null|array|mixed
     */
    function get_running_addon_info($key = null, $default = null)
    {
        $addon = request()->get('__addon__');
        if (null !== $key) {
            return data_get($addon, $key, $default);
        }

        return $addon;
    }
}

if (!function_exists('version_to_arr')) {
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
}

if (!function_exists('version_if')) {
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
}

if (!function_exists('date_format_before')) {
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
}

if (!function_exists('format_date')) {
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
}

if (!function_exists('money_to_zh')) {
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
}
