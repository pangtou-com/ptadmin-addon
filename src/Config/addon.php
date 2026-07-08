<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Template Directives
    |--------------------------------------------------------------------------
    |
    | 模板指令默认使用短语法：@pt:method。
    | 当多个插件声明了同名 method 时，可通过 default_addon 固定解析目标。
    |
    */
    'directives' => [
        'default_addon' => env('PTADMIN_ADDON_DEFAULT_DIRECTIVE_ADDON', null),
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
                'deploy_entry' => env('PTADMIN_ADDON_FRONTEND_MODULE_DEPLOY_ENTRY', '/{admin_web_prefix}/modules/{code}/dist/assets/remoteEntry.js'),
                'expose' => env('PTADMIN_ADDON_FRONTEND_MODULE_EXPOSE', './module'),
            ],
            'micro-app' => [
                'route_base' => env('PTADMIN_ADDON_FRONTEND_MICRO_APP_ROUTE_BASE', '/{code}'),
                'app_name' => env('PTADMIN_ADDON_FRONTEND_MICRO_APP_NAME', '{code_snake}'),
                'develop_url' => env('PTADMIN_ADDON_FRONTEND_MICRO_APP_DEVELOP_URL', 'http://localhost:5182/'),
                'deploy_url' => env('PTADMIN_ADDON_FRONTEND_MICRO_APP_DEPLOY_URL', '/{admin_web_prefix}/modules/{code}/dist/'),
            ],
        ],
        'templates' => [
            'module' => [
                'sources' => [
                    'official' => [
                        'manifest_url' => 'https://cloud.api.pangtou.com/storage/starter/template-plugin-module.json',
                        'archive_url' => '',
                    ],
                ],
            ],
            'micro-app' => [
                'sources' => [
                    'official' => [
                        'manifest_url' => 'https://cloud.api.pangtou.com/storage/starter/template-plugin-micro-app.json',
                        'archive_url' => '',
                    ],
                ],
            ],
        ],
        'sources' => [
            'official' => [
                'manifest_url' => '',
                'archive_url' => '',
            ],
        ],
        'curl_resolve' => [
            'cloud.api.pangtou.com:443:61.147.93.222',
        ],
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
