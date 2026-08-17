<?php

declare(strict_types=1);

namespace PTAdmin\Addon\Service;

use Illuminate\Filesystem\Filesystem;
use PTAdmin\Addon\Exception\AddonException;

/**
 * 宿主侧插件安装状态。
 *
 * 插件目录表示代码已部署，安装记录表示安装器生命周期已成功执行。
 */
final class AddonInstallationRegistry
{
    /** @var Filesystem */
    private $filesystem;

    public function __construct(Filesystem $filesystem)
    {
        $this->filesystem = $filesystem;
    }

    public function isInstalled(string $code): bool
    {
        return null !== $this->get($code);
    }

    public function get(string $code): ?array
    {
        $path = $this->recordPath($code);
        if (!$this->filesystem->isFile($path)) {
            return null;
        }

        try {
            $record = json_decode($this->filesystem->get($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $exception) {
            throw new AddonException(
                __('ptadmin-addon::messages.addon.installation_state_invalid', ['code' => $code]),
                20000,
                $exception
            );
        }

        if (!is_array($record) || (string) ($record['code'] ?? '') !== $code) {
            throw new AddonException(__('ptadmin-addon::messages.addon.installation_state_invalid', ['code' => $code]));
        }

        return $record;
    }

    public function markInstalled(string $code, ?string $version = null, string $source = 'package'): void
    {
        $previous = $this->get($code);
        $now = gmdate(DATE_ATOM);
        $record = [
            'code' => $code,
            'version' => $version,
            'source' => $source,
            'installed_at' => $previous['installed_at'] ?? $now,
            'updated_at' => $now,
        ];

        $this->write($code, $record);
    }

    public function forget(string $code): void
    {
        $path = $this->recordPath($code);
        if ($this->filesystem->isFile($path) && !$this->filesystem->delete($path)) {
            throw new AddonException(__('ptadmin-addon::messages.addon.installation_state_delete_failed', ['code' => $code]));
        }
    }

    private function write(string $code, array $record): void
    {
        $directory = $this->directory();
        if (!$this->filesystem->isDirectory($directory)) {
            $this->filesystem->makeDirectory($directory, 0755, true);
        }
        if (!$this->filesystem->isDirectory($directory) || !is_writable($directory)) {
            throw new AddonException(__('ptadmin-addon::messages.package.directory_not_writable', ['path' => $directory]));
        }

        $payload = json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        if (false === $this->filesystem->put($this->recordPath($code), $payload, true)) {
            throw new AddonException(__('ptadmin-addon::messages.addon.installation_state_write_failed', ['code' => $code]));
        }
    }

    private function recordPath(string $code): string
    {
        if (1 !== preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $code)) {
            throw new AddonException(__('ptadmin-addon::messages.addon.code_invalid', ['code' => $code]));
        }

        return $this->directory().DIRECTORY_SEPARATOR.$code.'.json';
    }

    private function directory(): string
    {
        return storage_path('app'.DIRECTORY_SEPARATOR.'ptadmin'.DIRECTORY_SEPARATOR.'addon'.DIRECTORY_SEPARATOR.'installations');
    }
}
