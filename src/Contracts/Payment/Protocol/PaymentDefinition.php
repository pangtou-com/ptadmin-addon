<?php

declare(strict_types=1);

namespace PTAdmin\Addon\Contracts\Payment\Protocol;

use InvalidArgumentException;

final class PaymentDefinition
{
    public const PROTOCOL_VERSION = 2;
    /** @var string */ private $code;
    /** @var string */ private $title;
    /** @var int */ private $protocolVersion;
    /** @var array<int, PaymentSceneDefinition> */ private $scenes;

    private function __construct(string $code, string $title, int $protocolVersion, array $scenes)
    {
        $code = trim($code);
        if (1 !== preg_match('/\A[a-z][a-z0-9_-]{0,99}\z/', $code)) throw new InvalidArgumentException('Payment capability code is invalid.');
        if (self::PROTOCOL_VERSION !== $protocolVersion) throw new InvalidArgumentException('Unsupported payment protocol version.');
        if ([] === $scenes) throw new InvalidArgumentException('Payment definition must declare at least one scene.');
        $this->code = $code;
        $this->title = '' === trim($title) ? $code : trim($title);
        $this->protocolVersion = $protocolVersion;
        $this->scenes = [];
        foreach ($scenes as $scene) {
            $item = $scene instanceof PaymentSceneDefinition ? $scene : PaymentSceneDefinition::fromArray((array) $scene);
            if (isset($this->scenes[$item->scene()])) throw new InvalidArgumentException('Payment definition contains duplicate scenes.');
            $this->scenes[$item->scene()] = $item;
        }
        $this->scenes = array_values($this->scenes);
    }

    public static function make(string $code, array $scenes): self
    {
        return new self($code, $code, self::PROTOCOL_VERSION, $scenes);
    }

    public function title(string $title): self
    {
        $copy = clone $this;
        $copy->title = '' === trim($title) ? $this->code : trim($title);
        return $copy;
    }

    public static function fromArray(array $data): self
    {
        return new self((string) ($data['code'] ?? ''), (string) ($data['title'] ?? ($data['code'] ?? '')), (int) ($data['protocol_version'] ?? 0), (array) ($data['scenes'] ?? []));
    }

    public function code(): string { return $this->code; }
    public function titleValue(): string { return $this->title; }
    public function protocolVersion(): int { return $this->protocolVersion; }
    /** @return array<int, PaymentSceneDefinition> */ public function scenes(): array { return $this->scenes; }
    public function scene(string $scene): ?PaymentSceneDefinition { foreach ($this->scenes as $item) if ($item->scene() === $scene) return $item; return null; }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['code' => $this->code, 'title' => $this->title, 'protocol_version' => $this->protocolVersion, 'scenes' => array_map(static function (PaymentSceneDefinition $scene): array { return $scene->toArray(); }, $this->scenes)];
    }
}
