/**
 * 顶部导航栏
 */
export function getNavBar() {
    return [
        { text: '指南', link: '/guide/', activeMatch: '/guide/' },
        { text: 'API', link: '/api/', activeMatch: '/api/' },
        { text: '示例', link: '/examples/', activeMatch: '/examples/' },
        { text: 'PTAdmin官网', link: 'https://www.pangtou.com' },
    ]
}
