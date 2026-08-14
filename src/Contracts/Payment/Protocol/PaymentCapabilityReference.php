<?php

declare(strict_types=1);

namespace PTAdmin\Addon\Contracts\Payment\Protocol;

use InvalidArgumentException;

final class PaymentCapabilityReference
{
    /** @var string */ private $addonCode;
    /** @var string */ private $capabilityCode;
    /** @var string */ private $profileCode;
    /** @var string */ private $scene;
    /** @var int */ private $protocolVersion;

    public function __construct(string $addonCode, string $capabilityCode, string $profileCode, string $scene, int $protocolVersion = PaymentDefinition::PROTOCOL_VERSION)
    {
        foreach (['addon_code' => $addonCode, 'capability_code' => $capabilityCode, 'profile_code' => $profileCode] as $field => $value) {
            if (1 !== preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9._-]{0,99}\z/', trim($value))) throw new InvalidArgumentException(sprintf('Payment %s is invalid.', $field));
        }
        if (PaymentDefinition::PROTOCOL_VERSION !== $protocolVersion) throw new InvalidArgumentException('Unsupported payment protocol version.');
        $this->addonCode = trim($addonCode);
        $this->capabilityCode = trim($capabilityCode);
        $this->profileCode = trim($profileCode);
        $this->scene = PaymentScene::assert($scene, 'scene');
        $this->protocolVersion = $protocolVersion;
    }

    public static function fromArray(array $data): self
    {
        return new self((string) ($data['addon_code'] ?? ''), (string) ($data['capability_code'] ?? ''), (string) ($data['profile_code'] ?? 'default'), (string) ($data['scene'] ?? ''), (int) ($data['protocol_version'] ?? PaymentDefinition::PROTOCOL_VERSION));
    }

    public function addonCode(): string { return $this->addonCode; }
    public function capabilityCode(): string { return $this->capabilityCode; }
    public function profileCode(): string { return $this->profileCode; }
    public function scene(): string { return $this->scene; }
    public function protocolVersion(): int { return $this->protocolVersion; }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['addon_code' => $this->addonCode, 'capability_code' => $this->capabilityCode, 'profile_code' => $this->profileCode, 'scene' => $this->scene, 'protocol_version' => $this->protocolVersion];
    }
}
