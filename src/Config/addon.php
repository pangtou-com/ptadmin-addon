<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Marketplace
    |--------------------------------------------------------------------------
    |
    | 插件云平台入口地址。默认指向官方平台，开发或私有部署场景下
    | 可通过环境变量覆盖。
    |
    */
    'marketplace' => [
        'base_url' => env('PTADMIN_ADDON_MARKETPLACE_BASE_URL', 'https://www.pangtou.com/api-addon/'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Frontend Templates
    |--------------------------------------------------------------------------
    |
    | 插件前端模板拉取配置。当前仅支持 official 源：
    | - module    使用 template-plugin-module.json
    | - micro-app 使用 template-plugin-micro-app.json
    |
    | 拉取时先读取模板清单 manifest_url，再按 latest/ref 解析最终 zip 下载地址。
    |
    */
    'frontend_templates' => [
        'default_template' => env('PTADMIN_ADDON_FRONTEND_TEMPLATE', 'module'),
        'manifest' => [
            'module' => [
                'route_base' => env('PTADMIN_ADDON_FRONTEND_MODULE_ROUTE_BASE', '/{code}'),
                'remote_name' => env('PTADMIN_ADDON_FRONTEND_MODULE_REMOTE_NAME', '{code_snake}_remote'),
                'develop_entry' => env('PTADMIN_ADDON_FRONTEND_MODULE_DEVELOP_ENTRY', 'http://localhost:4179/assets/remoteEntry.js'),
                'deploy_entry' => env('PTADMIN_ADDON_FRONTEND_MODULE_DEPLOY_ENTRY', '{app_url}/{admin_web_prefix}/modules/{code}/dist/admin/assets/remoteEntry.js'),
                'expose' => env('PTADMIN_ADDON_FRONTEND_MODULE_EXPOSE', './module'),
            ],
            'micro-app' => [
                'route_base' => env('PTADMIN_ADDON_FRONTEND_MICRO_APP_ROUTE_BASE', '/{code}'),
                'app_name' => env('PTADMIN_ADDON_FRONTEND_MICRO_APP_NAME', '{code_snake}'),
                'develop_url' => env('PTADMIN_ADDON_FRONTEND_MICRO_APP_DEVELOP_URL', 'http://localhost:5182/'),
                'deploy_url' => env('PTADMIN_ADDON_FRONTEND_MICRO_APP_DEPLOY_URL', '{app_url}/{admin_web_prefix}/modules/{code}/dist/admin/'),
            ],
        ],
        'templates' => [
            'module' => [
                'sources' => [
                    'official' => [
                        'manifest_url' => env('PTADMIN_ADDON_FRONTEND_TEMPLATE_MODULE_OFFICIAL_MANIFEST_URL', 'https://cloud.api.pangtou.com/storage/starter/template-plugin-module.json'),
                        'archive_url' => env('PTADMIN_ADDON_FRONTEND_TEMPLATE_MODULE_OFFICIAL_ARCHIVE_URL', env('PTADMIN_ADDON_FRONTEND_OFFICIAL_ARCHIVE_URL', '')),
                    ],
                ],
            ],
            'micro-app' => [
                'sources' => [
                    'official' => [
                        'manifest_url' => env('PTADMIN_ADDON_FRONTEND_TEMPLATE_MICRO_APP_OFFICIAL_MANIFEST_URL', 'https://cloud.api.pangtou.com/storage/starter/template-plugin-micro-app.json'),
                        'archive_url' => env('PTADMIN_ADDON_FRONTEND_TEMPLATE_MICRO_APP_OFFICIAL_ARCHIVE_URL', env('PTADMIN_ADDON_FRONTEND_OFFICIAL_ARCHIVE_URL', '')),
                    ],
                ],
            ],
        ],
        'sources' => [
            'official' => [
                'manifest_url' => env('PTADMIN_ADDON_FRONTEND_OFFICIAL_MANIFEST_URL', ''),
                'archive_url' => env('PTADMIN_ADDON_FRONTEND_OFFICIAL_ARCHIVE_URL', ''),
            ],
        ],
        'curl_resolve' => array_values(array_filter(array_map('trim', explode(',', env(
            'PTADMIN_ADDON_FRONTEND_CURL_RESOLVE',
            'cloud.api.pangtou.com:443:61.147.93.222'
        ))))),
    ],

    /*
    |--------------------------------------------------------------------------
    | Host Versions
    |--------------------------------------------------------------------------
    |
    | 当宿主核心包版本无法通过 Composer 解析时，可在此显式声明版本。
    | 例如：
    | [
    |     'ptadmin/admin' => '1.2.0',
    |     'ptadmin/base' => '1.2.0',
    | ]
    |
    */
    'host_versions' => [],

    /*
    |--------------------------------------------------------------------------
    | Default Capability Addons
    |--------------------------------------------------------------------------
    |
    | 能力型插件的默认实现。
    | 例如：
    | [
    |     'payment' => 'wechatpay',
    | ]
    |
    */
    'defaults' => [
        'payment' => null,
    ],
];
