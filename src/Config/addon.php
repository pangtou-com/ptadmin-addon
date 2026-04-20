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
    | 插件前端模板拉取配置。
    | region=auto 时会根据当前 locale / timezone 推断优先区域：
    | - cn      优先 gitee
    | - global  优先 github
    |
    | 当主源拉取失败后，会自动回退到 official。
    |
    */
    'frontend_templates' => [
        'default_template' => env('PTADMIN_ADDON_FRONTEND_TEMPLATE', 'module'),
        'region' => env('PTADMIN_ADDON_FRONTEND_REGION', 'auto'),
        'primary_sources' => [
            'cn' => 'gitee',
            'global' => 'github',
        ],
        'templates' => [
            'module' => [
                'sources' => [
                    'gitee' => [
                        'archive_url' => env('PTADMIN_ADDON_FRONTEND_TEMPLATE_MODULE_GITEE_ARCHIVE_URL', ''),
                    ],
                    'github' => [
                        'archive_url' => env('PTADMIN_ADDON_FRONTEND_TEMPLATE_MODULE_GITHUB_ARCHIVE_URL', 'https://github.com/pangtou-com/ptadmin-addon-module/archive/refs/heads/{ref}.zip'),
                    ],
                    'official' => [
                        'archive_url' => env('PTADMIN_ADDON_FRONTEND_TEMPLATE_MODULE_OFFICIAL_ARCHIVE_URL', env('PTADMIN_ADDON_FRONTEND_OFFICIAL_ARCHIVE_URL', '')),
                    ],
                ],
            ],
            'micro-app' => [
                'sources' => [
                    'gitee' => [
                        'archive_url' => env('PTADMIN_ADDON_FRONTEND_TEMPLATE_MICRO_APP_GITEE_ARCHIVE_URL', ''),
                    ],
                    'github' => [
                        'archive_url' => env('PTADMIN_ADDON_FRONTEND_TEMPLATE_MICRO_APP_GITHUB_ARCHIVE_URL', 'https://github.com/pangtou-com/ptadmin-addon-micro-app/archive/refs/heads/{ref}.zip'),
                    ],
                    'official' => [
                        'archive_url' => env('PTADMIN_ADDON_FRONTEND_TEMPLATE_MICRO_APP_OFFICIAL_ARCHIVE_URL', env('PTADMIN_ADDON_FRONTEND_OFFICIAL_ARCHIVE_URL', '')),
                    ],
                ],
            ],
        ],
        'sources' => [
            'gitee' => [
                'archive_url' => env('PTADMIN_ADDON_FRONTEND_GITEE_ARCHIVE_URL', ''),
            ],
            'github' => [
                'archive_url' => env('PTADMIN_ADDON_FRONTEND_GITHUB_ARCHIVE_URL', ''),
            ],
            'official' => [
                'archive_url' => env('PTADMIN_ADDON_FRONTEND_OFFICIAL_ARCHIVE_URL', ''),
            ],
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
