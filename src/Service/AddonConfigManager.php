<?php
/**
 * Author: Zane
 * Email: 873934580@qq.com
 * Date: 2024/12/2
 */

namespace PTAdmin\Addon\Service;

use Illuminate\Support\Str;
use PTAdmin\Addon\Exception\AddonException;
use PTAdmin\Addon\Providers\BaseAddonService;

/**
 * 插件配置管理器
 * @method getAddons($addonCode = null) 获取已加载的插件信息
 * @method getProviders($addonCode = null) 获取以启动的服务提供者
 * @method getInject($addonCode = null) 获取注入配置
 * @method getResponse($addonCode = null) 获取资源配置
 * @method getDirectives($addonCode = null) 获取指令配置
 * @method getHooks($addonCode = null) 获取钩子配置
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

    public function byCacheLoadConfig(array $data, $manager)
    {
        $this->load_status = true;
        foreach ($data as $key => $val) {
            if ($manager->isAddonDisable(addon_path($val['base_path']))) {
                continue;
            }
            if (property_exists($this, $key)) {
                $this->{$key} = $val;
            }
        }
    }

    public function __call($name, $arguments)
    {
        $name = lcfirst(str_replace("get", '', $name));
        if (property_exists($this, $name)) {
            if (count($arguments) > 0) {
                return $this->{$name}[$arguments[0]] ?? null;
            }
            return $this->{$name};
        }
        throw new \BadMethodCallException("Undefined method {$name}");
    }

    public function getLoadStatus(): bool
    {
        return $this->load_status;
    }

    public function loadConfig(array $dirs, $manager)
    {
        $this->load_status = true;
        foreach ($dirs as $dir) {
            if ($manager->isAddonDisable($dir)) {
                continue;
            }
            $config = $this->readAddonConfig($dir);
            if ($config === null) {
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
        if ($config === null || !isset($config['code'])) {
            return null;
        }
        $config['base_path'] = basename($path);

        return $config;
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

    protected function addonMergeConfig($config)
    {
        $allow = ['response', 'inject', 'hooks', 'providers', 'directives'];
        $addons = [];
        $this->setProviders($config);
        unset($config['providers']);
        foreach ($config as $key => $value) {
            if (in_array($key, $allow, true)) {
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
     * 设置服务注册类
     * @param $config
     * @return void
     */
    protected function setProviders($config)
    {
        if (isset($config['providers'])) {
            $this->providers[$config['code']] = $config['providers'];
            return;
        }
        // 如果没有显示指定服务注册类，则自动扫描
        $this->scanProviders($config);
    }

    /**
     * 扫描服务注册类
     * @param $config
     * @return void
     */
    protected function scanProviders($config)
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
            if (is_subclass_of($class, BaseAddonService::class)) {
                $this->providers[$config['code']][] = $class;
            }
        }
    }
}