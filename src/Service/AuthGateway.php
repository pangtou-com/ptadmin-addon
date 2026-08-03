<?php

declare(strict_types=1);

namespace PTAdmin\Addon\Service;

use PTAdmin\Addon\Contracts\Auth\AuthInterface;
use PTAdmin\Addon\Exception\AddonException;

final class AuthGateway
{
    /** @var string|null */
    private $addonCode;

    /** @var string|null */
    private $code;

    public function __construct(?string $addonCode = null, ?string $code = null)
    {
        $this->addonCode = blank($addonCode) ? null : trim((string) $addonCode);
        $this->code = blank($code) ? null : trim((string) $code);
    }

    public function addonCode(): ?string
    {
        return $this->addonCode;
    }

    public function code(): ?string
    {
        return $this->code;
    }

    public function definition(): array
    {
        return $this->resolveDefinition();
    }

    public function types(): array
    {
        return $this->definition()['type'] ?? [];
    }

    /** @param array<string, mixed> $context */
    public function ready(array $context = []): bool
    {
        $definition = $this->resolveDefinition();

        return (new CapabilityCatalog('auth'))->isAvailable(
            (string) $definition['code'],
            (string) $definition['addon_code'],
            $context
        );
    }

    public function supports(string $operation): bool
    {
        $definition = $this->resolveDefinition();
        $instance = app($definition['class']);

        return $instance instanceof AuthInterface && $instance->supports($operation);
    }

    /** @param array<string, mixed> $payload */
    public function getAuthorizeUrl(array $payload = []): array
    {
        return $this->invoke('getAuthorizeUrl', $payload);
    }

    /** @param array<string, mixed> $payload */
    public function handleCallback(array $payload = []): array
    {
        return $this->invoke('handleCallback', $payload);
    }

    /** @param array<string, mixed> $payload */
    public function getUser(array $payload = []): array
    {
        return $this->invoke('getUser', $payload);
    }

    /** @param array<string, mixed> $payload */
    public function refreshToken(array $payload = []): array
    {
        return $this->invoke('refreshToken', $payload);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function invoke(string $method, array $payload): array
    {
        $definition = $this->resolveDefinition();
        $instance = app($definition['class']);
        $target = 'auth:'.$definition['addon_code'].':'.$definition['code'];
        if (!$instance instanceof AuthInterface) {
            throw new AddonException(__('ptadmin-addon::messages.definition.auth_interface_required', ['target' => $target]));
        }
        if (!$instance->supports($method)) {
            throw new AddonException(__('ptadmin-addon::messages.definition.auth_method_unsupported', [
                'target' => $target,
                'method' => $method,
            ]));
        }

        return $instance->{$method}(InjectPayload::make($payload));
    }

    /** @return array<string, mixed> */
    private function resolveDefinition(): array
    {
        $manager = AddonInjectsManage::getInstance();
        if (!blank($this->addonCode) && !blank($this->code)) {
            return $manager->getDefinitionByAddonAndCode('auth', $this->addonCode, $this->code);
        }

        if (!blank($this->addonCode)) {
            $definitions = $manager->getDefinitionsByAddonCode('auth', $this->addonCode);
            if ([] === $definitions) {
                throw new AddonException(__('ptadmin-addon::messages.definition.auth_missing', ['target' => 'auth:'.$this->addonCode]));
            }

            return $definitions[0];
        }

        if (!blank($this->code)) {
            return $manager->getDefinition('auth', (string) $this->code);
        }

        $definitions = $manager->getDefinitionsByGroup('auth');
        if ([] === $definitions) {
            throw new AddonException(__('ptadmin-addon::messages.definition.auth_none'));
        }

        $configured = config('addon.defaults.auth');
        if (!blank($configured)) {
            foreach ($definitions as $definition) {
                if (($definition['addon_code'] ?? null) === $configured) {
                    return $definition;
                }
            }
        }

        return $definitions[0];
    }
}
