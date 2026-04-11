<?php

declare(strict_types=1);

namespace PTAdmin\Addon\Service;

use PTAdmin\Addon\Contracts\ArrayDataInterface;
use PTAdmin\Addon\Contracts\CapabilityInterface;
use PTAdmin\Addon\Exception\AddonException;

class AddonInjectsActuator
{
    public static function handle(string $group, string $code, array $payload = [], ?string $action = null)
    {
        $definition = AddonInjectsManage::getInstance()->getDefinition($group, $code);
        list($class, $method) = self::parseHandler($definition['class'] ?? '', $action);
        $instance = app($class);
        if (!blank($action) && $instance instanceof CapabilityInterface && !$instance->supports($method)) {
            throw new AddonException(__('ptadmin-addon::messages.definition.inject_action_unsupported', [
                'target' => $group.':'.$code,
                'method' => $method,
            ]));
        }
        if (!method_exists($instance, $method)) {
            throw new AddonException(__('ptadmin-addon::messages.definition.inject_handler_missing', ['handler' => $class.'@'.$method]));
        }

        $injectPayload = InjectPayload::make($payload);
        $reflection = new \ReflectionMethod($instance, $method);
        if (0 === $reflection->getNumberOfParameters()) {
            return $instance->{$method}();
        }

        $parameter = $reflection->getParameters()[0];
        $type = $parameter->getType();
        if ($type instanceof \ReflectionNamedType && class_exists($type->getName())) {
            $typeName = $type->getName();
            if (is_subclass_of($typeName, ArrayDataInterface::class)) {
                return $instance->{$method}($typeName::fromArray($injectPayload->all()));
            }
        }
        if ($type instanceof \ReflectionNamedType && 'array' === $type->getName()) {
            return $instance->{$method}($injectPayload->all());
        }

        return $instance->{$method}($injectPayload);
    }

    private static function parseHandler(string $handler, ?string $action = null): array
    {
        $handler = trim($handler);
        if (false === strpos($handler, '@')) {
            return [$handler, blank($action) ? 'handle' : trim($action)];
        }

        return explode('@', $handler, 2);
    }
}
