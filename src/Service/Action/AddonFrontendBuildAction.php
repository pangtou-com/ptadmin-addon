<?php

declare(strict_types=1);

namespace PTAdmin\Addon\Service\Action;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use PTAdmin\Addon\Exception\AddonException;
use PTAdmin\Addon\Service\AddonUtil;
use PTAdmin\Addon\Service\Traits\FormatOutputTrait;
use Symfony\Component\Process\Process;

final class AddonFrontendBuildAction
{
    use FormatOutputTrait;

    private string $code;

    private AddonAction $action;

    private Filesystem $filesystem;

    public function __construct($code, $action)
    {
        $this->code = (string) $code;
        $this->action = $action;
        $this->filesystem = new Filesystem();
    }

    /**
     * @return array<string, mixed>
     */
    public function handle(string $packageManager = '', string $script = 'build', bool $skipInstall = false): array
    {
        $addonPath = $this->resolveAddonPath();
        $frontendPath = $addonPath.\DIRECTORY_SEPARATOR.'Frontend';
        $packageFile = $frontendPath.\DIRECTORY_SEPARATOR.'package.json';

        if (!$this->filesystem->isDirectory($addonPath)) {
            throw new AddonException(__('ptadmin-addon::messages.command.frontend_build_addon_missing', ['path' => $addonPath]));
        }

        if (!$this->filesystem->isDirectory($frontendPath)) {
            throw new AddonException(__('ptadmin-addon::messages.command.frontend_build_frontend_missing', ['path' => $frontendPath]));
        }

        if (!$this->filesystem->exists($packageFile)) {
            throw new AddonException(__('ptadmin-addon::messages.command.frontend_build_package_missing', ['path' => $packageFile]));
        }

        $addonManifest = AddonUtil::readAddonConfig($addonPath);
        if (null === $addonManifest) {
            throw new AddonException(__('ptadmin-addon::messages.package.manifest_not_found'));
        }

        $package = $this->readJsonFile($packageFile, __('ptadmin-addon::messages.command.frontend_build_package_invalid'));
        $frontendConfig = $this->readFrontendConfig($package);
        $resolvedPackageManager = $this->resolvePackageManager($frontendPath, $packageManager, $frontendConfig);
        $resolvedScript = '' === trim($script) ? 'build' : trim($script);
        $buildScript = (string) ($frontendConfig['build_script'] ?? $resolvedScript);
        $installCommand = trim((string) ($frontendConfig['install_command'] ?? ''));
        $buildCommand = trim((string) ($frontendConfig['build_command'] ?? ''));
        $distRelative = trim((string) ($frontendConfig['dist_dir'] ?? 'dist'), '/');
        $assetRelative = trim((string) ($frontendConfig['asset_dir'] ?? 'dist/admin'), '/');
        $moduleManifestRelative = trim((string) ($frontendConfig['module_manifest'] ?? 'frontend.json'), '/');

        if (!$skipInstall) {
            if ('' !== $installCommand) {
                $this->runCommand($installCommand, $frontendPath, __('ptadmin-addon::messages.command.frontend_build_install_start'));
            } elseif (!$this->filesystem->isDirectory($frontendPath.\DIRECTORY_SEPARATOR.'node_modules')) {
                $this->runCommand(
                    $this->buildInstallCommand($resolvedPackageManager),
                    $frontendPath,
                    __('ptadmin-addon::messages.command.frontend_build_install_start')
                );
            } else {
                $this->info(__('ptadmin-addon::messages.command.frontend_build_install_skipped'));
            }
        } else {
            $this->info(__('ptadmin-addon::messages.command.frontend_build_install_skipped'));
        }

        $this->runCommand(
            '' !== $buildCommand ? $buildCommand : $this->buildPackageManagerCommand($resolvedPackageManager, $buildScript),
            $frontendPath,
            __('ptadmin-addon::messages.command.frontend_build_build_start')
        );

        $distPath = $frontendPath.\DIRECTORY_SEPARATOR.$distRelative;
        if (!$this->filesystem->isDirectory($distPath)) {
            throw new AddonException(__('ptadmin-addon::messages.command.frontend_build_dist_missing', ['path' => $distPath]));
        }

        $assetPath = $addonPath.\DIRECTORY_SEPARATOR.$assetRelative;
        $this->syncDirectory($distPath, $assetPath);

        $entries = $this->resolveBuiltEntries($assetPath, $assetRelative);
        if ('' === $entries['js']) {
            throw new AddonException(__('ptadmin-addon::messages.command.frontend_build_entry_missing', ['path' => $assetPath]));
        }

        $moduleManifestPath = $addonPath.\DIRECTORY_SEPARATOR.$moduleManifestRelative;
        $modulePayload = $this->buildModuleManifest($addonManifest, $entries, $package, $moduleManifestPath);
        $this->filesystem->put(
            $moduleManifestPath,
            (string) json_encode($modulePayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        $this->success(__('ptadmin-addon::messages.command.frontend_build_done', [
            'path' => $assetPath,
            'manifest' => $moduleManifestPath,
        ]));

        return [
            'code' => (string) ($addonManifest['code'] ?? $this->code),
            'package_manager' => $resolvedPackageManager,
            'script' => $buildScript,
            'asset_path' => $assetPath,
            'module_manifest' => $moduleManifestPath,
            'entry' => $entries,
        ];
    }

    private function resolveAddonPath(): string
    {
        return base_path('addons'.\DIRECTORY_SEPARATOR.Str::studly($this->code));
    }

    /**
     * @return array<string, mixed>
     */
    private function readJsonFile(string $path, string $message): array
    {
        $content = @file_get_contents($path);
        if (false === $content || '' === trim($content)) {
            throw new AddonException($message);
        }

        $decoded = @json_decode($content, true);
        if (!\is_array($decoded)) {
            throw new AddonException($message);
        }

        return $decoded;
    }

    /**
     * @param array<string, mixed> $package
     *
     * @return array<string, mixed>
     */
    private function readFrontendConfig(array $package): array
    {
        $config = $package['ptadmin-addon'] ?? $package['ptadmin_addon'] ?? [];

        return \is_array($config) ? $config : [];
    }

    /**
     * @param array<string, mixed> $frontendConfig
     */
    private function resolvePackageManager(string $frontendPath, string $packageManager, array $frontendConfig): string
    {
        $resolved = strtolower(trim($packageManager));
        if ('' !== $resolved) {
            return $resolved;
        }

        $configured = strtolower(trim((string) ($frontendConfig['package_manager'] ?? '')));
        if ('' !== $configured) {
            return $configured;
        }

        foreach ([
            'pnpm-lock.yaml' => 'pnpm',
            'yarn.lock' => 'yarn',
            'package-lock.json' => 'npm',
            'npm-shrinkwrap.json' => 'npm',
        ] as $filename => $manager) {
            if ($this->filesystem->exists($frontendPath.\DIRECTORY_SEPARATOR.$filename)) {
                return $manager;
            }
        }

        return 'npm';
    }

    private function buildInstallCommand(string $packageManager): string
    {
        if ('yarn' === $packageManager) {
            return 'yarn install';
        }

        return $packageManager.' install';
    }

    private function buildPackageManagerCommand(string $packageManager, string $script): string
    {
        if ('yarn' === $packageManager) {
            return 'yarn '.$script;
        }

        return $packageManager.' run '.$script;
    }

    private function runCommand(string $command, string $workingDirectory, string $startMessage): void
    {
        $this->info($startMessage);
        $this->info(__('ptadmin-addon::messages.command.frontend_build_running', ['command' => $command]));

        $process = Process::fromShellCommandline($command, $workingDirectory);
        $process->setTimeout(null);
        $process->run();

        if ($process->isSuccessful()) {
            return;
        }

        $errorOutput = trim($process->getErrorOutput());
        $output = trim($process->getOutput());
        $message = '' !== $errorOutput ? $errorOutput : $output;

        throw new AddonException(__('ptadmin-addon::messages.command.frontend_build_command_failed', [
            'command' => $command,
            'message' => '' !== $message ? $message : 'unknown error',
        ]));
    }

    private function syncDirectory(string $sourcePath, string $targetPath): void
    {
        if ($this->filesystem->isDirectory($targetPath)) {
            $this->filesystem->deleteDirectory($targetPath);
        }

        $this->filesystem->ensureDirectoryExists(\dirname($targetPath));
        if (!$this->filesystem->copyDirectory($sourcePath, $targetPath)) {
            throw new AddonException(__('ptadmin-addon::messages.command.frontend_build_sync_failed', ['path' => $targetPath]));
        }
    }

    /**
     * @return array{js:string, css:array<int, string>}
     */
    private function resolveBuiltEntries(string $assetPath, string $assetRelative): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($assetPath, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $extension = strtolower((string) $file->getExtension());
            if (!\in_array($extension, ['js', 'css'], true)) {
                continue;
            }

            if (Str::endsWith($file->getFilename(), '.map')) {
                continue;
            }

            $relativePath = trim(str_replace('\\', '/', $iterator->getSubPathname()), '/');
            $files[$extension][] = trim($assetRelative.'/'.$relativePath, '/');
        }

        $jsFiles = $files['js'] ?? [];
        $cssFiles = $files['css'] ?? [];

        sort($jsFiles);
        sort($cssFiles);

        return [
            'js' => $this->pickPreferredEntry($jsFiles),
            'css' => array_values($cssFiles),
        ];
    }

    /**
     * @param array<int, string> $files
     */
    private function pickPreferredEntry(array $files): string
    {
        if ([] === $files) {
            return '';
        }

        foreach ($files as $file) {
            if (Str::endsWith($file, '/index.js') || 'index.js' === $file || preg_match('/index[^\/]*\.js$/', $file)) {
                return $file;
            }
        }

        return $files[0];
    }

    /**
     * @param array<string, mixed> $addonManifest
     * @param array{js:string, css:array<int, string>} $entries
     * @param array<string, mixed> $package
     *
     * @return array<string, mixed>
     */
    private function buildModuleManifest(array $addonManifest, array $entries, array $package, string $moduleManifestPath): array
    {
        $existing = $this->readExistingModuleDefinition($moduleManifestPath, (string) ($addonManifest['code'] ?? $this->code));
        $frontendConfig = $this->readFrontendConfig($package);
        $configuredModule = isset($frontendConfig['module']) && \is_array($frontendConfig['module'])
            ? $frontendConfig['module']
            : [];

        $module = array_replace_recursive($existing, $configuredModule);
        $code = (string) ($addonManifest['code'] ?? $this->code);
        $title = (string) ($addonManifest['title'] ?? $addonManifest['name'] ?? $code);
        $description = (string) ($addonManifest['description'] ?? '');
        $routeBase = $this->normalizeRoute((string) ($module['route_base'] ?? '/'.$code));
        $pageTitle = (string) ($module['title'] ?? $title);

        $payload = [
            'modules' => [
                [
                    'key' => (string) ($module['key'] ?? $code),
                    'title' => $pageTitle,
                    'description' => (string) ($module['description'] ?? $description),
                    'version' => (string) ($module['version'] ?? ($addonManifest['version'] ?? '1.0.0')),
                    'enabled' => isset($module['enabled']) ? (int) $module['enabled'] : 1,
                    'runtime' => (string) ($module['runtime'] ?? 'local'),
                    'route_base' => $routeBase,
                    'meta' => [
                        'icon' => $module['meta']['icon'] ?? null,
                        'order' => isset($module['meta']['order']) ? (int) $module['meta']['order'] : 0,
                        'preload' => isset($module['meta']['preload']) ? (bool) $module['meta']['preload'] : false,
                        'develop' => isset($module['meta']['develop']) ? (bool) $module['meta']['develop'] : (bool) ($addonManifest['develop'] ?? false),
                    ],
                    'entry' => [
                        'local' => [
                            'type' => (string) data_get($module, 'entry.local.type', 'module'),
                            'js' => $entries['js'],
                            'css' => $entries['css'],
                        ],
                    ],
                    'pages' => $this->normalizePages($module['pages'] ?? [], $routeBase, $pageTitle, $code),
                ],
            ],
        ];

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function readExistingModuleDefinition(string $moduleManifestPath, string $code): array
    {
        if (!$this->filesystem->exists($moduleManifestPath)) {
            return [];
        }

        $payload = $this->readJsonFile($moduleManifestPath, __('ptadmin-addon::messages.command.frontend_build_module_invalid'));
        $modules = isset($payload['modules']) && \is_array($payload['modules']) ? $payload['modules'] : $payload;
        if (!\is_array($modules)) {
            return [];
        }

        foreach ($modules as $module) {
            if (!\is_array($module)) {
                continue;
            }

            $key = (string) ($module['key'] ?? '');
            if ($key === $code || '' === $key) {
                return $module;
            }
        }

        return [];
    }

    /**
     * @param mixed $pages
     *
     * @return array<int, array<string, mixed>>
     */
    private function normalizePages($pages, string $routeBase, string $title, string $code): array
    {
        if (!\is_array($pages) || [] === $pages) {
            return [[
                'key' => $code.'.index',
                'path' => $routeBase,
                'route_name' => $code.'-index',
                'title' => $title,
                'keep_alive' => true,
            ]];
        }

        $results = [];
        foreach ($pages as $page) {
            if (!\is_array($page)) {
                continue;
            }

            $path = $this->normalizeRoute((string) ($page['path'] ?? $routeBase));
            $results[] = [
                'key' => (string) ($page['key'] ?? $code.'.index'),
                'path' => '' === $path ? $routeBase : $path,
                'route_name' => (string) ($page['route_name'] ?? $code.'-index'),
                'title' => (string) ($page['title'] ?? $title),
                'keep_alive' => !isset($page['keep_alive']) || (bool)$page['keep_alive'],
            ];
        }

        return [] === $results ? [[
            'key' => $code.'.index',
            'path' => $routeBase,
            'route_name' => $code.'-index',
            'title' => $title,
            'keep_alive' => true,
        ]] : array_values($results);
    }

    private function normalizeRoute(string $route): string
    {
        $route = trim($route);
        if ('' === $route) {
            return '';
        }

        return '/'.trim($route, '/');
    }
}
