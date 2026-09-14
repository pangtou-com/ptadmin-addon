<?php

declare(strict_types=1);

namespace PTAdmin\Addon\Contracts;

interface AddonResourceSyncRunnerInterface
{
    public function assertAvailable(): void;

    public function sync(string $addonCode, bool $includeDisabled = false, ?callable $output = null): void;
}
