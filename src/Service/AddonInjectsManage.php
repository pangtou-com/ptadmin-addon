<?php

declare(strict_types=1);

namespace PTAdmin\Addon\Service;

use PTAdmin\Addon\Addon;
use PTAdmin\Addon\Contracts\Captcha\Protocol\CaptchaDefinition;
use PTAdmin\Addon\Contracts\Payment\Protocol\PaymentDefinition;
use PTAdmin\Addon\Exception\AddonException;

class AddonInjectsManage
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

    public function register(string $addonCode, string $group, $definition): self
    {
        if (!$definition instanceof InjectDefinition) {
            $definition = $this->normalizeDefinition($definition);
        }

        $registered = $definition->toArray();
        if ('payment' === $group && !isset($registered['payment_definition'])) {
            throw new \InvalidArgumentException('Payment inject definitions must declare payment protocol v2.');
        }
        if ('payment' === $group && [] !== ($registered['type'] ?? [])) {
            throw new \InvalidArgumentException('Payment scenes must be declared through PaymentDefinition, not types().');
        }
        if ('captcha' === $group && !isset($registered['captcha_definition'])) {
            throw new \InvalidArgumentException('Captcha inject definitions must declare captcha protocol v1.');
        }
        if ('captcha' === $group && [] !== ($registered['type'] ?? [])) {
            throw new \InvalidArgumentException('Captcha challenge type must be declared through CaptchaDefinition, not types().');
        }

        $this->definitions[$addonCode][$group][] = $registered;

        return $this;
    }

    public function getInject(string $addonCode): array
    {
        $this->bootstrap($addonCode);

        return $this->definitions[$addonCode] ?? [];
    }

    public function getInjects($group = null): array
    {
        $data = [];
        foreach (array_keys(Addon::getAddons()) as $addonCode) {
            $inject = $this->getInject($addonCode);
            if (null === $group) {
                $data[$addonCode] = $inject;

                continue;
            }
            if (isset($inject[$group])) {
                $data = array_merge($data, $inject[$group]);
            }
        }

        return $data;
    }

    public function getDefinition(string $group, string $code): array
    {
        $definitions = [];
        foreach (array_keys(Addon::getAddons()) as $addonCode) {
            foreach ($this->getInject($addonCode)[$group] ?? [] as $definition) {
                if (($definition['code'] ?? null) === $code) {
                    $definitions[] = ['addon_code' => $addonCode] + $definition;
                }
            }
        }

        if (1 < \count($definitions)) {
            throw new AddonException(__('ptadmin-addon::messages.definition.inject_ambiguous', ['target' => $group.':'.$code]));
        }
        if (1 === \count($definitions)) {
            return $definitions[0];
        }

        throw new AddonException(__('ptadmin-addon::messages.definition.inject_missing', ['target' => $group.':'.$code]));
    }

    public function getDefinitionByAddonCode(string $group, string $addonCode): array
    {
        foreach ($this->getInject($addonCode)[$group] ?? [] as $definition) {
            return ['addon_code' => $addonCode] + $definition;
        }

        throw new AddonException(__('ptadmin-addon::messages.definition.inject_missing', ['target' => $group.':'.$addonCode]));
    }

    public function getDefinitionByAddonAndCode(string $group, string $addonCode, string $code): array
    {
        foreach ($this->getInject($addonCode)[$group] ?? [] as $definition) {
            if (($definition['code'] ?? null) === $code) {
                return ['addon_code' => $addonCode] + $definition;
            }
        }

        throw new AddonException(__('ptadmin-addon::messages.definition.inject_missing', ['target' => $group.':'.$addonCode.':'.$code]));
    }

    public function getDefinitionsByAddonCode(string $group, string $addonCode): array
    {
        $definitions = [];
        foreach ($this->getInject($addonCode)[$group] ?? [] as $definition) {
            $definitions[] = ['addon_code' => $addonCode] + $definition;
        }

        return $definitions;
    }

    public function getDefinitionsByGroup(string $group): array
    {
        $definitions = [];
        foreach (array_keys(Addon::getAddons()) as $addonCode) {
            foreach ($this->getInject($addonCode)[$group] ?? [] as $definition) {
                $definitions[] = ['addon_code' => $addonCode] + $definition;
            }
        }

        return $definitions;
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
        if (null === $bootstrap || !method_exists($bootstrap, 'registerInjects')) {
            return;
        }

        $bootstrap->registerInjects($this);
    }

    private function normalizeDefinition($definition): InjectDefinition
    {
        if (!\is_array($definition)) {
            throw new \InvalidArgumentException(__('ptadmin-addon::messages.definition.inject_invalid'));
        }

        $result = InjectDefinition::make($definition['code'] ?? '');
        if (isset($definition['title'])) {
            $result->title($definition['title']);
        }
        if (isset($definition['type'])) {
            $result->types((array) $definition['type']);
        }
        if (isset($definition['class'])) {
            $result->handler($definition['class']);
        }
        if (isset($definition['payment_definition'])) {
            $paymentDefinition = $definition['payment_definition'] instanceof PaymentDefinition
                ? $definition['payment_definition']
                : PaymentDefinition::fromArray((array) $definition['payment_definition']);
            $result->paymentDefinition($paymentDefinition);
        }
        if (isset($definition['captcha_definition'])) {
            $captchaDefinition = $definition['captcha_definition'] instanceof CaptchaDefinition
                ? $definition['captcha_definition']
                : CaptchaDefinition::fromArray((array) $definition['captcha_definition']);
            $result->captchaDefinition($captchaDefinition);
        }

        return $result;
    }
}
