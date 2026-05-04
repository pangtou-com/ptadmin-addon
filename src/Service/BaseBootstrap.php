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

use PTAdmin\Addon\Exception\AddonException;

/**
 * 插件启动基类.
 */
abstract class BaseBootstrap
{
    /**
     * 管理后台资源定义。
     *
     * `admin_menu` 现在直接使用资源服务协议，不再是旧菜单协议。
     *
     * 推荐最小结构：
     *
     * ```php
     * public array $admin_menu = [
     *     [
     *         'name' => 'test.dashboard',
     *         'title' => '测试概览',
     *         'type' => 'nav',
     *         'module' => 'test',
     *         'page_key' => 'test.page.home',
     *         'addon_code' => 'test',
     *         'parent' => 'test',
     *         'route' => '/test',
     *         'icon' => 'HomeFilled',
     *         'is_nav' => 1,
     *         'status' => 1,
     *         'sort' => 10,
     *         'meta' => [
     *             'note' => '测试插件后台入口',
     *             'keep_alive' => 1,
     *             'hidden' => 0,
     *         ],
     *     ],
     * ];
     * ```
     *
     * 协议要点：
     * - `name` 是资源唯一标识，不是显示名称
     * - `title` 是显示名称
     * - `type` 仅支持：`dir`、`nav`、`link`、`btn`、`field`
     * - `nav/link` 必须包含：`module`、`page_key`、`route`
     * - `btn/field` 必须包含：`module`
     * - 菜单型子资源的父级必须是 `dir`
     * - 推荐统一使用 `parent => 父资源name`
     * - 当 `admin_parent_menu = null` 且未显式声明根资源时，会自动补一个插件根目录资源
     *
     * @var array<int, array<string, mixed>>
     */
    public array $admin_menu = [];

    /** @var string|null 管理后台的父级菜单，当父级菜单不存在时则默认为插件为父级菜单 */
    public ?string $admin_parent_menu = null;

    /**
     * 返回后台资源定义。
     *
     * 默认直接返回 `admin_menu` 中声明的资源协议，并在需要时自动补齐插件根目录资源。
     * 如果插件有更复杂的资源结构，也可以按需重写该方法，直接返回标准资源协议数组。
     *
     * 该方法返回的数据会被以下链路直接消费：
     * - 安装时资源同步
     * - 启用时资源同步
     * - `addon:resources:sync`
     * - 开发态 `auth/resources` 动态合并
     *
     * 因此这里的定义必须严格满足资源服务协议。
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

        $parentCode = null;
        $definitions = $this->flattenResourceDefinitions($menu, $addonCode, $addonInfo);

        if (\is_string($this->admin_parent_menu) && '' !== trim($this->admin_parent_menu)) {
            $parentCode = trim($this->admin_parent_menu);
        } else {
            $parentCode = $addonCode;
            if (!$this->hasResourceDefinition($definitions, $parentCode)) {
                array_unshift($definitions, array(
                    'name' => $parentCode,
                    'title' => (string) ($addonInfo['title'] ?? $addonInfo['name'] ?? $addonCode),
                    'type' => 'dir',
                    'addon_code' => $addonCode,
                    'is_nav' => 1,
                    'status' => 1,
                    'sort' => 0,
                    'meta' => array(
                        'note' => (string) ($addonInfo['description'] ?? ''),
                        'controller' => '',
                        'hidden' => 0,
                        'keep_alive' => 0,
                    ),
                ));
            }
        }

        foreach ($definitions as &$definition) {
            if (
                $definition['name'] !== $parentCode
                && (!isset($definition['parent']) || '' === trim((string) $definition['parent']))
                && null !== $parentCode
            ) {
                $definition['parent'] = $parentCode;
            }
        }
        unset($definition);

        $this->validateResourceDefinitions($definitions, $addonCode);

        return array_values($definitions);
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

    private function hasResourceDefinition(array $definitions, string $name): bool
    {
        foreach ($definitions as $definition) {
            if (\is_array($definition) && $name === trim((string) ($definition['name'] ?? ''))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, array<string, mixed>> $definitions
     *
     * @return array<int, array<string, mixed>>
     */
    private function flattenResourceDefinitions(array $definitions, string $addonCode, array $addonInfo, ?string $inheritedParent = null): array
    {
        $results = array();

        foreach ($definitions as $item) {
            if (!\is_array($item)) {
                continue;
            }

            $name = trim((string) ($item['name'] ?? ''));
            if ('' === $name) {
                continue;
            }

            $definition = $item;
            unset($definition['children']);

            if (!isset($definition['title']) || '' === trim((string) $definition['title'])) {
                $definition['title'] = $name;
            }
            if (!isset($definition['addon_code']) || '' === trim((string) $definition['addon_code'])) {
                $definition['addon_code'] = $addonCode;
            }
            if (
                !isset($definition['parent'])
                && null !== $inheritedParent
                && $name !== $inheritedParent
            ) {
                $definition['parent'] = $inheritedParent;
            }
            if (
                !isset($definition['module'])
                && 'dir' !== (string) ($definition['type'] ?? 'nav')
                && isset($addonInfo['module'])
                && '' !== trim((string) $addonInfo['module'])
            ) {
                $definition['module'] = (string) $addonInfo['module'];
            }
            if (!isset($definition['sort']) && isset($definition['weight'])) {
                $definition['sort'] = (int) $definition['weight'];
            }
            if (!isset($definition['meta']) && !isset($definition['meta_json'])) {
                $meta = array();
                if (isset($definition['note'])) {
                    $meta['note'] = (string) $definition['note'];
                }
                if (isset($definition['controller'])) {
                    $meta['controller'] = (string) $definition['controller'];
                }
                if ([] !== $meta) {
                    $definition['meta'] = $meta;
                }
            }

            $results[] = $definition;

            if (isset($item['children']) && \is_array($item['children']) && [] !== $item['children']) {
                $results = array_merge(
                    $results,
                    $this->flattenResourceDefinitions($item['children'], $addonCode, $addonInfo, $name)
                );
            }
        }

        return $results;
    }

    /**
     * @param array<int, array<string, mixed>> $definitions
     */
    private function validateResourceDefinitions(array $definitions, string $addonCode): void
    {
        $allowedTypes = array('dir', 'nav', 'link', 'btn', 'field');
        $definitionMap = array();

        foreach ($definitions as $definition) {
            if (!\is_array($definition)) {
                continue;
            }

            $name = trim((string) ($definition['name'] ?? ''));
            $title = trim((string) ($definition['title'] ?? ''));
            $type = trim((string) ($definition['type'] ?? 'nav'));

            if ('' === $name) {
                throw new AddonException(__('ptadmin-addon::messages.definition.resource_name_required', [
                    'addon' => $addonCode,
                ]));
            }

            if ('' === $title) {
                throw new AddonException(__('ptadmin-addon::messages.definition.resource_title_required', [
                    'addon' => $addonCode,
                    'resource' => $name,
                ]));
            }

            if (!\in_array($type, $allowedTypes, true)) {
                throw new AddonException(__('ptadmin-addon::messages.definition.resource_type_invalid', [
                    'addon' => $addonCode,
                    'resource' => $name,
                    'type' => $type,
                ]));
            }

            $missingFields = array();
            if (\in_array($type, array('nav', 'link'), true)) {
                foreach (array('module', 'page_key', 'route') as $field) {
                    if (!isset($definition[$field]) || '' === trim((string) $definition[$field])) {
                        $missingFields[] = $field;
                    }
                }
            } elseif (\in_array($type, array('btn', 'field'), true)) {
                if (!isset($definition['module']) || '' === trim((string) $definition['module'])) {
                    $missingFields[] = 'module';
                }
            }

            if ([] !== $missingFields) {
                throw new AddonException(__('ptadmin-addon::messages.definition.resource_required_fields', [
                    'addon' => $addonCode,
                    'resource' => $name,
                    'type' => $type,
                    'fields' => implode('、', $missingFields),
                ]));
            }

            $definitionMap[$name] = $definition;
        }

        foreach ($definitionMap as $name => $definition) {
            $parent = isset($definition['parent']) ? trim((string) $definition['parent']) : '';
            if ('' !== $parent && !isset($definitionMap[$parent])) {
                throw new AddonException(__('ptadmin-addon::messages.definition.resource_parent_missing', [
                    'addon' => $addonCode,
                    'resource' => $name,
                    'parent' => $parent,
                ]));
            }
        }

        foreach ($definitionMap as $name => $definition) {
            $parent = isset($definition['parent']) ? trim((string) $definition['parent']) : '';
            if ('' === $parent || !isset($definitionMap[$parent])) {
                continue;
            }

            $childType = trim((string) ($definition['type'] ?? 'nav'));
            if ('btn' === $childType) {
                continue;
            }

            $parentType = trim((string) ($definitionMap[$parent]['type'] ?? 'nav'));
            if ('dir' !== $parentType) {
                throw new AddonException(__('ptadmin-addon::messages.definition.resource_parent_type_invalid', [
                    'addon' => $addonCode,
                    'resource' => $parent,
                    'child' => $name,
                    'type' => $parentType,
                ]));
            }
        }
    }
}
