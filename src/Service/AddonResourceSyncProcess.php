<?php

declare(strict_types=1);

namespace PTAdmin\Addon\Service;

use PTAdmin\Addon\Contracts\AddonResourceSyncRunnerInterface;
use PTAdmin\Addon\Exception\AddonException;
use Symfony\Component\Process\Process;

final class AddonResourceSyncProcess implements AddonResourceSyncRunnerInterface
{
    /** @var string|null */
    private $phpBinary;

    public function assertAvailable(): void
    {
        if (!\function_exists('proc_open')) {
            throw new AddonException(__('ptadmin-addon::messages.addon.resource_sync_process_unavailable'));
        }

        $artisan = base_path('artisan');
        if (!is_file($artisan) || !is_readable($artisan)) {
            throw new AddonException(__('ptadmin-addon::messages.addon.resource_sync_artisan_missing', [
                'path' => $artisan,
            ]));
        }

        $this->phpBinary();
    }

    public function sync(string $addonCode, bool $includeDisabled = false, ?callable $output = null): void
    {
        $this->assertAvailable();

        $command = [
            $this->phpBinary(),
            base_path('artisan'),
            'addon:resources:sync',
            $addonCode,
            '--no-interaction',
        ];
        if ($includeDisabled) {
            $command[] = '--include-disabled';
        }

        $process = new Process(
            $command,
            base_path(),
            null,
            null,
            max(1, (int) config('addon.upgrade.resource_sync_timeout', 120))
        );
        $process->run(function (string $type, string $buffer) use ($output): void {
            if (null === $output) {
                return;
            }

            $message = trim($buffer);
            if ('' !== $message) {
                $output($message, Process::ERR === $type);
            }
        });

        if (!$process->isSuccessful()) {
            $message = trim($process->getErrorOutput());
            if ('' === $message) {
                $message = trim($process->getOutput());
            }

            throw new AddonException(__('ptadmin-addon::messages.addon.resource_sync_process_failed', [
                'code' => $addonCode,
                'exit_code' => (string) $process->getExitCode(),
                'message' => $message,
            ]));
        }
    }

    private function phpBinary(): string
    {
        if (null !== $this->phpBinary) {
            return $this->phpBinary;
        }

        foreach ($this->phpBinaryCandidates() as $candidate) {
            if ($this->isCliBinary($candidate)) {
                return $this->phpBinary = $candidate;
            }
        }

        throw new AddonException(__('ptadmin-addon::messages.addon.resource_sync_php_missing'));
    }

    /** @return array<int, string> */
    private function phpBinaryCandidates(): array
    {
        $configured = trim((string) config('addon.upgrade.php_binary', ''));
        $filename = '\\' === \DIRECTORY_SEPARATOR ? 'php.exe' : 'php';
        $candidates = array_filter([
            $configured,
            'cli' === PHP_SAPI ? PHP_BINARY : '',
            rtrim(PHP_BINDIR, '\\/').\DIRECTORY_SEPARATOR.$filename,
            $filename,
        ]);

        return array_values(array_unique($candidates));
    }

    private function isCliBinary(string $candidate): bool
    {
        try {
            $process = new Process([
                $candidate,
                '-r',
                'exit(PHP_SAPI === "cli" ? 0 : 1);',
            ]);
            $process->setTimeout(10);
            $process->run();

            return $process->isSuccessful();
        } catch (\Throwable $exception) {
            return false;
        }
    }
}
