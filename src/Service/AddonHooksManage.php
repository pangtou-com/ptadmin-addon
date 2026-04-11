<?php

declare(strict_types=1);

namespace PTAdmin\Addon\Service;

use PTAdmin\Addon\Addon;
use PTAdmin\Addon\Exception\AddonException;

class AddonHooksManage
{
    private static $instance;

    /** @var array */
    private $definitions = [];

    /** @var array */
    private $booted = [];

    private function __construct()
    {
    }

    public static function getInstance(): self
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function register(string $addonCode, $definition): self
    {
        if (!$definition instanceof HookDefinition) {
            $definition = $this->normalizeDefinition($definition);
        }

        $data = $definition->toArray();
        $this->definitions[$addonCode][$data['event']][] = $data;

        return $this;
    }

    public function getHooks(string $addonCode): array
    {
        $this->bootstrap($addonCode);

        return $this->definitions[$addonCode] ?? [];
    }

    public function getAll(): array
    {
        $data = [];
        foreach (array_keys(Addon::getAddons()) as $addonCode) {
            $data[$addonCode] = $this->getHooks($addonCode);
        }

        return $data;
    }

    public function getListeners(string $event): array
    {
        $listeners = [];
        foreach (array_keys(Addon::getAddons()) as $addonCode) {
            $hooks = $this->getHooks($addonCode);
            foreach ($hooks[$event] ?? [] as $listener) {
                $listeners[] = array_merge($listener, ['addon_code' => $addonCode]);
            }
        }
        usort($listeners, function (array $a, array $b): int {
            return ($b['priority'] ?? 0) <=> ($a['priority'] ?? 0);
        });

        return $listeners;
    }

    public function dispatch(string $event, array $payload = []): array
    {
        $results = [];
        $hookPayload = HookPayload::make($payload);
        foreach ($this->getListeners($event) as $listener) {
            $results[] = $this->invokeListener($listener, $hookPayload);
        }

        return $results;
    }

    public function reset($addonCode = null): void
    {
        if (null === $addonCode) {
            $this->definitions = [];
            $this->booted = [];

            return;
        }

        unset($this->definitions[$addonCode], $this->booted[$addonCode]);
    }

    private function bootstrap(string $addonCode): void
    {
        if (isset($this->booted[$addonCode])) {
            return;
        }
        $this->booted[$addonCode] = true;

        $bootstrap = Addon::getAddonBootstrap($addonCode);
        if (null === $bootstrap || !method_exists($bootstrap, 'registerHooks')) {
            return;
        }

        $bootstrap->registerHooks($this);
    }

    private function normalizeDefinition($definition): HookDefinition
    {
        if (!\is_array($definition)) {
            throw new \InvalidArgumentException(__('ptadmin-addon::messages.definition.hook_invalid'));
        }

        $result = HookDefinition::make($definition['event'] ?? '');
        if (isset($definition['handler'])) {
            $result->handler($definition['handler']);
        }
        if (isset($definition['priority'])) {
            $result->priority((int) $definition['priority']);
        }

        return $result;
    }

    private function invokeListener(array $listener, HookPayload $payload)
    {
        list($class, $method) = $this->parseHandler($listener['handler'] ?? '');
        $instance = app($class);
        if (!method_exists($instance, $method)) {
            throw new AddonException(__('ptadmin-addon::messages.definition.hook_listener_missing', ['listener' => $class.'@'.$method]));
        }

        $reflection = new \ReflectionMethod($instance, $method);
        if (0 === $reflection->getNumberOfParameters()) {
            return $instance->{$method}();
        }

        $parameter = $reflection->getParameters()[0];
        $type = $parameter->getType();
        if (null !== $type && 'array' === $type->getName()) {
            return $instance->{$method}($payload->all());
        }

        return $instance->{$method}($payload);
    }

    private function parseHandler(string $handler): array
    {
        $handler = trim($handler);
        if (false === strpos($handler, '@')) {
            return [$handler, 'handle'];
        }

        return explode('@', $handler, 2);
    }
}
