<?php

declare(strict_types=1);

namespace PTAdmin\Addon\Service;

use PTAdmin\Addon\Contracts\ArrayDataInterface;
use PTAdmin\Addon\Contracts\CapabilityInterface;
use PTAdmin\Addon\Contracts\CapabilityReadinessInterface;
use PTAdmin\Addon\Exception\AddonException;
use ReflectionMethod;
use ReflectionNamedType;
use Throwable;

final class CapabilityCatalog
{
    /** @var string */
    private $group;

    public function __construct(string $group)
    {
        $group = trim($group);
        if (1 !== preg_match('/\A[a-z][a-z0-9_-]{0,49}\z/', $group)) {
            throw new \InvalidArgumentException('Addon capability group is invalid.');
        }

        $this->group = $group;
    }

    public function group(): string
    {
        return $this->group;
    }

    /**
     * @return array<int, array{addon_code:string, group:string, code:string, title:string, types:array<int, mixed>}>
     */
    public function all(): array
    {
        return array_map(function (array $definition): array {
            return $this->publicDefinition($definition);
        }, $this->definitions());
    }

    /**
     * 能力未声明 ready 动作时按可用处理，以保持已有插件兼容。
     * 宿主仍需在此结果之上应用业务场景、平台开关等规则。
     *
     * @param array<string, mixed> $context
     *
     * @return array<int, array{addon_code:string, group:string, code:string, title:string, types:array<int, mixed>}>
     */
    public function available(array $context = []): array
    {
        $definitions = array_filter($this->definitions(), function (array $definition) use ($context): bool {
            return $this->definitionAvailable($definition, $context);
        });

        return array_values(array_map(function (array $definition): array {
            return $this->publicDefinition($definition);
        }, $definitions));
    }

    public function has(string $code, ?string $addonCode = null): bool
    {
        return [] !== $this->matchingDefinitions($code, $addonCode);
    }

    /**
     * @return array{addon_code:string, group:string, code:string, title:string, types:array<int, mixed>}|null
     */
    public function find(string $code, ?string $addonCode = null): ?array
    {
        $definition = $this->findDefinition($code, $addonCode);

        return null === $definition ? null : $this->publicDefinition($definition);
    }

    /** @param array<string, mixed> $context */
    public function isAvailable(string $code, ?string $addonCode = null, array $context = []): bool
    {
        $definition = $this->findDefinition($code, $addonCode);

        return null !== $definition && $this->definitionAvailable($definition, $context);
    }

    /** @return array<int, array<string, mixed>> */
    private function definitions(): array
    {
        return AddonInjectsManage::getInstance()->getDefinitionsByGroup($this->group);
    }

    /** @return array<string, mixed>|null */
    private function findDefinition(string $code, ?string $addonCode): ?array
    {
        $definitions = $this->matchingDefinitions($code, $addonCode);
        if (1 < \count($definitions)) {
            throw new AddonException(__('ptadmin-addon::messages.definition.inject_ambiguous', [
                'target' => $this->group.':'.trim($code),
            ]));
        }

        return $definitions[0] ?? null;
    }

    /** @return array<int, array<string, mixed>> */
    private function matchingDefinitions(string $code, ?string $addonCode): array
    {
        $code = trim($code);
        $addonCode = null === $addonCode ? null : trim($addonCode);

        return array_values(array_filter($this->definitions(), static function (array $definition) use ($code, $addonCode): bool {
            return ($definition['code'] ?? null) === $code
                && (null === $addonCode || ($definition['addon_code'] ?? null) === $addonCode);
        }));
    }

    /**
     * @param array<string, mixed> $definition
     *
     * @return array{addon_code:string, group:string, code:string, title:string, types:array<int, mixed>}
     */
    private function publicDefinition(array $definition): array
    {
        $code = (string) ($definition['code'] ?? '');

        return [
            'addon_code' => (string) ($definition['addon_code'] ?? ''),
            'group' => $this->group,
            'code' => $code,
            'title' => (string) ($definition['title'] ?? $code),
            'types' => array_values((array) ($definition['type'] ?? [])),
        ];
    }

    /**
     * @param array<string, mixed> $definition
     * @param array<string, mixed> $context
     */
    private function definitionAvailable(array $definition, array $context): bool
    {
        try {
            $instance = app((string) ($definition['class'] ?? ''));
            if ($instance instanceof CapabilityReadinessInterface) {
                return true === $instance->ready(InjectPayload::make($context));
            }
            if (!method_exists($instance, 'ready')) {
                return true;
            }
            if ($instance instanceof CapabilityInterface && !$instance->supports('ready')) {
                return true;
            }

            return true === $this->invokeReady($instance, $context);
        } catch (Throwable $throwable) {
            return false;
        }
    }

    /**
     * @param object               $instance
     * @param array<string, mixed> $context
     *
     * @return mixed
     */
    private function invokeReady($instance, array $context)
    {
        $reflection = new ReflectionMethod($instance, 'ready');
        if (0 === $reflection->getNumberOfParameters()) {
            return $instance->ready();
        }

        $type = $reflection->getParameters()[0]->getType();
        if ($type instanceof ReflectionNamedType && class_exists($type->getName())) {
            $typeName = $type->getName();
            if (is_subclass_of($typeName, ArrayDataInterface::class)) {
                return $instance->ready($typeName::fromArray($context));
            }
        }
        if ($type instanceof ReflectionNamedType && 'array' === $type->getName()) {
            return $instance->ready($context);
        }

        return $instance->ready(InjectPayload::make($context));
    }
}
