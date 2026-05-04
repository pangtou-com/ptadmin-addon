<?php

declare(strict_types=1);

namespace PTAdmin\Addon\Service\Action;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PTAdmin\Addon\Exception\AddonException;
use PTAdmin\Addon\Service\AddonUtil;

final class AddonFrontendPullAction extends AbstractAddonAction
{
    private const OFFICIAL_SOURCE = 'official';

    public function __destruct()
    {
        if (null !== $this->action) {
            $this->filesystem->deleteDirectory($this->action->getStorePath());
        }
    }

    /**
     * @return array<string, string>
     */
    public function handle(string $template = 'module', string $ref = 'main', string $source = '', bool $force = false): array
    {
        $addonPath = $this->resolveAddonPath();
        $targetPath = $addonPath.\DIRECTORY_SEPARATOR.'Frontend';

        if (!$this->filesystem->isDirectory($addonPath)) {
            throw new AddonException(__('ptadmin-addon::messages.command.frontend_pull_addon_missing', ['path' => $addonPath]));
        }

        if ($this->filesystem->isDirectory($targetPath) && !$force) {
            throw new AddonException(__('ptadmin-addon::messages.command.frontend_pull_exists', ['path' => $targetPath]));
        }

        $template = $this->normalizeTemplate($template);
        $ref = '' === trim($ref) ? 'main' : trim($ref);
        $temporaryRoot = $this->action->getStorePath('frontend');
        $errors = [];

        $this->filesystem->ensureDirectoryExists($temporaryRoot);

        foreach ($this->resolveSourceOrder($source) as $currentSource) {
            try {
                $url = $this->buildArchiveUrl($currentSource, $template, $ref);
                $this->info(__('ptadmin-addon::messages.command.frontend_pull_trying', [
                    'source' => $currentSource,
                    'url' => $url,
                ]));

                $downloadFile = $temporaryRoot.\DIRECTORY_SEPARATOR.$currentSource.'.zip';
                $extractPath = $temporaryRoot.\DIRECTORY_SEPARATOR.$currentSource;
                $this->filesystem->deleteDirectory($extractPath);
                $this->filesystem->ensureDirectoryExists($extractPath);

                $this->downloadArchive($url, $downloadFile);
                $sourcePath = $this->extractArchive($downloadFile, $extractPath);

                if ($this->filesystem->isDirectory($targetPath)) {
                    $this->filesystem->deleteDirectory($targetPath);
                }

                if (!$this->filesystem->moveDirectory($sourcePath, $targetPath)) {
                    throw new AddonException(__('ptadmin-addon::messages.command.frontend_pull_move_failed', ['path' => $targetPath]));
                }

                $this->postProcessTemplate($targetPath, $template, $addonPath);

                $this->success(__('ptadmin-addon::messages.command.frontend_pull_created', [
                    'source' => $currentSource,
                    'path' => $targetPath,
                ]));

                return [
                    'source' => $currentSource,
                    'path' => $targetPath,
                    'template' => $template,
                    'ref' => $ref,
                ];
            } catch (\Throwable $throwable) {
                $errors[] = sprintf('%s: %s', $currentSource, $throwable->getMessage());
                $this->error(__('ptadmin-addon::messages.command.frontend_pull_source_failed', [
                    'source' => $currentSource,
                    'message' => $throwable->getMessage(),
                ]));
            }
        }

        throw new AddonException(__('ptadmin-addon::messages.command.frontend_pull_all_failed', [
            'messages' => implode(' | ', $errors),
        ]));
    }

    private function resolveAddonPath(): string
    {
        return base_path('addons'.\DIRECTORY_SEPARATOR.Str::studly($this->code));
    }

    /**
     * @return array<int, string>
     */
    private function resolveSourceOrder(string $source): array
    {
        $specifiedSource = strtolower(trim($source));
        if ('' !== $specifiedSource) {
            if (self::OFFICIAL_SOURCE === $specifiedSource) {
                return [self::OFFICIAL_SOURCE];
            }

            return array_values(array_unique([$specifiedSource, self::OFFICIAL_SOURCE]));
        }

        $region = $this->resolveRegion();
        $primary = (string) config('addon.frontend_templates.primary_sources.'.$region, self::OFFICIAL_SOURCE);

        return array_values(array_unique([$primary, 'github']));
    }

    private function resolveRegion(): string
    {
        $configuredRegion = strtolower(trim((string) config('addon.frontend_templates.region', 'auto')));
        if ('' !== $configuredRegion && 'auto' !== $configuredRegion) {
            return $configuredRegion;
        }

        $locale = strtolower(str_replace('_', '-', (string) config('app.locale', '')));
        $timezone = strtolower((string) config('app.timezone', ''));

        if (Str::startsWith($locale, 'zh') || \in_array($timezone, ['asia/shanghai', 'asia/chongqing', 'asia/harbin', 'asia/urumqi'], true)) {
            return 'cn';
        }

        return 'global';
    }

    private function buildArchiveUrl(string $source, string $template, string $ref): string
    {
        $pattern = trim((string) config('addon.frontend_templates.templates.'.$template.'.sources.'.$source.'.archive_url', ''));
        if ('' === $pattern) {
            $pattern = trim((string) config('addon.frontend_templates.sources.'.$source.'.archive_url', ''));
        }

        if ('' === $pattern) {
            throw new AddonException(__('ptadmin-addon::messages.command.frontend_pull_source_unconfigured', ['source' => $source]));
        }

        return strtr($pattern, [
            '{template}' => $template,
            '{ref}' => $ref,
        ]);
    }

    private function normalizeTemplate(string $template): string
    {
        $normalized = strtolower(trim($template));

        if ('' !== $normalized) {
            return $normalized;
        }

        return strtolower(trim((string) config('addon.frontend_templates.default_template', 'module'))) ?: 'module';
    }

    private function downloadArchive(string $url, string $downloadFile): void
    {
        try {
            $response = Http::withOptions([
                'verify' => false,
            ])->timeout(60)->get($url);
        } catch (ConnectionException $exception) {
            throw new AddonException($exception->getMessage(), 20000, $exception);
        }

        if (!$response->successful()) {
            throw new AddonException(__('ptadmin-addon::messages.command.frontend_pull_http_failed', [
                'status' => $response->status(),
            ]));
        }

        $body = $response->body();
        if ('' === $body) {
            throw new AddonException(__('ptadmin-addon::messages.command.frontend_pull_empty'));
        }

        $this->filesystem->put($downloadFile, $body);
    }

    private function extractArchive(string $downloadFile, string $extractPath): string
    {
        $zip = new \ZipArchive();
        $opened = $zip->open($downloadFile);
        if (true !== $opened) {
            throw new AddonException(__('ptadmin-addon::messages.command.frontend_pull_unzip_failed'));
        }

        if (true !== $zip->extractTo($extractPath)) {
            $zip->close();

            throw new AddonException(__('ptadmin-addon::messages.command.frontend_pull_unzip_failed'));
        }

        $zip->close();

        $entries = array_values(array_filter(scandir($extractPath) ?: [], static function (string $entry): bool {
            return !\in_array($entry, ['.', '..'], true);
        }));

        if ([] === $entries) {
            throw new AddonException(__('ptadmin-addon::messages.command.frontend_pull_empty'));
        }

        if (1 === \count($entries)) {
            $singlePath = $extractPath.\DIRECTORY_SEPARATOR.$entries[0];
            if ($this->filesystem->isDirectory($singlePath)) {
                return $singlePath;
            }
        }

        return $extractPath;
    }

    private function postProcessTemplate(string $targetPath, string $template, string $addonPath): void
    {
        if ('module' === $template) {
            $this->rewriteModuleFrontendManifest($targetPath, $addonPath);

            return;
        }

        if ('micro-app' === $template) {
            $this->rewriteMicroAppFrontendManifest($targetPath, $addonPath);
        }
    }

    private function rewriteModuleFrontendManifest(string $targetPath, string $addonPath): void
    {
        [$manifestPath, $payload, $addonManifest] = $this->loadFrontendManifest($targetPath, $addonPath);
        if (null === $manifestPath || null === $payload || null === $addonManifest) {
            return;
        }

        $code = strtolower(trim((string) ($addonManifest['code'] ?? $this->code)));
        $routeBase = $this->resolveModuleRouteBase($code);
        $entry = $this->resolveModuleEntry($code, (bool) ($addonManifest['develop'] ?? false), (array) ($payload['entry'] ?? []));

        $payload['id'] = $code;
        $payload['code'] = $code;
        $payload['name'] = $code;
        $payload['routeBase'] = $routeBase;
        $payload['entry'] = $entry;

        $this->filesystem->put(
            $manifestPath,
            (string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

    private function rewriteMicroAppFrontendManifest(string $targetPath, string $addonPath): void
    {
        [$manifestPath, $payload, $addonManifest] = $this->loadFrontendManifest($targetPath, $addonPath);
        if (null === $manifestPath || null === $payload || null === $addonManifest) {
            return;
        }

        $code = strtolower(trim((string) ($addonManifest['code'] ?? $this->code)));
        $routeBase = $this->resolveMicroAppRouteBase($code);
        $entry = $this->resolveMicroAppEntry($code, (bool) ($addonManifest['develop'] ?? false), (array) ($payload['entry'] ?? []));

        $payload['id'] = $code;
        $payload['code'] = $code;
        $payload['name'] = $code;
        $payload['routeBase'] = $routeBase;
        $payload['entry'] = $entry;

        $this->filesystem->put(
            $manifestPath,
            (string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

    /**
     * @return array{0: string|null, 1: array<string, mixed>|null, 2: array<string, mixed>|null}
     */
    private function loadFrontendManifest(string $targetPath, string $addonPath): array
    {
        $manifestPath = $targetPath.\DIRECTORY_SEPARATOR.'frontend.json';
        if (!$this->filesystem->exists($manifestPath)) {
            return [null, null, null];
        }

        $addonManifest = AddonUtil::readAddonConfig($addonPath);
        if (null === $addonManifest) {
            return [null, null, null];
        }

        $content = @file_get_contents($manifestPath);
        $payload = \is_string($content) ? @json_decode($content, true) : null;
        if (!\is_array($payload)) {
            throw new AddonException(__('ptadmin-addon::messages.command.frontend_build_module_invalid'));
        }

        return [$manifestPath, $payload, $addonManifest];
    }

    private function resolveModuleRouteBase(string $code): string
    {
        $pattern = trim((string) config('addon.frontend_templates.manifest.module.route_base', '/{code}'));

        return $this->replaceManifestPlaceholders($pattern, $code);
    }

    private function resolveMicroAppRouteBase(string $code): string
    {
        $pattern = trim((string) config('addon.frontend_templates.manifest.micro-app.route_base', '/{code}'));

        return $this->replaceManifestPlaceholders($pattern, $code);
    }

    /**
     * @param array<string, mixed> $entry
     *
     * @return array<string, mixed>
     */
    private function resolveModuleEntry(string $code, bool $develop, array $entry): array
    {
        $federation = \is_array($entry['federation'] ?? null) ? $entry['federation'] : [];

        return [
            'federation' => [
                'remote' => $this->replaceManifestPlaceholders(
                    (string) config('addon.frontend_templates.manifest.module.remote_name', '{code_snake}_remote'),
                    $code
                ),
                'entry' => $this->replaceManifestPlaceholders(
                    (string) config(
                        'addon.frontend_templates.manifest.module.'.($develop ? 'develop_entry' : 'deploy_entry'),
                        $develop ? 'http://localhost:4179/assets/remoteEntry.js' : '{app_url}/addons/{code}/dist/admin/assets/remoteEntry.js'
                    ),
                    $code
                ),
                'expose' => (string) ($federation['expose'] ?? config('addon.frontend_templates.manifest.module.expose', './module')),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $entry
     *
     * @return array<string, mixed>
     */
    private function resolveMicroAppEntry(string $code, bool $develop, array $entry): array
    {
        $wujie = \is_array($entry['wujie'] ?? null) ? $entry['wujie'] : [];

        return [
            'wujie' => [
                'name' => $this->replaceManifestPlaceholders(
                    (string) config('addon.frontend_templates.manifest.micro-app.app_name', '{code_snake}'),
                    $code
                ),
                'url' => $this->normalizeMicroAppUrl($this->replaceManifestPlaceholders(
                    (string) config(
                        'addon.frontend_templates.manifest.micro-app.'.($develop ? 'develop_url' : 'deploy_url'),
                        $develop ? 'http://localhost:5182/' : '{app_url}/addons/{code}/dist/admin/'
                    ),
                    $code
                )),
                'alive' => (bool) ($wujie['alive'] ?? false),
                'degrade' => (bool) ($wujie['degrade'] ?? false),
                'sync' => (bool) ($wujie['sync'] ?? false),
            ],
        ];
    }

    private function replaceManifestPlaceholders(string $pattern, string $code): string
    {
        $appUrl = rtrim((string) config('app.url', ''), '/');
        if ('' === $appUrl) {
            $appUrl = 'http://localhost';
        }

        return strtr($pattern, [
            '{code}' => $code,
            '{code_kebab}' => $code,
            '{code_snake}' => str_replace('-', '_', $code),
            '{app_url}' => $appUrl,
        ]);
    }

    private function normalizeMicroAppUrl(string $url): string
    {
        $normalized = trim($url);
        if ('' === $normalized) {
            return $normalized;
        }

        return rtrim($normalized, '/').'/';
    }
}
