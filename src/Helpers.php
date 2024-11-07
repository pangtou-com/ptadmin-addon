<?php

declare(strict_types=1);

/**
 *  PTAdmin
 *  ============================================================================
 *  版权所有 2022-2024 重庆胖头网络技术有限公司，并保留所有权利。
 *  网站地址: https://www.pangtou.com
 *  ----------------------------------------------------------------------------
 *  尊敬的用户，
 *     感谢您对我们产品的关注与支持。我们希望提醒您，在商业用途中使用我们的产品时，请务必前往官方渠道购买正版授权。
 *  购买正版授权不仅有助于支持我们不断提供更好的产品和服务，更能够确保您在使用过程中不会引起不必要的法律纠纷。
 *  正版授权是保障您合法使用产品的最佳方式，也有助于维护您的权益和公司的声誉。我们一直致力于为客户提供高质量的解决方案，并通过正版授权机制确保产品的可靠性和安全性。
 *  如果您有任何疑问或需要帮助，我们的客户服务团队将随时为您提供支持。感谢您的理解与合作。
 *  诚挚问候，
 *  【重庆胖头网络技术有限公司】
 *  ============================================================================
 *  Author:    Zane
 *  Homepage:  https://www.pangtou.com
 *  Email:     vip@pangtou.com
 */

if (!function_exists('get_table_name')) {
    /**
     * 返回数据表名称.
     * 通过模型或者DB操作数据库时laravel会自动添加上前缀信息，则无需这个使用这个方法。
     * 通过sql语句操作时则需要加上前缀信息.
     *
     * @param $tableName
     *
     * @return string
     */
    function get_table_name($tableName): string
    {
        $prefix = config('database.prefix', 'pt_');
        if (blank($prefix)) {
            return $tableName;
        }
        if (\Illuminate\Support\Str::startsWith($tableName, $prefix)) {
            return $tableName;
        }

        return $prefix.$tableName;
    }
}

if (!function_exists('table_to_prefix_empty')) {
    /**
     * 将表前缀替换为空的.
     *
     * @param mixed $tableName
     */
    function table_to_prefix_empty($tableName): string
    {
        $prefix = config('database.prefix', 'pt_');
        if (blank($prefix)) {
            return $tableName;
        }

        return \Illuminate\Support\Str::replaceFirst($prefix, '', $tableName);
    }
}
if (!function_exists('has_addon')) {
    /**
     * 验证插件是否存在.
     *
     * @param $name
     *
     * @return bool
     */
    function has_addon($name): bool
    {
        return false;
    }
}

if (!function_exists('has_addon_version')) {
    /**
     * 验证插件版本.
     *
     * @param string $name
     * @param mixed  $version
     *
     * @return bool
     */
    function has_addon_version(string $name, $version): bool
    {
        return false;
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
        return base_path('addons'.\DIRECTORY_SEPARATOR.ucfirst($code).(null !== $path ? \DIRECTORY_SEPARATOR.$path : ''));
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
        return 'Addon\\'.ucfirst($code).($namespace ? '\\'.$namespace : '');
    }
}

if (!function_exists('parser_addon_ini')) {
    /**
     * 解析插件配置文件.
     *
     * @param mixed $addonDir
     *
     * @return array
     */
    function parser_addon_ini($addonDir): array
    {
        $root = addon_path($addonDir);
        if (!is_dir($root)) {
            return [];
        }
        $file = $root.\DIRECTORY_SEPARATOR.'info.ini';
        if (!is_file($file) || !file_exists($file)) {
            return [];
        }
        $config = parse_ini_file($file, true);
        if (false === $config) {
            return [];
        }
        $result = [];
        foreach ($config as $key => $value) {
            if ('license_key' === $key) {
                continue;
            }
            $key = explode('.', $key);
            if (count($key) > 1) {
                $result[$key[0]][$key[1]] = $value;
            } else {
                $result[$key[0]] = $value;
            }
        }

        return $result;
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
        return null !== request()->addon;
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
        $addon = request()->addon;
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
