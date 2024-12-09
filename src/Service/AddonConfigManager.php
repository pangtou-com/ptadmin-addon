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

namespace PTAdmin\Addon\Service;

use Illuminate\Support\Arr;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use PTAdmin\Addon\Exception\AddonException;

/**
 * 插件配置管理器.
 *
 * @method getAddons($addonCode = null)     获取已加载的插件信息
 * @method getProviders($addonCode = null)  获取以启动的服务提供者
 * @method getInject($addonCode = null)     获取注入配置
 * @method getResponse($addonCode = null)   获取资源配置
 * @method getDirectives($addonCode = null) 获取指令配置
 * @method getHooks($addonCode = null)      获取钩子配置
 */
class AddonConfigManager
{
    protected $load_status = false;
    /** @var array 插件基础信息 */
    protected $addons = [];
    /** @var array 插件服务注册 */
    protected $providers = [];
    /** @var array 注入配置 */
    protected $inject = [];
    /** @var array 资源配置 */
    protected $response = [];
    /** @var array 导出指令配置 */
    protected $directives = [];
    /** @var array 钩子信息 */
    protected $hooks = [];

    public function __call($name, $arguments)
    {
        $name = lcfirst(str_replace('get', '', $name));
        if (property_exists($this, $name)) {
            if (\count($arguments) > 0) {
                return $this->{$name}[$arguments[0]] ?? null;
            }

            return $this->{$name};
        }

        throw new \BadMethodCallException("Undefined method {$name}");
    }

    public function __toArray(): array
    {
        return [
            'addons' => $this->addons,
            'providers' => $this->providers,
            'inject' => $this->inject,
            'response' => $this->response,
            'directives' => $this->directives,
            'hooks' => $this->hooks,
        ];
    }

    public function __toString()
    {
        return var_export($this->__toArray(), true);
    }

    public function byCacheLoadConfig(array $data, $manager): void
    {
        $this->load_status = true;
        foreach ($data as $key => $val) {
            if (!isset($val['base_path'])) {
                continue;
            }
            if ($manager->isAddonDisable(addon_path($val['base_path']))) {
                continue;
            }
            if (property_exists($this, $key)) {
                $this->{$key} = $val;
            }
        }
    }

    public function getLoadStatus(): bool
    {
        return $this->load_status;
    }

    public function loadConfig(array $dirs, $manager): void
    {
        $this->load_status = true;
        foreach ($dirs as $dir) {
            if ($manager->isAddonDisable($dir)) {
                continue;
            }
            $config = $this->readAddonConfig($dir);
            if (null === $config) {
                continue;
            }
            if (isset($this->addons[$config['code']])) {
                throw new AddonException("插件代码【{$config['code']}】重复定义");
            }

            $this->addonMergeConfig($config);
        }
    }

    public function readAddonConfig($path)
    {
        $filename = $path.\DIRECTORY_SEPARATOR.'ptadmin.config.json';
        if (!file_exists($filename)) {
            return null;
        }
        $content = @file_get_contents($filename);
        if (false === $content) {
            return null;
        }

        $config = @json_decode($content, true);
        if (null === $config || !isset($config['code'])) {
            return null;
        }
        $config['base_path'] = basename($path);

        return $config;
    }

    protected function addonMergeConfig($config): void
    {
        $allow = ['response', 'inject', 'hooks', 'providers', 'directives'];
        $addons = [];
        $this->setProviders($config);
        unset($config['providers']);
        foreach ($config as $key => $value) {
            if (\in_array($key, $allow, true)) {
                if (property_exists($this, $key)) {
                    $this->{$key}[$config['code']] = $value;
                }

                continue;
            }
            $addons[$key] = $value;
        }
        $this->addons[$config['code']] = $addons;
    }

    /**
     * 设置服务注册类.
     *
     * @param $config
     */
    protected function setProviders($config): void
    {
        if (isset($config['providers'])) {
            $provider = Arr::wrap($config['providers']);
            foreach ($provider as $item) {
                if (is_subclass_of($item, ServiceProvider::class)) {
                    $this->providers[$config['code']][] = $item;
                }
            }

            return;
        }
        // 如果没有显示指定服务注册类，则自动扫描
        $this->scanProviders($config);
    }

    /**
     * 扫描服务注册类.
     *
     * @param $config
     */
    protected function scanProviders($config): void
    {
        $providers = addon_path($config['base_path'], 'Providers');
        if (!is_dir($providers)) {
            return;
        }
        $files = array_diff(scandir($providers), ['.', '..', '.gitkeep']);
        foreach ($files as $item) {
            if (!Str::endsWith($item, '.php')) {
                continue;
            }
            $class = 'Addon\\'.$config['base_path'].'\\Providers\\'.str_replace('.php', '', $item);
            if (is_subclass_of($class, ServiceProvider::class)) {
                $this->providers[$config['code']][] = $class;
            }
        }
    }
}
