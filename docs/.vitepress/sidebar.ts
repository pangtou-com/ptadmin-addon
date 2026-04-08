/**
 * 指引左侧菜单
 */
export function getSideBarGuide() {
    return [
        { text: '概览', link: '/guide/index.md' },
        { text: '快速开始', link: '/guide/quick-start.md' },
        { text: 'Manifest', link: '/guide/manifest.md' },
        { text: '生命周期', link: '/guide/lifecycle.md' },
        { text: '插件样板', link: '/guide/plugin-starter.md' },
    ]
}

export function getSideBarApi() {
    return [
        { text: '概览', link: '/api/index.md' },
        { text: '命令行', link: '/api/commands.md' },
        { text: 'Facade API', link: '/api/facade.md' },
        { text: '能力接口', link: '/api/contracts.md' },
        {
            text: '运行期注册',
            collapsible: true,
            items: [
                { text: 'Bootstrap 与 Installer', link: '/api/runtime.md' },
            ],
        },
    ]
}

export function getSideBarExamples() {
    return [
        { text: '概览', link: '/examples/index.md' },
        { text: '常见场景', link: '/examples/common-scenarios.md' },
    ]
}
