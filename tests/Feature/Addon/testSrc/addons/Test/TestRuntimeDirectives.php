<?php

declare(strict_types=1);

namespace PTAdmin\AddonTests\Feature\Addon\testSrc\addons\Test;

use PTAdmin\Addon\Service\DirectivesDTO;

class TestRuntimeDirectives
{
    public function handle(): array
    {
        return [
            ['runtime', 'directive'],
        ];
    }

    public function arc(DirectivesDTO $dto): array
    {
        $limit = (int) $dto->getAttribute('limit', 2);
        $context = runtime_context_from_dto($dto);
        $items = [];
        for ($i = 1; $i <= $limit; ++$i) {
            $items[] = [
                'title' => 'arc-'.$i,
                'context_route' => (string) data_get($context, 'route', ''),
                'context_type' => (string) data_get($context, 'resolved.type', ''),
            ];
        }

        return $items;
    }

    public function badge(DirectivesDTO $dto): string
    {
        return '<strong>'.e((string) $dto->getAttribute('label', 'badge')).'</strong>';
    }

    public function auth(): bool
    {
        return true;
    }
}
