/**
 * 指引左侧菜单
 */
export function getSideBarGuide() {
    return [
        { text: 'PTAdmin\/Easy?', link: '/guide/index.md' },
        { text: '安装', link: '/guide/install.md' },
        { text: '示例', link: '/guide/example.md' },
        {
            text: 'API',
            collapsible: true,
            items: [
                { text: '模型管理', link: '/guide/mod.md' },
                { text: '模型字段管理', link: '/guide/field.md' },
                { text: '数据处理', link: '/guide/handler.md' },
                { text: '渲染处理', link: '/guide/render.md' },
            ],
        },
        { text: '配置说明', link: '/guide/config.md' },
        { text: '开发计划', link: '/guide/plan.md' },
    ]
}

/**
 * 扩展模块左侧菜单
 */
export function getSideBarExtend() {
    return [
        { text: '介绍', link: '/extend/index.md' },
        {
            text: '模型扩展',
            collapsible: true,
            items: [
                { text: '模型字段扩展', link: '/extend/mod.md' },
                { text: '模型事件处理', link: '/extend/field.md' },
            ],
        },
        {
            text: '组件扩展',
            collapsible: true,
            items: [
                { text: '自定义组件', link: '/extend/mod.md' },
                { text: '组件规则', link: '/extend/field.md' },
            ],
        },
    ]
}