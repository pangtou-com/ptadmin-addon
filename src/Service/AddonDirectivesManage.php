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

use PTAdmin\Addon\Addon;

/**
 * PTAdmin 插件模版指令管理器.
 */
class AddonDirectivesManage
{
    // 实例
    private static $instance;

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
     * 判断是否为循环指令.
     *
     * @param mixed $name
     * @param $method
     *
     * @return bool
     */
    public function isLoop($name, $method): bool
    {
        return $this->getProvider($name)->isLoop($method);
    }

    /**
     * 获取指令提供者.
     *
     * @param $name
     *
     * @return AddonDirectives
     */
    public function getProvider($name): AddonDirectives
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
        $addon = new AddonDirectives($name, Addon::getDirective($name));
        $this->provider[$name] = $addon;

        return $addon;
    }
}
