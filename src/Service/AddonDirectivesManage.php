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

    /** @var array 代码注册的指令定义 */
    private $definitions = [];

    /** @var array 已完成引导的插件 */
    private $booted = [];

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
     * 注册插件指令定义.
     *
     * @param string                               $addonCode
     * @param DirectiveDefinition|array|string     $directive
     *
     * @return $this
     */
    public function register(string $addonCode, $directive): self
    {
        if (!$directive instanceof DirectiveDefinition) {
            $directive = $this->normalizeDirectiveDefinition($directive);
        }

        $this->definitions[$addonCode][$directive->getName()] = $directive->toArray();
        unset($this->provider[$addonCode]);

        return $this;
    }

    /**
     * 反注册插件指令.
     *
     * @param string      $addonCode
     * @param string|null $name
     */
    public function unregister(string $addonCode, ?string $name = null): void
    {
        if (null === $name) {
            unset($this->definitions[$addonCode], $this->provider[$addonCode], $this->booted[$addonCode]);

            return;
        }
        unset($this->definitions[$addonCode][$name], $this->provider[$addonCode]);
    }

    /**
     * 重置注册中心状态.
     *
     * @param string|null $addonCode
     */
    public function reset($addonCode = null): void
    {
        if (null === $addonCode) {
            $this->provider = [];
            $this->definitions = [];
            $this->booted = [];

            return;
        }

        unset($this->provider[$addonCode], $this->definitions[$addonCode], $this->booted[$addonCode]);
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
        $addon = new AddonDirectives($name, $this->getDirectives($name));
        $this->provider[$name] = $addon;

        return $addon;
    }

    public function getDirectives(string $addonCode): array
    {
        $this->bootstrap($addonCode);

        return $this->definitions[$addonCode] ?? [];
    }

    public function getAll(): array
    {
        $data = [];
        foreach (array_keys(Addon::getAddons()) as $addonCode) {
            $data[$addonCode] = $this->getDirectives($addonCode);
        }

        return $data;
    }

    /**
     * 懒加载插件中的代码指令注册.
     *
     * @param string $addonCode
     */
    private function bootstrap(string $addonCode): void
    {
        if (isset($this->booted[$addonCode])) {
            return;
        }
        $this->booted[$addonCode] = true;

        $bootstrap = Addon::getAddonBootstrap($addonCode);
        if (null === $bootstrap || !method_exists($bootstrap, 'registerDirectives')) {
            return;
        }

        $bootstrap->registerDirectives($this);
    }

    /**
     * 统一转换为代码注册指令定义对象.
     *
     * @param array|string $directive
     */
    private function normalizeDirectiveDefinition($directive): DirectiveDefinition
    {
        if (\is_string($directive)) {
            return DirectiveDefinition::make(AddonDirectives::DEFAULT_METHOD)->handler($directive);
        }
        if (!\is_array($directive)) {
            throw new \InvalidArgumentException(__('ptadmin-addon::messages.definition.directive_invalid'));
        }

        $definition = DirectiveDefinition::make($directive['name'] ?? $directive['method'] ?? AddonDirectives::DEFAULT_METHOD);
        if (isset($directive['title'])) {
            $definition->title($directive['title']);
        }
        if (isset($directive['class'])) {
            $definition->handler($directive['class']);
        }
        if (isset($directive['method'])) {
            $definition->method($directive['method']);
        }
        if (isset($directive['type'])) {
            $definition->type($directive['type']);
        }
        if (isset($directive['cache'])) {
            $definition->cacheable(false !== (bool) $directive['cache']);
        }

        return $definition;
    }
}
