<?php

declare(strict_types=1);

namespace PTAdmin\Addon\Commands;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class AddonInit extends BaseAddonCommand
{
    protected $signature = 'addon:init {code : 插件编码} {--title= : 插件标题} {--f|force : 强制覆盖已存在目录}';
    protected $description = '初始化插件开发脚手架';

    public function handle(): int
    {
        $code = strtolower((string) $this->argument('code'));
        $title = (string) ($this->option('title') ?: Str::headline($code));
        $basePath = Str::studly($code);
        $target = base_path('addons'.\DIRECTORY_SEPARATOR.$basePath);
        $force = (bool) $this->option('force');
        $filesystem = new Filesystem();

        if ($filesystem->isDirectory($target)) {
            if (!$force) {
                $this->error(__('ptadmin-addon::messages.command.init_exists', ['path' => $target]));

                return 1;
            }
            $filesystem->deleteDirectory($target);
        }

        $this->createDirectories($filesystem, $target);
        $this->writeManifest($filesystem, $target, $code, $title, $basePath);
        $this->writeInstaller($filesystem, $target, $basePath);
        $this->writeBootstrap($filesystem, $target, $basePath, $title, $code);
        $this->writeProvider($filesystem, $target, $basePath);
        $this->writeFunctions($filesystem, $target);
        $this->writeRoutes($filesystem, $target, $code);
        $this->writeConfig($filesystem, $target, $code, $title);
        $this->writeModel($filesystem, $target, $basePath);
        $this->writeService($filesystem, $target, $basePath, $title, $code);
        $this->writeDashboardWidget($filesystem, $target, $basePath, $title, $code);
        $this->writeControllers($filesystem, $target, $basePath, $title, $code);
        $this->writeViews($filesystem, $target, $title, $code);
        $this->writeReadme($filesystem, $target, $title, $basePath);
        $this->writeGitkeep($filesystem, $target.\DIRECTORY_SEPARATOR.'Assets'.\DIRECTORY_SEPARATOR.'.gitkeep');
        $this->writeGitkeep($filesystem, $target.\DIRECTORY_SEPARATOR.'Response'.\DIRECTORY_SEPARATOR.'Lang'.\DIRECTORY_SEPARATOR.'.gitkeep');
        $this->writeGitkeep($filesystem, $target.\DIRECTORY_SEPARATOR.'Http'.\DIRECTORY_SEPARATOR.'Requests'.\DIRECTORY_SEPARATOR.'.gitkeep');
        $this->writeGitkeep($filesystem, $target.\DIRECTORY_SEPARATOR.'Database'.\DIRECTORY_SEPARATOR.'Migrations'.\DIRECTORY_SEPARATOR.'.gitkeep');
        $this->writeGitkeep($filesystem, $target.\DIRECTORY_SEPARATOR.'Database'.\DIRECTORY_SEPARATOR.'Seeders'.\DIRECTORY_SEPARATOR.'.gitkeep');

        $this->info(__('ptadmin-addon::messages.command.init_created', ['path' => $target]));

        return 0;
    }

    private function createDirectories(Filesystem $filesystem, string $target): void
    {
        $directories = [
            $target,
            $target.\DIRECTORY_SEPARATOR.'Routes',
            $target.\DIRECTORY_SEPARATOR.'Config',
            $target.\DIRECTORY_SEPARATOR.'Assets',
            $target.\DIRECTORY_SEPARATOR.'Database',
            $target.\DIRECTORY_SEPARATOR.'Database'.\DIRECTORY_SEPARATOR.'Migrations',
            $target.\DIRECTORY_SEPARATOR.'Database'.\DIRECTORY_SEPARATOR.'Seeders',
            $target.\DIRECTORY_SEPARATOR.'Dashboard',
            $target.\DIRECTORY_SEPARATOR.'Http',
            $target.\DIRECTORY_SEPARATOR.'Http'.\DIRECTORY_SEPARATOR.'Controllers',
            $target.\DIRECTORY_SEPARATOR.'Http'.\DIRECTORY_SEPARATOR.'Controllers'.\DIRECTORY_SEPARATOR.'Admin',
            $target.\DIRECTORY_SEPARATOR.'Http'.\DIRECTORY_SEPARATOR.'Controllers'.\DIRECTORY_SEPARATOR.'Api',
            $target.\DIRECTORY_SEPARATOR.'Http'.\DIRECTORY_SEPARATOR.'Controllers'.\DIRECTORY_SEPARATOR.'Home',
            $target.\DIRECTORY_SEPARATOR.'Http'.\DIRECTORY_SEPARATOR.'Requests',
            $target.\DIRECTORY_SEPARATOR.'Models',
            $target.\DIRECTORY_SEPARATOR.'Providers',
            $target.\DIRECTORY_SEPARATOR.'Response'.\DIRECTORY_SEPARATOR.'Views',
            $target.\DIRECTORY_SEPARATOR.'Response'.\DIRECTORY_SEPARATOR.'Views'.\DIRECTORY_SEPARATOR.'home',
            $target.\DIRECTORY_SEPARATOR.'Response'.\DIRECTORY_SEPARATOR.'Views'.\DIRECTORY_SEPARATOR.'ptadmin',
            $target.\DIRECTORY_SEPARATOR.'Response'.\DIRECTORY_SEPARATOR.'Lang',
            $target.\DIRECTORY_SEPARATOR.'Service',
        ];

        foreach ($directories as $directory) {
            $filesystem->ensureDirectoryExists($directory);
        }
    }

    private function writeManifest(Filesystem $filesystem, string $target, string $code, string $title, string $basePath): void
    {
        $manifest = [
            'id' => $code,
            'code' => $code,
            'name' => $title,
            'version' => '1.0.0',
            'develop' => true,
            'type' => 'module',
            'description' => $title,
            'authors' => [
                [
                    'name' => 'PTAdmin',
                    'email' => 'vip@pangtou.com',
                ],
            ],
            'compatibility' => [
                'php' => '>=7.4',
            ],
            'providers' => [
                'Addon\\'.$basePath.'\\Providers\\'.$basePath.'ServiceProvider',
            ],
            'entry' => [
                'installer' => 'Addon\\'.$basePath.'\\Installer',
                'bootstrap' => 'Addon\\'.$basePath.'\\Bootstrap',
            ],
            'resources' => [
                'assets' => './Assets',
                'routes' => './Routes',
                'views' => './Response/Views',
                'lang' => './Response/Lang',
                'config' => './Config',
                'functions' => './functions.php',
            ],
        ];

        $filesystem->put(
            $target.\DIRECTORY_SEPARATOR.'manifest.json',
            (string) json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

    private function writeInstaller(Filesystem $filesystem, string $target, string $basePath): void
    {
        $filesystem->put($target.\DIRECTORY_SEPARATOR.'Installer.php', <<<PHP
<?php

declare(strict_types=1);

namespace Addon\\{$basePath};

use PTAdmin\\Addon\\Service\\BaseInstaller;

class Installer extends BaseInstaller
{
}
PHP
        );
    }

    private function writeBootstrap(Filesystem $filesystem, string $target, string $basePath, string $title, string $code): void
    {
        $filesystem->put($target.\DIRECTORY_SEPARATOR.'Bootstrap.php', <<<PHP
<?php

declare(strict_types=1);

namespace Addon\\{$basePath};

use Addon\\{$basePath}\\Dashboard\\{$basePath}OverviewWidget;
use PTAdmin\\Addon\\Service\\AddonDirectivesManage;
use PTAdmin\\Addon\\Service\\AddonHooksManage;
use PTAdmin\\Addon\\Service\\AddonInjectsManage;
use PTAdmin\\Addon\\Service\\BaseBootstrap;

class Bootstrap extends BaseBootstrap
{
    public \$admin_parent_menu = null;

    public \$admin_menu = [
        [
            'name' => 'dashboard',
            'title' => '{$basePath}概览',
            'icon' => 'layui-icon-home',
            'route' => '/{$code}',
            'type' => 'nav',
            'is_nav' => 1,
            'weight' => 0,
            'note' => '{$basePath} 插件后台入口',
        ],
    ];

    /**
     * 返回插件后台仪表盘组件定义。
     *
     * 后台会统一收集所有插件注册的组件定义，
     * 再按需调用 `query_handler` 查询实时数据。
     *
     * @param string               \$addonCode
     * @param array<string, mixed> \$addonInfo
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAdminDashboardWidgetDefinitions(string \$addonCode, array \$addonInfo = array()): array
    {
        return array(
            array(
                'code' => '{$code}.overview',
                'title' => '{$title}概览',
                'type' => 'stats',
                'group' => 'overview',
                'icon' => 'layui-icon-chart',
                'sort' => 100,
                'resource_code' => '{$code}.dashboard',
                'description' => '{$title} 后台概览组件',
                'default_query' => array(
                    'range' => 'all',
                ),
                'capabilities' => array(
                    'refresh' => true,
                    'range' => false,
                    'filters' => false,
                    'drilldown' => false,
                ),
                'actions' => array(
                    array(
                        'code' => 'open_dashboard',
                        'label' => '进入后台',
                        'type' => 'link',
                        'target' => '/{$code}',
                    ),
                    array(
                        'code' => 'refresh_overview',
                        'label' => '刷新统计',
                        'type' => 'request',
                        'confirm_text' => '确认刷新当前插件统计吗？',
                        'meta' => array(
                            'intent' => 'refresh',
                        ),
                    ),
                ),
                'query_handler' => {$basePath}OverviewWidget::class,
                'cache_ttl' => 300,
            ),
        );
    }

    public function registerDirectives(AddonDirectivesManage \$manager): void
    {
    }

    public function registerInjects(AddonInjectsManage \$manager): void
    {
    }

    public function registerHooks(AddonHooksManage \$manager): void
    {
    }
}
PHP
        );
    }

    private function writeProvider(Filesystem $filesystem, string $target, string $basePath): void
    {
        $filesystem->put($target.\DIRECTORY_SEPARATOR.'Providers'.\DIRECTORY_SEPARATOR.$basePath.'ServiceProvider.php', <<<PHP
<?php

declare(strict_types=1);

namespace Addon\\{$basePath}\\Providers;

use Addon\\{$basePath}\\Service\\{$basePath}Service;
use PTAdmin\\Addon\\Providers\\BaseAddonService;

class {$basePath}ServiceProvider extends BaseAddonService
{
    protected \$addonCode = '{$this->normalizeCode($basePath)}';

    public function register(): void
    {
        \$this->app->singleton({$basePath}Service::class, {$basePath}Service::class);
    }
}
PHP
        );
    }

    private function writeFunctions(Filesystem $filesystem, string $target): void
    {
        $filesystem->put($target.\DIRECTORY_SEPARATOR.'functions.php', <<<PHP
<?php

declare(strict_types=1);
PHP
        );
    }

    private function writeRoutes(Filesystem $filesystem, string $target, string $code): void
    {
        $basePath = Str::studly($code);

        $filesystem->put($target.\DIRECTORY_SEPARATOR.'Routes'.\DIRECTORY_SEPARATOR.'admin.php', <<<PHP
<?php

declare(strict_types=1);

use Addon\\{$basePath}\\Http\\Controllers\\Admin\\{$basePath}Controller;
use Illuminate\Support\Facades\Route;
use PTAdmin\\Foundation\\Auth\\AdminAuth;

Route::group([
    'prefix' => admin_route_prefix().'/{$code}',
    'middleware' => ['ptadmin.auth:'.AdminAuth::getGuard()],
], function (): void {
    Route::get('/', [{$basePath}Controller::class, 'index']);
});
PHP
        );

        $filesystem->put($target.\DIRECTORY_SEPARATOR.'Routes'.\DIRECTORY_SEPARATOR.'api.php', <<<PHP
<?php

declare(strict_types=1);

use Addon\\{$basePath}\\Http\\Controllers\\Api\\{$basePath}Controller;
use Illuminate\Support\Facades\Route;

Route::prefix('api/{$code}')->group(function (): void {
    Route::get('/ping', [{$basePath}Controller::class, 'ping']);
});
PHP
        );

        $filesystem->put($target.\DIRECTORY_SEPARATOR.'Routes'.\DIRECTORY_SEPARATOR.'web.php', <<<PHP
<?php

declare(strict_types=1);

use Addon\\{$basePath}\\Http\\Controllers\\Home\\{$basePath}Controller;
use Illuminate\Support\Facades\Route;

Route::prefix('{$code}')->group(function (): void {
    Route::get('/', [{$basePath}Controller::class, 'index']);
});
PHP
        );
    }

    private function writeConfig(Filesystem $filesystem, string $target, string $code, string $title): void
    {
        $filesystem->put($target.\DIRECTORY_SEPARATOR.'Config'.\DIRECTORY_SEPARATOR.'config.php', <<<PHP
<?php

declare(strict_types=1);

return [
    'code' => '{$code}',
    'name' => '{$title}',
    'admin_route_prefix' => '{$code}',
    'api_route_prefix' => 'api/{$code}',
];
PHP
        );
    }

    private function writeModel(Filesystem $filesystem, string $target, string $basePath): void
    {
        $filesystem->put($target.\DIRECTORY_SEPARATOR.'Models'.\DIRECTORY_SEPARATOR.$basePath.'.php', <<<PHP
<?php

declare(strict_types=1);

namespace Addon\\{$basePath}\\Models;

use PTAdmin\\Foundation\\Database\\Models\\AbstractModel;

class {$basePath} extends AbstractModel
{
    protected \$table = '{$this->toSnakeTable($basePath)}_items';
}
PHP
        );
    }

    private function writeService(Filesystem $filesystem, string $target, string $basePath, string $title, string $code): void
    {
        $filesystem->put($target.\DIRECTORY_SEPARATOR.'Service'.\DIRECTORY_SEPARATOR.$basePath.'Service.php', <<<PHP
<?php

declare(strict_types=1);

namespace Addon\\{$basePath}\\Service;

class {$basePath}Service
{
    public function dashboard(): array
    {
        return [
            'code' => '{$code}',
            'name' => '{$title}',
            'status' => 'developing',
        ];
    }

    public function publicInfo(): array
    {
        return [
            'code' => '{$code}',
            'name' => '{$title}',
            'version' => '1.0.0',
        ];
    }
}
PHP
        );
    }

    private function writeDashboardWidget(Filesystem $filesystem, string $target, string $basePath, string $title, string $code): void
    {
        $serviceProperty = lcfirst($basePath).'Service';

        $filesystem->put($target.\DIRECTORY_SEPARATOR.'Dashboard'.\DIRECTORY_SEPARATOR.$basePath.'OverviewWidget.php', <<<PHP
<?php

declare(strict_types=1);

namespace Addon\\{$basePath}\\Dashboard;

use Addon\\{$basePath}\\Service\\{$basePath}Service;
use PTAdmin\\Contracts\\AdminDashboardWidgetActionHandlerInterface;
use PTAdmin\\Contracts\\AdminDashboardWidgetHandlerInterface;

class {$basePath}OverviewWidget implements AdminDashboardWidgetHandlerInterface, AdminDashboardWidgetActionHandlerInterface
{
    private {$basePath}Service \${$serviceProperty};

    public function __construct({$basePath}Service \${$serviceProperty})
    {
        \$this->{$serviceProperty} = \${$serviceProperty};
    }

    /**
     * @param array<string, mixed> \$query
     * @param array<string, mixed> \$definition
     * @param array<string, mixed> \$context
     *
     * @return array<string, mixed>
     */
    public function query(array \$query, array \$definition, array \$context = array()): array
    {
        \$dashboard = \$this->{$serviceProperty}->dashboard();
        \$publicInfo = \$this->{$serviceProperty}->publicInfo();

        return array(
            'type' => 'stats',
            'items' => array(
                array(
                    'code' => 'plugin_code',
                    'label' => '插件编码',
                    'value' => (string) (\$dashboard['code'] ?? '{$code}'),
                ),
                array(
                    'code' => 'version',
                    'label' => '当前版本',
                    'value' => (string) (\$publicInfo['version'] ?? '1.0.0'),
                ),
                array(
                    'code' => 'status',
                    'label' => '开发状态',
                    'value' => (string) (\$dashboard['status'] ?? 'developing'),
                ),
            ),
            'query' => \$query,
            'context' => \$context,
            'definition_code' => (string) (\$definition['code'] ?? '{$code}.overview'),
        );
    }

    /**
     * @param string               \$actionCode
     * @param array<string, mixed> \$payload
     * @param array<string, mixed> \$definition
     * @param array<string, mixed> \$context
     * @param array<string, mixed> \$actionDefinition
     *
     * @return array<string, mixed>
     */
    public function executeAction(string \$actionCode, array \$payload, array \$definition, array \$context = array(), array \$actionDefinition = array()): array
    {
        return array(
            'type' => 'action_result',
            'message' => '插件仪表盘动作执行完成',
            'action_code' => \$actionCode,
            'payload' => \$payload,
            'context' => \$context,
            'action' => array(
                'code' => (string) (\$actionDefinition['code'] ?? \$actionCode),
                'label' => (string) (\$actionDefinition['label'] ?? ''),
            ),
        );
    }
}
PHP
        );
    }

    private function writeControllers(Filesystem $filesystem, string $target, string $basePath, string $title, string $code): void
    {
        $serviceProperty = lcfirst($basePath).'Service';

        $filesystem->put($target.\DIRECTORY_SEPARATOR.'Http'.\DIRECTORY_SEPARATOR.'Controllers'.\DIRECTORY_SEPARATOR.'Admin'.\DIRECTORY_SEPARATOR.$basePath.'Controller.php', <<<PHP
<?php

declare(strict_types=1);

namespace Addon\\{$basePath}\\Http\\Controllers\\Admin;

use Addon\\{$basePath}\\Service\\{$basePath}Service;
use PTAdmin\\Foundation\\Response\\AdminResponse;

class {$basePath}Controller
{
    private {$basePath}Service \${$serviceProperty};

    public function __construct({$basePath}Service \${$serviceProperty})
    {
        \$this->{$serviceProperty} = \${$serviceProperty};
    }

    public function index(): \Illuminate\\Http\\JsonResponse
    {
        return AdminResponse::success(\$this->{$serviceProperty}->dashboard());
    }
}
PHP
        );

        $filesystem->put($target.\DIRECTORY_SEPARATOR.'Http'.\DIRECTORY_SEPARATOR.'Controllers'.\DIRECTORY_SEPARATOR.'Api'.\DIRECTORY_SEPARATOR.$basePath.'Controller.php', <<<PHP
<?php

declare(strict_types=1);

namespace Addon\\{$basePath}\\Http\\Controllers\\Api;

use Addon\\{$basePath}\\Service\\{$basePath}Service;
use Illuminate\\Http\\JsonResponse;

class {$basePath}Controller
{
    private {$basePath}Service \${$serviceProperty};

    public function __construct({$basePath}Service \${$serviceProperty})
    {
        \$this->{$serviceProperty} = \${$serviceProperty};
    }

    public function ping(): JsonResponse
    {
        return response()->json([
            'code' => 0,
            'message' => 'ok',
            'data' => \$this->{$serviceProperty}->publicInfo(),
        ]);
    }
}
PHP
        );

        $filesystem->put($target.\DIRECTORY_SEPARATOR.'Http'.\DIRECTORY_SEPARATOR.'Controllers'.\DIRECTORY_SEPARATOR.'Home'.\DIRECTORY_SEPARATOR.$basePath.'Controller.php', <<<PHP
<?php

declare(strict_types=1);

namespace Addon\\{$basePath}\\Http\\Controllers\\Home;

use Addon\\{$basePath}\\Service\\{$basePath}Service;

class {$basePath}Controller
{
    private {$basePath}Service \${$serviceProperty};

    public function __construct({$basePath}Service \${$serviceProperty})
    {
        \$this->{$serviceProperty} = \${$serviceProperty};
    }

    public function index(): \Illuminate\\Contracts\\View\\View
    {
        return view('{$code}::home.index', [
            'info' => \$this->{$serviceProperty}->publicInfo(),
        ]);
    }
}
PHP
        );
    }

    private function writeViews(Filesystem $filesystem, string $target, string $title, string $code): void
    {
        $filesystem->put($target.\DIRECTORY_SEPARATOR.'Response'.\DIRECTORY_SEPARATOR.'Views'.\DIRECTORY_SEPARATOR.'home'.\DIRECTORY_SEPARATOR.'index.blade.php', <<<BLADE
<div>
    <h1>{$title}</h1>
    <p>{$code} plugin home page scaffold created successfully.</p>
</div>
BLADE
        );

        $filesystem->put($target.\DIRECTORY_SEPARATOR.'Response'.\DIRECTORY_SEPARATOR.'Views'.\DIRECTORY_SEPARATOR.'ptadmin'.\DIRECTORY_SEPARATOR.'index.blade.php', <<<BLADE
<div>
    <h1>{$title} 后台</h1>
    <p>{$code} plugin admin view scaffold created successfully.</p>
</div>
BLADE
        );
    }

    private function writeReadme(Filesystem $filesystem, string $target, string $title, string $basePath): void
    {
        $filesystem->put($target.\DIRECTORY_SEPARATOR.'README.md', <<<MD
# {$basePath} Plugin

`{$title}` 插件开发骨架已生成。

建议从以下目录开始开发：

- `Dashboard`
- `Http/Controllers`
- `Models`
- `Providers`
- `Routes`
- `Service`
- `Database`
MD
        );
    }

    private function writeGitkeep(Filesystem $filesystem, string $path): void
    {
        $filesystem->put($path, '');
    }

    private function toSnakeTable(string $basePath): string
    {
        return Str::snake($basePath);
    }

    private function normalizeCode(string $basePath): string
    {
        return Str::kebab($basePath);
    }
}
