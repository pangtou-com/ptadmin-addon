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

/**
 * PTAdmin 插件模版指令管理器.
 */
class AddonDirectivesManage
{
    // 实例
    private static $instance;

    // 插件指令集合
    private $addon_maps = [];

    /** @var array 指令实例集合 */
    private $provider = [];

    private function __construct()
    {
    }

    public static function getInstance(): self
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * 插入指令数据.会覆盖已有的数据.
     *
     * @param mixed $addonName 插件名称
     * @param mixed $data      插件指令内容
     *                         名称
     *                         自定义方法
     *                         是否需要对结果缓存，默认情况下由指令调用管理缓存内容，如果插件设置则代表插件自行管理缓存，会将用户传入的参数传递给插件
     *                         方法的类型，一般情况下会指令会编译为foreach循环语句，当为true时则编译为if判断语句
     *
     * @return $this
     */
    public function insert($addonName, $data): self
    {
        // 如果已经存在则合并
        if ($this->has($addonName)) {
            return $this->mergeAddon($addonName, $data);
        }
        $this->addon_maps[$addonName] = $data;

        return $this;
    }

    /**
     * 判断是否存在指令.
     *
     * @param string $name 指令名称
     *
     * @return bool
     */
    public function has(string $name): bool
    {
        return isset($this->addon_maps[$name]);
    }

    /**
     * 判断是否为循环指令.
     *
     * @param mixed $name
     * @param $method
     *
     * @return bool
     */
    public function isLoop($name, $method): bool
    {
        $provider = $this->getProvider($name);

        return $provider->isLoop($method);
    }

    /**
     * 获取指令信息.
     *
     * @param $name
     *
     * @return mixed
     */
    public function getAddon($name)
    {
        return $this->addon_maps[$name] ?? null;
    }

    /**
     * 获取指令提供者.
     *
     * @param $name
     *
     * @return null|AddonDirectives
     */
    public function getProvider($name): ?AddonDirectives
    {
        if (isset($this->provider[$name])) {
            return $this->provider[$name];
        }

        return $this->resolveProvider($name);
    }

    /**
     * 实例化指令提供者.
     *
     * @param $name
     *
     * @return AddonDirectives
     */
    protected function resolveProvider($name): AddonDirectives
    {
        $addon = new AddonDirectives($name, $this->getAddon($name));
        $this->provider[$name] = $addon;

        return $addon;
    }

    /**
     * 合并指令数据.
     * TODO 还需要完善，考虑覆盖原方法的情况.
     *
     * @param $addonName
     * @param $data
     *
     * @return $this
     */
    protected function mergeAddon($addonName, $data): self
    {
        $old = $this->getAddon($addonName) ?? [];
        $this->addon_maps[$addonName] = array_merge(Arr::wrap($old), Arr::wrap($data));

        return $this;
    }
}
