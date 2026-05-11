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

        return $this->pullToTarget($targetPath, $template, $ref, $source, $force, function (string $path, string $normalizedTemplate) use ($addonPath): void {
            $this->postProcessTemplate($path, $normalizedTemplate, $addonPath);
        });
    }

    /**
     * 拉取项目二开前端模板到指定目录。
     *
     * @return array<string, string>
     */
    public function handleProject(string $targetPath, string $template = 'micro-app', string $ref = 'main', string $source = '', bool $force = false, string $code = '__app__'): array
    {
        $targetPath = rtrim($targetPath, \DIRECTORY_SEPARATOR);
        if ('' === $targetPath) {
            throw new AddonException('项目二开前端模板目标目录不能为空');
        }

        if ($this->filesystem->isDirectory($targetPath) && !$force) {
            throw new AddonException(__('ptadmin-addon::messages.command.frontend_pull_exists', ['path' => $targetPath]));
        }

        return $this->pullToTarget($targetPath, $template, $ref, $source, $force, function (string $path, string $normalizedTemplate) use ($code): void {
            $this->postProcessProjectTemplate($path, $normalizedTemplate, $code);
        });
    }

    /**
     * @return array<string, string>
     */
    private function pullToTarget(string $targetPath, string $template, string $ref, string $source, bool $force, callable $postProcess): array
    {
        $template = $this->normalizeTemplate($template);
        $ref = '' === trim($ref) ? 'main' : trim($ref);
        $temporaryRoot = $this->action->getStorePath('frontend');
        $errors = [];

        $this->filesystem->ensureDirectoryExists($temporaryRoot);

        foreach ($this->resolveSourceOrder($source) as $currentSource) {
            try {
                $archive = $this->resolveArchive($currentSource, $template, $ref);
                $this->info(__('ptadmin-addon::messages.command.frontend_pull_trying', [
                    'source' => $currentSource,
                    'url' => $archive['url'],
                ]));

                $downloadFile = $temporaryRoot.\DIRECTORY_SEPARATOR.$currentSource.'.zip';
                $extractPath = $temporaryRoot.\DIRECTORY_SEPARATOR.$currentSource;
                $this->filesystem->deleteDirectory($extractPath);
                $this->filesystem->ensureDirectoryExists($extractPath);

                $this->downloadArchive($archive['url'], $downloadFile);
                $sourcePath = $this->extractArchive($downloadFile, $extractPath);

                if ($this->filesystem->isDirectory($targetPath)) {
                    $this->filesystem->deleteDirectory($targetPath);
                }
                $this->filesystem->ensureDirectoryExists(\dirname($targetPath));

                if (!$this->filesystem->moveDirectory($sourcePath, $targetPath)) {
                    throw new AddonException(__('ptadmin-addon::messages.command.frontend_pull_move_failed', ['path' => $targetPath]));
                }

                $postProcess($targetPath, $template);

                $this->success(__('ptadmin-addon::messages.command.frontend_pull_created', [
                    'source' => $currentSource,
                    'path' => $targetPath,
                ]));

                return [
                    'source' => $currentSource,
                    'path' => $targetPath,
                    'template' => $template,
                    'ref' => $archive['ref'],
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
        if ('' !== $specifiedSource && self::OFFICIAL_SOURCE !== $specifiedSource) {
            throw new AddonException(__('ptadmin-addon::messages.command.frontend_pull_source_unsupported', [
                'source' => $specifiedSource,
            ]));
        }

        return [self::OFFICIAL_SOURCE];
    }

    /**
     * @return array{url: string, ref: string}
     */
    private function resolveArchive(string $source, string $template, string $ref): array
    {
        $manifestUrl = trim((string) config('addon.frontend_templates.templates.'.$template.'.sources.'.$source.'.manifest_url', ''));
        if ('' === $manifestUrl) {
            $manifestUrl = trim((string) config('addon.frontend_templates.sources.'.$source.'.manifest_url', ''));
        }

        if ('' !== $manifestUrl) {
            return $this->resolveArchiveFromManifest($manifestUrl, $ref);
        }

        $url = $this->buildArchiveUrl($source, $template, $ref);

        return [
            'url' => $url,
            'ref' => $ref,
        ];
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

    /**
     * @return array{url: string, ref: string}
     */
    private function resolveArchiveFromManifest(string $manifestUrl, string $ref): array
    {
        $manifest = $this->downloadTemplateManifest($manifestUrl);
        $version = $this->resolveManifestVersion($manifest, $ref);
        $artifact = $this->resolveManifestArtifact($version);
        $url = trim((string) ($artifact['url'] ?? ''));
        if ('' === $url) {
            throw new AddonException(__('ptadmin-addon::messages.command.frontend_pull_manifest_archive_missing'));
        }

        return [
            'url' => $this->normalizeManifestArchiveUrl($url, (string) ($manifest['base_url'] ?? $manifestUrl)),
            'ref' => (string) ($version['version'] ?? $ref),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function downloadTemplateManifest(string $manifestUrl): array
    {
        try {
            $response = Http::withOptions($this->httpOptions())->timeout(60)->get($manifestUrl);
        } catch (ConnectionException $exception) {
            throw new AddonException(__('ptadmin-addon::messages.command.frontend_pull_manifest_connection_failed', [
                'url' => $manifestUrl,
                'message' => $exception->getMessage(),
            ]), 20000, $exception);
        }

        if (!$response->successful()) {
            throw new AddonException(__('ptadmin-addon::messages.command.frontend_pull_manifest_http_failed', [
                'url' => $manifestUrl,
                'status' => $response->status(),
            ]));
        }

        $body = $response->body();
        if ('' === trim($body)) {
            throw new AddonException(__('ptadmin-addon::messages.command.frontend_pull_manifest_invalid'));
        }

        $manifest = json_decode($body, true);
        if (!\is_array($manifest)) {
            $manifest = json_decode($this->stripJsonTrailingCommas($body), true);
        }
        if (!\is_array($manifest)) {
            throw new AddonException(__('ptadmin-addon::messages.command.frontend_pull_manifest_invalid'));
        }

        return $manifest;
    }

    /**
     * @param array<string, mixed> $manifest
     *
     * @return array<string, mixed>
     */
    private function resolveManifestVersion(array $manifest, string $ref): array
    {
        $requested = trim($ref);
        $latest = trim((string) ($manifest['latest'] ?? ''));
        $targetVersion = \in_array($requested, ['', 'main', 'master', 'latest'], true) ? $latest : $requested;
        $versions = \is_array($manifest['versions'] ?? null) ? $manifest['versions'] : [];

        foreach ($versions as $version) {
            if (!\is_array($version)) {
                continue;
            }

            if ('' === $targetVersion || $targetVersion === (string) ($version['version'] ?? '')) {
                return $version;
            }
        }

        if ([] !== $versions && '' === $targetVersion && \is_array($versions[0] ?? null)) {
            return $versions[0];
        }

        throw new AddonException(__('ptadmin-addon::messages.command.frontend_pull_manifest_version_missing', [
            'version' => $targetVersion ?: $requested,
        ]));
    }

    /**
     * @param array<string, mixed> $version
     *
     * @return array<string, mixed>
     */
    private function resolveManifestArtifact(array $version): array
    {
        $artifacts = \is_array($version['artifacts'] ?? null) ? $version['artifacts'] : [];
        $artifact = $artifacts['primary'] ?? null;
        if (!\is_array($artifact)) {
            foreach ($artifacts as $candidate) {
                if (\is_array($candidate)) {
                    $artifact = $candidate;

                    break;
                }
            }
        }

        if (!\is_array($artifact)) {
            throw new AddonException(__('ptadmin-addon::messages.command.frontend_pull_manifest_archive_missing'));
        }

        return $artifact;
    }

    private function normalizeManifestArchiveUrl(string $url, string $baseUrl): string
    {
        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }

        $base = trim($baseUrl);
        if ('' === $base) {
            return $url;
        }

        $path = (string) (parse_url($base, PHP_URL_PATH) ?: '');
        if (preg_match('#\.[a-z0-9]+$#i', $path)) {
            $base = preg_replace('#/[^/]*$#', '/', $base) ?: $base;
        }

        return rtrim($base, '/').'/'.ltrim($url, '/');
    }

    private function stripJsonTrailingCommas(string $json): string
    {
        return (string) preg_replace('/,\s*([}\]])/', '$1', $json);
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
            $response = Http::withOptions($this->httpOptions())->timeout(60)->get($url);
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

    /**
     * @return array<string, mixed>
     */
    private function httpOptions(): array
    {
        $options = [
            'verify' => false,
        ];
        $resolve = config('addon.frontend_templates.curl_resolve', []);
        if (\is_array($resolve) && [] !== $resolve) {
            $options['curl'] = [
                CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                CURLOPT_RESOLVE => array_values(array_filter($resolve, static fn ($value): bool => \is_string($value) && '' !== trim($value))),
            ];
        }

        return $options;
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
            $this->rewritePackageJson($targetPath, $addonPath);

            return;
        }

        if ('micro-app' === $template) {
            $this->rewriteMicroAppFrontendManifest($targetPath, $addonPath);
            $this->rewritePackageJson($targetPath, $addonPath);
        }
    }

    private function postProcessProjectTemplate(string $targetPath, string $template, string $code): void
    {
        $this->rewriteProjectFrontendManifest($targetPath, $template, $code);
        $this->rewriteProjectPackageJson($targetPath, $code);
    }

    private function rewritePackageJson(string $targetPath, string $addonPath): void
    {
        $packagePath = $targetPath.\DIRECTORY_SEPARATOR.'package.json';
        if (!$this->filesystem->exists($packagePath)) {
            return;
        }

        $addonManifest = AddonUtil::readAddonConfig($addonPath);
        $code = strtolower(trim((string) ($addonManifest['code'] ?? $this->code)));
        if ('' === $code) {
            return;
        }

        $content = @file_get_contents($packagePath);
        $payload = \is_string($content) ? @json_decode($content, true) : null;
        if (!\is_array($payload)) {
            throw new AddonException(__('ptadmin-addon::messages.command.frontend_build_module_invalid'));
        }

        $payload['name'] = '@pangtou-addon/'.$code;

        $this->filesystem->put(
            $packagePath,
            (string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
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

    private function rewriteProjectFrontendManifest(string $targetPath, string $template, string $code): void
    {
        $manifestPath = $targetPath.\DIRECTORY_SEPARATOR.'frontend.json';
        if (!$this->filesystem->exists($manifestPath)) {
            return;
        }

        $content = @file_get_contents($manifestPath);
        $payload = \is_string($content) ? @json_decode($content, true) : null;
        if (!\is_array($payload)) {
            throw new AddonException(__('ptadmin-addon::messages.command.frontend_build_module_invalid'));
        }

        $code = $this->normalizeProjectCode($code);
        $payload['id'] = $code;
        $payload['key'] = $code;
        $payload['code'] = $code;
        $payload['name'] = (string) config('app.name', $payload['name'] ?? 'Application');
        $payload['runtime'] = 'micro-app' === $template ? 'wujie' : (string) ($payload['runtime'] ?? 'wujie');
        $payload['routeBase'] = '/';

        if ('wujie' === $payload['runtime']) {
            $payload['entry'] = $this->resolveProjectMicroAppEntry($code, (array) ($payload['entry'] ?? []));
        }

        $this->filesystem->put(
            $manifestPath,
            (string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

    private function rewriteProjectPackageJson(string $targetPath, string $code): void
    {
        $packagePath = $targetPath.\DIRECTORY_SEPARATOR.'package.json';
        if (!$this->filesystem->exists($packagePath)) {
            return;
        }

        $content = @file_get_contents($packagePath);
        $payload = \is_string($content) ? @json_decode($content, true) : null;
        if (!\is_array($payload)) {
            throw new AddonException(__('ptadmin-addon::messages.command.frontend_build_module_invalid'));
        }

        $payload['name'] = '@pangtou-app/'.$this->normalizeProjectPackageName($code);

        $this->filesystem->put(
            $packagePath,
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
                        $develop ? 'http://localhost:4179/assets/remoteEntry.js' : '{app_url}/{admin_web_prefix}/modules/{code}/dist/assets/remoteEntry.js'
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
                        $develop ? 'http://localhost:5182/' : '{app_url}/{admin_web_prefix}/modules/{code}/dist/'
                    ),
                    $code
                )),
                'alive' => (bool) ($wujie['alive'] ?? false),
                'degrade' => (bool) ($wujie['degrade'] ?? false),
                'sync' => (bool) ($wujie['sync'] ?? false),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $entry
     *
     * @return array<string, mixed>
     */
    private function resolveProjectMicroAppEntry(string $code, array $entry): array
    {
        $wujie = \is_array($entry['wujie'] ?? null) ? $entry['wujie'] : [];
        $developUrl = trim((string) config('ptadmin-auth.project_frontend_dev_url', ''));

        return [
            'wujie' => [
                'name' => (string) ($wujie['name'] ?? 'ptadmin_project_app'),
                'url' => '' !== $developUrl ? $this->normalizeMicroAppUrl($developUrl) : (string) ($wujie['url'] ?? ''),
                'alive' => (bool) ($wujie['alive'] ?? true),
                'degrade' => (bool) ($wujie['degrade'] ?? false),
                'sync' => (bool) ($wujie['sync'] ?? true),
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
            '{admin_web_prefix}' => $this->adminWebPrefix(),
        ]);
    }

    private function adminWebPrefix(): string
    {
        if (function_exists('admin_web_prefix')) {
            return trim(admin_web_prefix(), '/');
        }

        return trim((string) config('ptadmin-auth.web_prefix', 'admin'), '/');
    }

    private function normalizeMicroAppUrl(string $url): string
    {
        $normalized = trim($url);
        if ('' === $normalized) {
            return $normalized;
        }

        return rtrim($normalized, '/').'/';
    }

    private function normalizeProjectCode(string $code): string
    {
        $code = trim($code);

        return '' === $code ? '__app__' : $code;
    }

    private function normalizeProjectPackageName(string $code): string
    {
        return str_replace('_', '-', trim($this->normalizeProjectCode($code), '_'));
    }
}
