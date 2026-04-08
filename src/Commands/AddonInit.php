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
                $this->error("插件目录已存在：{$target}");

                return 1;
            }
            $filesystem->deleteDirectory($target);
        }

        $this->createDirectories($filesystem, $target);
        $this->writeManifest($filesystem, $target, $code, $title, $basePath);
        $this->writeInstaller($filesystem, $target, $basePath);
        $this->writeBootstrap($filesystem, $target, $basePath);
        $this->writeFunctions($filesystem, $target);
        $this->writeRoutes($filesystem, $target, $code);
        $this->writeConfig($filesystem, $target, $code);
        $this->writeView($filesystem, $target, $title);
        $this->writeGitkeep($filesystem, $target.\DIRECTORY_SEPARATOR.'Assets'.\DIRECTORY_SEPARATOR.'.gitkeep');
        $this->writeGitkeep($filesystem, $target.\DIRECTORY_SEPARATOR.'Response'.\DIRECTORY_SEPARATOR.'Lang'.\DIRECTORY_SEPARATOR.'.gitkeep');

        $this->info("插件脚手架已创建：{$target}");

        return 0;
    }

    private function createDirectories(Filesystem $filesystem, string $target): void
    {
        $directories = [
            $target,
            $target.\DIRECTORY_SEPARATOR.'Routes',
            $target.\DIRECTORY_SEPARATOR.'Config',
            $target.\DIRECTORY_SEPARATOR.'Assets',
            $target.\DIRECTORY_SEPARATOR.'Response'.\DIRECTORY_SEPARATOR.'Views',
            $target.\DIRECTORY_SEPARATOR.'Response'.\DIRECTORY_SEPARATOR.'Lang',
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
                'php' => '>=8.0',
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

    private function writeBootstrap(Filesystem $filesystem, string $target, string $basePath): void
    {
        $filesystem->put($target.\DIRECTORY_SEPARATOR.'Bootstrap.php', <<<PHP
<?php

declare(strict_types=1);

namespace Addon\\{$basePath};

use PTAdmin\\Addon\\Service\\AddonDirectivesManage;
use PTAdmin\\Addon\\Service\\AddonHooksManage;
use PTAdmin\\Addon\\Service\\AddonInjectsManage;
use PTAdmin\\Addon\\Service\\BaseBootstrap;

class Bootstrap extends BaseBootstrap
{
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
        $filesystem->put($target.\DIRECTORY_SEPARATOR.'Routes'.\DIRECTORY_SEPARATOR.'web.php', <<<PHP
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('{$code}::index');
});
PHP
        );
    }

    private function writeConfig(Filesystem $filesystem, string $target, string $code): void
    {
        $filesystem->put($target.\DIRECTORY_SEPARATOR.'Config'.\DIRECTORY_SEPARATOR.'config.php', <<<PHP
<?php

declare(strict_types=1);

return [
    'code' => '{$code}',
];
PHP
        );
    }

    private function writeView(Filesystem $filesystem, string $target, string $title): void
    {
        $filesystem->put($target.\DIRECTORY_SEPARATOR.'Response'.\DIRECTORY_SEPARATOR.'Views'.\DIRECTORY_SEPARATOR.'index.blade.php', <<<BLADE
<div>
    <h1>{$title}</h1>
    <p>Plugin scaffold created successfully.</p>
</div>
BLADE
        );
    }

    private function writeGitkeep(Filesystem $filesystem, string $path): void
    {
        $filesystem->put($path, '');
    }
}
