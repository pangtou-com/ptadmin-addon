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
        $items = [];
        for ($i = 1; $i <= $limit; ++$i) {
            $items[] = [
                'title' => 'arc-'.$i,
            ];
        }

        return $items;
    }

    public function auth(): bool
    {
        return true;
    }
}
