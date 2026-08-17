<?php

declare(strict_types=1);

namespace PTAdmin\Addon\Contracts\Captcha\Data;

use PTAdmin\Addon\Support\ArrayData;

final class ChallengeCreateResult extends ArrayData
{
    protected static function defaults(): array
    {
        return [
            'challenge_id' => '',
            'type' => '',
            'expires_at' => null,
            'presentation' => [],
            'response_schema' => [],
            'renderer' => [],
            'private_state' => [],
            'meta' => [],
        ];
    }

    public function challengeId(): string { return (string) $this->get('challenge_id', ''); }
    public function type(): string { return (string) $this->get('type', ''); }
    public function privateState(): array { return (array) $this->get('private_state', []); }

    /** @return array<string, mixed> */
    public function publicData(): array
    {
        $data = $this->toArray();
        unset($data['private_state']);

        return $data;
    }
}
