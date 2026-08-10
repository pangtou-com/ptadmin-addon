<?php

declare(strict_types=1);

namespace PTAdmin\Addon\Contracts;

interface ApplicationInstanceProviderInterface
{
    public function applicationInstanceId(): string;

    public function publicKey(): string;

    public function sign(string $payload): string;
}
