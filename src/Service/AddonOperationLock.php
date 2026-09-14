<?php

declare(strict_types=1);

namespace PTAdmin\Addon\Service;

use Illuminate\Filesystem\Filesystem;
use PTAdmin\Addon\Exception\AddonException;

final class AddonOperationLock
{
    /** @var Filesystem */
    private $filesystem;

    public function __construct(Filesystem $filesystem)
    {
        $this->filesystem = $filesystem;
    }

    /** @return mixed */
    public function run(string $addonCode, callable $operation)
    {
        if (1 !== preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $addonCode)) {
            throw new AddonException(__('ptadmin-addon::messages.addon.code_invalid', ['code' => $addonCode]));
        }

        $directory = storage_path('app'.\DIRECTORY_SEPARATOR.'ptadmin'.\DIRECTORY_SEPARATOR.'addon'.\DIRECTORY_SEPARATOR.'locks');
        if (!$this->filesystem->isDirectory($directory)) {
            $this->filesystem->makeDirectory($directory, 0755, true);
        }
        if (!$this->filesystem->isDirectory($directory) || !is_writable($directory)) {
            throw new AddonException(__('ptadmin-addon::messages.package.directory_not_writable', ['path' => $directory]));
        }

        $path = $directory.\DIRECTORY_SEPARATOR.$addonCode.'.lock';
        $handle = @fopen($path, 'c');
        if (false === $handle || !flock($handle, LOCK_EX | LOCK_NB)) {
            if (\is_resource($handle)) {
                fclose($handle);
            }

            throw new AddonException(__('ptadmin-addon::messages.addon.operation_in_progress', ['code' => $addonCode]));
        }

        try {
            return $operation();
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
