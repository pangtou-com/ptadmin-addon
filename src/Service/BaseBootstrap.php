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

/**
 * 插件启动基类.
 */
abstract class BaseBootstrap
{
    /** @var array 管理后台菜单目录 */
    // ['name' => ''， 'title' => '', 'icon' => '', 'route' => '', 'type' => '', 'is_nav' => 1, 'weight' => 99, 'note' => '']
    public array $admin_menu = [];

    /** @var string|null 管理后台的父级菜单，当父级菜单不存在时则默认为插件为父级菜单 */
    public ?string $admin_parent_menu = null;

    /**
     * 返回后台资源定义。
     *
     * 默认根据 `$admin_menu` 和 `$admin_parent_menu` 生成资源树，
     * 插件也可以按需重写该方法输出更细粒度的资源定义。
     *
     * @param string               $addonCode
     * @param array<string, mixed> $addonInfo
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAdminResourceDefinitions(string $addonCode, array $addonInfo = array()): array
    {
        $menu = \is_array($this->admin_menu) ? $this->admin_menu : array();
        if ([] === $menu) {
            return array();
        }

        $module = isset($addonInfo['module']) && '' !== (string) $addonInfo['module']
            ? (string) $addonInfo['module']
            : $addonCode;
        $definitions = array();
        $parentCode = null;

        if (\is_string($this->admin_parent_menu) && '' !== trim($this->admin_parent_menu)) {
            $parentCode = trim($this->admin_parent_menu);
        } else {
            $parentCode = $addonCode;
            $definitions[] = array(
                'code' => $parentCode,
                'name' => (string) ($addonInfo['title'] ?? $addonInfo['name'] ?? $addonCode),
                'type' => 'dir',
                'module' => $module,
                'page_key' => null,
                'addon_code' => $addonCode,
                'is_nav' => 1,
                'status' => 1,
                'sort' => 0,
                'meta_json' => array(
                    'note' => (string) ($addonInfo['description'] ?? ''),
                    'controller' => '',
                ),
            );
        }

        return array_merge($definitions, $this->buildChildResourceDefinitions($menu, $addonCode, $parentCode, $module, $addonCode));
    }

    /**
     * 返回后台仪表盘组件定义。
     *
     * 第一阶段仅负责组件注册定义，不参与实际数据查询。
     * 查询统一由 `ptadmin/admin` 聚合并调度到插件侧处理器。
     *
     * @param string               $addonCode
     * @param array<string, mixed> $addonInfo
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAdminDashboardWidgetDefinitions(string $addonCode, array $addonInfo = array()): array
    {
        return array();
    }

    /**
     * 返回后台前端模块定义。
     *
     * 当前能力用于前后端分离场景下的插件前端引导。
     * 后台会统一收集所有插件声明的模块信息，并提供给前端启动阶段读取。
     *
     * 推荐插件在 `manifest.json` 中使用如下结构声明：
     *
     * `frontend.admin.entry`   后台前端模块入口
     * `frontend.admin.preload` 是否在后台启动时预加载
     * `frontend.admin.cache`   是否启用前端缓存，或提供更细粒度缓存配置
     *
     * 也可以在插件中重写本方法，根据运行时状态动态返回。
     *
     * @param string               $addonCode
     * @param array<string, mixed> $addonInfo
     *
     * @return array<string, mixed>
     */
    public function getAdminFrontendModuleDefinition(string $addonCode, array $addonInfo = array()): array
    {
        $definition = array();

        if (isset($addonInfo['admin_frontend']) && \is_array($addonInfo['admin_frontend'])) {
            $definition = $addonInfo['admin_frontend'];
        } elseif (\is_array(data_get($addonInfo, 'frontend.admin'))) {
            $definition = (array) data_get($addonInfo, 'frontend.admin', array());
        }

        if ([] === $definition) {
            return array();
        }

        if (!isset($definition['code']) || '' === trim((string) $definition['code'])) {
            $definition['code'] = $addonCode;
        }

        if (!isset($definition['title']) || '' === trim((string) $definition['title'])) {
            $definition['title'] = (string) ($addonInfo['title'] ?? $addonInfo['name'] ?? $addonCode);
        }

        return $definition;
    }

    /**
     * 插件启用之前调用.
     */
    public function enable(): void
    {
    }

    /**
     * 插件禁用.
     */
    public function disable(): void
    {
    }

    /**
     * 注册模版指令.
     *
     * 插件应在此方法中使用代码注册指令。
     */
    public function registerDirectives(AddonDirectivesManage $manager): void
    {
    }

    /**
     * 注册插件能力注入.
     */
    public function registerInjects(AddonInjectsManage $manager): void
    {
    }

    /**
     * 注册插件 hooks.
     */
    public function registerHooks(AddonHooksManage $manager): void
    {
    }

    /**
     * @param array<int, array<string, mixed>> $menu
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildChildResourceDefinitions(array $menu, string $addonCode, string $parentCode, string $module, string $codePrefix): array
    {
        $definitions = array();

        foreach ($menu as $item) {
            if (!\is_array($item)) {
                continue;
            }

            $name = trim((string) ($item['name'] ?? $item['code'] ?? ''));
            if ('' === $name) {
                continue;
            }

            $code = isset($item['code']) && '' !== trim((string) $item['code'])
                ? trim((string) $item['code'])
                : $codePrefix.'.'.$name;

            $definitions[] = array(
                'code' => $code,
                'name' => (string) ($item['title'] ?? $name),
                'type' => (string) ($item['type'] ?? 'nav'),
                'module' => $module,
                'page_key' => $this->resolvePageKeyFromMenuItem($item),
                'addon_code' => $addonCode,
                'parent' => $parentCode,
                'path' => $item['path'] ?? null,
                'route' => $item['route'] ?? null,
                'icon' => $item['icon'] ?? null,
                'is_nav' => isset($item['is_nav']) ? (int) $item['is_nav'] : 1,
                'status' => isset($item['status']) ? (int) $item['status'] : 1,
                'sort' => isset($item['weight']) ? (int) $item['weight'] : 0,
                'meta_json' => array(
                    'note' => (string) ($item['note'] ?? ''),
                    'controller' => (string) ($item['controller'] ?? ''),
                ),
            );

            if (isset($item['children']) && \is_array($item['children']) && [] !== $item['children']) {
                $definitions = array_merge(
                    $definitions,
                    $this->buildChildResourceDefinitions($item['children'], $addonCode, $code, $module, $code)
                );
            }
        }

        return $definitions;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function resolvePageKeyFromMenuItem(array $item): ?string
    {
        $type = (string) ($item['type'] ?? 'nav');
        if (!\in_array($type, ['nav', 'link'], true)) {
            return null;
        }

        $pageKey = trim((string) ($item['page_key'] ?? $item['name'] ?? ''));

        return '' === $pageKey ? null : $pageKey;
    }
}
