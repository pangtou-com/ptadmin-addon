<?php

declare(strict_types=1);

namespace PTAdmin\Addon\Service;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use PTAdmin\Addon\Exception\AddonException;

final class AddonPackageSourceResolver
{
    private Filesystem $filesystem;

    private ?string $frontendRuntimePath = null;

    private bool $withSource;

    public function __construct(?Filesystem $filesystem = null, bool $withSource = false)
    {
        $this->filesystem = $filesystem ?? new Filesystem();
        $this->withSource = $withSource;
    }

    public function resolve(string $packageBase): string
    {
        if ($this->isReleasePackage($packageBase)) {
            return $this->materializeReleasePackage($packageBase, $this->sourceParentPath($packageBase));
        }

        foreach ($this->candidateDirectories($packageBase) as $path) {
            if ($this->isReleasePackage($path)) {
                return $this->materializeReleasePackage($path, $this->sourceParentPath($packageBase));
            }
        }

        throw new AddonException(__('ptadmin-addon::messages.package.release_manifest_not_found'));
    }

    public function getFrontendRuntimePath(): ?string
    {
        return $this->frontendRuntimePath;
    }

    private function isReleasePackage(string $path): bool
    {
        return is_dir($path)
            && is_file($path.\DIRECTORY_SEPARATOR.'manifest.json')
            && is_file($path.\DIRECTORY_SEPARATOR.'release.json');
    }

    private function materializeReleasePackage(string $releasePath, string $targetParent): string
    {
        $manifest = $this->readManifest($releasePath);
        $basePath = $this->resolveBasePath($manifest);
        $target = $targetParent.\DIRECTORY_SEPARATOR.$basePath;

        $this->filesystem->deleteDirectory($target);
        $this->filesystem->ensureDirectoryExists($target);
        $this->filesystem->copy($releasePath.\DIRECTORY_SEPARATOR.'manifest.json', $target.\DIRECTORY_SEPARATOR.'manifest.json');
        $this->filesystem->copy($releasePath.\DIRECTORY_SEPARATOR.'release.json', $target.\DIRECTORY_SEPARATOR.'release.json');

        $this->copyDirectoryContents($releasePath.\DIRECTORY_SEPARATOR.'backend', $target);
        if ($this->withSource) {
            $this->copyFrontendSource($releasePath.\DIRECTORY_SEPARATOR.'frontend-source', $target.\DIRECTORY_SEPARATOR.'Frontend');
        }
        $this->copyFrontendDist($releasePath.\DIRECTORY_SEPARATOR.'frontend-dist', $targetParent.\DIRECTORY_SEPARATOR.'frontend-runtime');

        if (!is_file($target.\DIRECTORY_SEPARATOR.'manifest.json')) {
            throw new AddonException(__('ptadmin-addon::messages.package.manifest_not_found'));
        }

        return $target;
    }

    /**
     * @return array<string, mixed>
     */
    private function readManifest(string $releasePath): array
    {
        $content = @file_get_contents($releasePath.\DIRECTORY_SEPARATOR.'manifest.json');
        $manifest = false === $content ? null : json_decode($content, true);
        if (!\is_array($manifest) || !isset($manifest['code'])) {
            throw new AddonException(__('ptadmin-addon::messages.package.manifest_missing'));
        }

        return $manifest;
    }

    /**
     * @param array<string, mixed> $manifest
     */
    private function resolveBasePath(array $manifest): string
    {
        $configured = (string) ($manifest['base_path'] ?? '');
        if ('' !== $configured) {
            return basename($configured);
        }

        foreach ($this->manifestClassCandidates($manifest) as $class) {
            if (preg_match('/^Addon\\\\([^\\\\]+)\\\\/', $class, $matches)) {
                return $matches[1];
            }
        }

        return (string) Str::studly((string) $manifest['code']);
    }

    /**
     * @param array<string, mixed> $manifest
     *
     * @return array<int, string>
     */
    private function manifestClassCandidates(array $manifest): array
    {
        $candidates = [];
        foreach (['entry.installer', 'entry.bootstrap'] as $key) {
            $value = data_get($manifest, $key);
            if (\is_string($value)) {
                $candidates[] = $value;
            }
        }

        $providers = $manifest['providers'] ?? [];
        if (\is_string($providers)) {
            $providers = [$providers];
        }
        if (\is_array($providers)) {
            foreach ($providers as $provider) {
                if (\is_string($provider)) {
                    $candidates[] = $provider;
                }
            }
        }

        return $candidates;
    }

    private function copyFrontendSource(string $sourcePath, string $targetPath): void
    {
        if (!is_dir($sourcePath)) {
            return;
        }

        $this->copyDirectoryContents($sourcePath, $targetPath);
    }

    private function copyFrontendDist(string $sourcePath, string $targetPath): void
    {
        if (!is_dir($sourcePath)) {
            return;
        }

        $this->filesystem->deleteDirectory($targetPath);
        $this->filesystem->ensureDirectoryExists($targetPath);
        if (is_file($sourcePath.\DIRECTORY_SEPARATOR.'frontend.json')) {
            $this->filesystem->copy($sourcePath.\DIRECTORY_SEPARATOR.'frontend.json', $targetPath.\DIRECTORY_SEPARATOR.'frontend.json');
        }
        $this->copyDirectoryContents($sourcePath.\DIRECTORY_SEPARATOR.'dist', $targetPath.\DIRECTORY_SEPARATOR.'dist');
        $this->frontendRuntimePath = $targetPath;
    }

    private function copyDirectoryContents(string $sourcePath, string $targetPath): void
    {
        if (!is_dir($sourcePath)) {
            return;
        }

        $this->filesystem->ensureDirectoryExists($targetPath);
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourcePath, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            $relativePath = ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $iterator->getSubPathName()), DIRECTORY_SEPARATOR);
            $destinationPath = $targetPath.\DIRECTORY_SEPARATOR.$relativePath;

            if ($file->isDir()) {
                $this->filesystem->ensureDirectoryExists($destinationPath);
            } else {
                $this->filesystem->ensureDirectoryExists(\dirname($destinationPath));
                $this->filesystem->copy($file->getPathname(), $destinationPath);
            }
        }
    }

    /**
     * @return array<int, string>
     */
    private function candidateDirectories(string $packageBase): array
    {
        if (!is_dir($packageBase)) {
            return [];
        }

        $dirs = array_diff(scandir($packageBase) ?: [], ['.', '..']);
        sort($dirs);
        $paths = [];
        foreach ($dirs as $dir) {
            $path = $packageBase.\DIRECTORY_SEPARATOR.$dir;
            if (is_dir($path) && 'source' !== $dir) {
                $paths[] = $path;
            }
        }

        return $paths;
    }

    private function sourceParentPath(string $packageBase): string
    {
        return rtrim($packageBase, \DIRECTORY_SEPARATOR).\DIRECTORY_SEPARATOR.'source';
    }
}
