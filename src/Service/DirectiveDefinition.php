<?php

declare(strict_types=1);

/**
 *  PTAdmin
 *  ============================================================================
 *  版权所有 2022-2025 重庆胖头网络技术有限公司，并保留所有权利。
 *  网站地址: https://www.pangtou.com
 *  ----------------------------------------------------------------------------
 *  尊敬的用户，
 *     感谢您对我们产品的关注与支持。我们希望提醒您，在商业用途中使用我们的产品时，请务必前往官方渠道购买正版授权。
 *  购买正版授权不仅有助于支持我们不断提供更好的产品和服务，更能够确保您在使用过程中不会引起不必要的法律纠纷。
 *  正版授权是保障您合法使用产品的最佳方式，也有助于维护您的权益和公司的声誉。我们一直致力于为客户提供高质量的解决方案，并通过正版授权机制确保产品的可靠性和安全性。
 *  如果您有任何疑问或需要帮助，我们的客户服务团队将随时为您提供支持。感谢您的理解与合作。
 *  诚挚问候，
 *  【重庆胖头网络技术有限公司】
 *  ============================================================================
 *  Author:    Zane
 *  Homepage:  https://www.pangtou.com
 *  Email:     vip@pangtou.com
 */

namespace PTAdmin\Addon\Service;

/**
 * 代码注册指令定义.
 */
class DirectiveDefinition
{
    public const TYPE_LOOP = 'loop';
    public const TYPE_IF = 'if';
    public const TYPE_OUTPUT = 'output';

    /** @var string */
    private $name;

    /** @var string */
    private $handler;

    /** @var string|null */
    private $title;

    /** @var string */
    private $method = AddonDirectives::DEFAULT_METHOD;

    /** @var string */
    private $type = self::TYPE_LOOP;

    /** @var bool */
    private $cacheable = true;

    private function __construct(string $name)
    {
        $this->name($name);
    }

    public static function make(string $name): self
    {
        return new self($name);
    }

    public function name(string $name): self
    {
        $this->name = trim($name);

        return $this;
    }

    public function handler(string $handler): self
    {
        $this->handler = trim($handler);

        return $this;
    }

    public function title(string $title): self
    {
        $this->title = trim($title);

        return $this;
    }

    public function method(string $method): self
    {
        $this->method = trim($method);

        return $this;
    }

    public function type(string $type): self
    {
        $this->type = $this->normalizeType($type);

        return $this;
    }

    public function cacheable(bool $cacheable = true): self
    {
        $this->cacheable = $cacheable;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getHandler(): string
    {
        return $this->handler;
    }

    public function getTitle(): ?string
    {
        return blank($this->title) ? null : $this->title;
    }

    public function getMethod(): string
    {
        return blank($this->method) ? AddonDirectives::DEFAULT_METHOD : $this->method;
    }

    public function getType(): string
    {
        return $this->normalizeType($this->type);
    }

    public function isCacheable(): bool
    {
        return $this->cacheable;
    }

    public function toArray(): array
    {
        $result = [
            'name' => $this->getName(),
            'class' => $this->getHandler(),
            'method' => $this->getMethod(),
            'type' => $this->getType(),
            'cache' => $this->isCacheable(),
        ];
        if (null !== $this->getTitle()) {
            $result['title'] = $this->getTitle();
        }

        return $result;
    }

    private function normalizeType(string $type): string
    {
        $type = strtolower(trim($type));
        if ('' === $type) {
            return self::TYPE_LOOP;
        }
        if ('for' === $type || 'foreach' === $type) {
            return self::TYPE_LOOP;
        }

        return $type;
    }
}
