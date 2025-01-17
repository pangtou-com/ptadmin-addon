<?php

declare(strict_types=1);

/**
 *  PTAdmin
 *  ============================================================================
 *  版权所有 2022-2025 重庆胖头网络技术有限公司，并保留所有权利。
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

/**
 * 插件配置管理器.
 *
 * @method string getCode()
 * @method string getBasePath()
 * @method string getTitle()
 * @method string getVersion()
 */
class AddonConfigManager
{
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

    /** @var array 原始配置信息 */
    protected $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function __call($name, $arguments)
    {
        if (Str::startsWith($name, 'get')) {
            $name = Str::snake(Str::after($name, 'get'));

            return data_get($this->config, $name);
        }
    }

    public function __toString()
    {
        return var_export($this->toArray(), true);
    }

    public function toArray(): array
    {
        return [
            'addons' => $this->getAddons(),
            'providers' => $this->getProviders(),
            'inject' => $this->getInject(),
            'response' => $this->getResponse(),
            'directives' => $this->getDirectives(),
            'hooks' => $this->getHooks(),
        ];
    }

    public function getAddonPath($path = null): string
    {
        return base_path('addons'.\DIRECTORY_SEPARATOR.$this->config['base_path'].(null !== $path ? \DIRECTORY_SEPARATOR.$path : ''));
    }

    public function getAddonNamespace($namespace = null): string
    {
        $addon = $this->config['base_path'];

        return 'Addon\\'.$addon.($namespace ? '\\'.$namespace : '');
    }

    public function getAddons(): array
    {
        return $this->config;
    }

    /**
     * 插件是否禁用中.
     *
     * @return bool
     */
    public function isDisable(): bool
    {
        return file_exists($this->config['base_path'].\DIRECTORY_SEPARATOR.'disable');
    }

    public function getProviders(): array
    {
        if (\count($this->providers) > 0) {
            return $this->providers;
        }
        if (isset($this->config['providers'])) {
            $provider = Arr::wrap($this->config['providers']);
            foreach ($provider as $item) {
                if (is_subclass_of($item, ServiceProvider::class)) {
                    $this->providers[] = $item;
                }
            }

            return $this->providers ?? [];
        }

        return $this->scanProviders();
    }

    public function getInject(): array
    {
        if (\count($this->inject) > 0) {
            return $this->inject;
        }
        if (isset($this->config['inject'])) {
            $this->inject = Arr::wrap($this->config['inject']);
        }

        return $this->inject;
    }

    public function getResponse(): array
    {
        if (\count($this->response) > 0) {
            return $this->response;
        }
        if (isset($this->config['response'])) {
            $this->response = Arr::wrap($this->config['response']);
        }

        return $this->response;
    }

    public function getDirectives(): array
    {
        if (\count($this->directives) > 0) {
            return $this->directives;
        }
        $d = Arr::wrap($this->config['directives'] ?? []);
        $data = [];
        foreach ($d as $item) {
            if (isset($item['name'])) {
                $data[$item['name']] = $item;
            }
        }

        return $this->directives = $data;
    }

    public function getHooks(): array
    {
        if (\count($this->hooks) > 0) {
            return $this->hooks;
        }
        if (isset($this->config['hooks'])) {
            $this->hooks = Arr::wrap($this->config['hooks']);
        }

        return $this->hooks;
    }

    /**
     * 扫描服务注册类.
     */
    protected function scanProviders(): array
    {
        $config = $this->config;
        $providers = addon_path($config['base_path'], 'Providers');
        if (!is_dir($providers)) {
            return [];
        }
        $files = array_diff(scandir($providers), ['.', '..', '.gitkeep']);
        foreach ($files as $item) {
            if (!Str::endsWith($item, '.php')) {
                continue;
            }
            $class = 'Addon\\'.$config['base_path'].'\\Providers\\'.str_replace('.php', '', $item);
            if (is_subclass_of($class, ServiceProvider::class)) {
                $this->providers[] = $class;
            }
        }

        return $this->providers ?? [];
    }
}
