<?php

declare(strict_types=1);

namespace PTAdmin\Addon\Service\Action;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PTAdmin\Addon\Exception\AddonException;

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
        $primary = (string) config('addon.frontend_templates.primary_sources.'.$region, 'github');
        $secondary = $this->resolveMirrorSource($primary);

        return array_values(array_unique([$primary, $secondary, self::OFFICIAL_SOURCE]));
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

    private function resolveMirrorSource(string $primary): string
    {
        return 'gitee' === strtolower(trim($primary)) ? 'github' : 'gitee';
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
}
