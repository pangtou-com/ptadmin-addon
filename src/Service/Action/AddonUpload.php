<?php

declare(strict_types=1);

/**
 *  PTAdmin
 *  ============================================================================
 *  版权所有 2022-2024 重庆胖头网络技术有限公司，并保留所有权利。
 *  网站地址: https://www.pangtou.com
 *  ----------------------------------------------------------------------------
 *  尊敬的用户，
 *     感谢您对我们产品的关注与支持。我们希望提醒您，在商业用途中使用我们的产品时，请务必前往官方渠道购买正版授权。
 *  购买正版授权不仅有助于支持我们不断提供更好的产品和服务，更能够确保您在使用过程中不会引起不必要的法律纠纷。
 *  正版授权是保障您合法使用产品的最佳方式，也有助于维护您的权益和公司的声誉。我们一直致力于为客户提供高质量的解决方案，并通过正版授权机制确保产品的可靠性和安全性。
 *  如果您有任何疑问或需要帮助，我们的客户服务团队将随时为您提供支持。感谢您的理解与合作。
 *  诚挚问候，
 *  【重庆胖头网络技术有限公司】
 *  ============================================================================
 *  Author:    Zane
 *  Homepage:  https://www.pangtou.com
 *  Email:     vip@pangtou.com
 */

namespace PTAdmin\Addon\Service\Action;

use Illuminate\Support\Str;
use PTAdmin\Addon\AddonApi;
use PTAdmin\Addon\Exception\AddonException;
use PTAdmin\Addon\Service\AddonUtil;

final class AddonUpload extends AbstractAddonAction
{
    public function handle()
    {
        $this->info(__('ptadmin-addon::messages.action.upload_permission_check'));
        if (!$this->checkUploadPermission()) {
            return null;
        }

        return $this->pack()->upload();
    }

    private function checkUploadPermission(): bool
    {
        $this->error(__('ptadmin-addon::messages.action.upload_permission_pending'));

        return true;
    }

    private function pack(): self
    {
        $this->info(__('ptadmin-addon::messages.action.pack_start'));
        $this->filesystem->ensureDirectoryExists($this->action->getStorePath());

        $this->buildPackageZip($this->action->getAddonPath(), $this->action->getStorePath($this->filename));
        $this->info(__('ptadmin-addon::messages.action.pack_done'));

        return $this;
    }

    /**
     * @return mixed
     */
    private function upload()
    {
        $this->info(__('ptadmin-addon::messages.action.upload_start'));
        $filename = $this->action->getStorePath($this->filename);
        $stageDir = $this->action->getStorePath('package');
        $data = [];
        $data['code'] = $this->code;
        $data['md5'] = md5_file($filename);
        $data['content_hash'] = $this->getFolderMd5($stageDir);

        return AddonApi::addonUpload($filename, $data);
    }

    private function buildPackageZip(string $addonPath, string $zipFilename): void
    {
        $stageDir = $this->action->getStorePath('package');
        $this->buildPackageStage($addonPath, $stageDir);
        $this->zipDirectoryContents($stageDir, $zipFilename);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPackageStage(string $addonPath, string $stageDir): array
    {
        $manifest = AddonUtil::readAddonConfig($addonPath);
        if (null === $manifest) {
            throw new AddonException(__('ptadmin-addon::messages.package.manifest_not_found'));
        }

        $this->filesystem->deleteDirectory($stageDir);
        $this->filesystem->ensureDirectoryExists($stageDir);

        $this->writeReleaseManifestJson($manifest, $stageDir.\DIRECTORY_SEPARATOR.'manifest.json');

        $backendIncluded = $this->copyBackendPartition($addonPath, $stageDir.\DIRECTORY_SEPARATOR.'backend');
        $frontendSourceIncluded = $this->copyFrontendSourcePartition($addonPath, $stageDir.\DIRECTORY_SEPARATOR.'frontend-source');
        $frontendDistIncluded = $this->copyFrontendDistPartition($addonPath, $stageDir.\DIRECTORY_SEPARATOR.'frontend-dist');

        if (!$backendIncluded && !$frontendDistIncluded) {
            throw new AddonException(__('ptadmin-addon::messages.package.release_payload_missing'));
        }

        $releaseManifest = $this->buildReleaseManifest($manifest, $backendIncluded, $frontendSourceIncluded, $frontendDistIncluded);
        $this->filesystem->put(
            $stageDir.\DIRECTORY_SEPARATOR.'release.json',
            (string) json_encode($releaseManifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        return $releaseManifest;
    }

    private function copyBackendPartition(string $addonPath, string $targetPath): bool
    {
        return $this->copyDirectoryPartition($addonPath, $targetPath, function (string $relativePath): bool {
            if ($this->shouldExcludePackagePath($relativePath)) {
                return false;
            }

            if ('manifest.json' === $relativePath || 'release.json' === $relativePath || 'frontend.json' === $relativePath) {
                return false;
            }

            if ('dist' === $relativePath || Str::startsWith($relativePath, 'dist'.\DIRECTORY_SEPARATOR)) {
                return false;
            }

            return !('Frontend' === $relativePath || Str::startsWith($relativePath, 'Frontend'.\DIRECTORY_SEPARATOR));
        });
    }

    private function copyFrontendSourcePartition(string $addonPath, string $targetPath): bool
    {
        $sourcePath = $addonPath.\DIRECTORY_SEPARATOR.'Frontend';
        if (!is_dir($sourcePath)) {
            return false;
        }

        return $this->copyDirectoryPartition($sourcePath, $targetPath, function (string $relativePath): bool {
            if ($this->shouldExcludePackagePath($relativePath)) {
                return false;
            }

            if ('frontend.json' === $relativePath || 'release.json' === $relativePath) {
                return false;
            }

            if ('dist' === $relativePath || Str::startsWith($relativePath, 'dist'.\DIRECTORY_SEPARATOR)) {
                return false;
            }

            return true;
        });
    }

    private function copyFrontendDistPartition(string $addonPath, string $targetPath): bool
    {
        $manifestPath = $this->resolveFrontendManifestPath($addonPath);
        $distPath = $this->resolveFrontendDistPath($addonPath);
        if (null === $manifestPath || null === $distPath) {
            return false;
        }

        $this->filesystem->ensureDirectoryExists($targetPath);
        $this->filesystem->copy($manifestPath, $targetPath.\DIRECTORY_SEPARATOR.'frontend.json');

        return $this->copyDirectoryPartition($distPath, $targetPath.\DIRECTORY_SEPARATOR.'dist', function (string $relativePath): bool {
            return !$this->shouldExcludePackagePath($relativePath);
        });
    }

    private function buildReleaseManifest(array $manifest, bool $backendIncluded, bool $frontendSourceIncluded, bool $frontendDistIncluded): array
    {
        $code = (string) data_get($manifest, 'code', $this->code);
        $kind = 'empty';
        if ($backendIncluded && $frontendDistIncluded) {
            $kind = 'full-stack';
        } elseif ($backendIncluded) {
            $kind = 'backend-only';
        } elseif ($frontendDistIncluded) {
            $kind = 'frontend-only';
        } elseif ($frontendSourceIncluded) {
            $kind = 'source-only';
        }

        return [
            'schema' => 'ptadmin-addon-release@1',
            'code' => $code,
            'name' => (string) data_get($manifest, 'name', data_get($manifest, 'title', $code)),
            'version' => (string) data_get($manifest, 'version', ''),
            'kind' => $kind,
            'develop' => false,
            'type' => (string) data_get($manifest, 'type', ''),
            'packed_at' => date(DATE_ATOM),
            'components' => [
                'backend' => [
                    'path' => 'backend',
                    'included' => $backendIncluded,
                    'required' => false,
                ],
                'frontend_source' => [
                    'path' => 'frontend-source',
                    'included' => $frontendSourceIncluded,
                    'required' => false,
                ],
                'frontend_dist' => [
                    'path' => 'frontend-dist',
                    'included' => $frontendDistIncluded,
                    'required' => false,
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $manifest
     */
    private function writeReleaseManifestJson(array $manifest, string $targetPath): void
    {
        $manifest['develop'] = false;

        $this->filesystem->put(
            $targetPath,
            (string) json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

    private function zipDirectoryContents(string $sourceDir, string $zipFilename): void
    {
        $zip = new \ZipArchive();
        $result = $zip->open($zipFilename, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        if (true !== $result) {
            throw new AddonException(__('ptadmin-addon::messages.package.ziparchive_missing'));
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            $relative = ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $iterator->getSubPathName()), DIRECTORY_SEPARATOR);
            if ('' === $relative || $this->shouldExcludePackagePath($relative)) {
                continue;
            }

            if ($file->isDir()) {
                $zip->addEmptyDir($relative);

                continue;
            }

            $zip->addFile($file->getPathname(), $relative);
        }

        $zip->close();
    }

    private function copyDirectoryPartition(string $sourcePath, string $targetPath, callable $filter): bool
    {
        if (!is_dir($sourcePath)) {
            return false;
        }

        $included = false;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourcePath, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            $relativePath = ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $iterator->getSubPathName()), DIRECTORY_SEPARATOR);
            if ('' === $relativePath || !$filter($relativePath)) {
                continue;
            }

            $destinationPath = $targetPath.DIRECTORY_SEPARATOR.$relativePath;
            if ($file->isDir()) {
                $this->filesystem->ensureDirectoryExists($destinationPath);
            } else {
                $this->filesystem->ensureDirectoryExists(\dirname($destinationPath));
                $this->filesystem->copy($file->getPathname(), $destinationPath);
            }

            $included = true;
        }

        return $included;
    }

    private function resolveFrontendManifestPath(string $addonPath): ?string
    {
        foreach ([
            $addonPath.\DIRECTORY_SEPARATOR.'frontend.json',
            $addonPath.\DIRECTORY_SEPARATOR.'Frontend'.\DIRECTORY_SEPARATOR.'frontend.json',
        ] as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    private function resolveFrontendDistPath(string $addonPath): ?string
    {
        foreach ([
            $addonPath.\DIRECTORY_SEPARATOR.'dist',
            $addonPath.\DIRECTORY_SEPARATOR.'Frontend'.\DIRECTORY_SEPARATOR.'dist',
        ] as $path) {
            if (is_dir($path)) {
                return $path;
            }
        }

        return null;
    }
}
