<?php

declare(strict_types=1);

namespace PTAdmin\AddonTests\Feature\Addon;

use PTAdmin\Addon\Service\DirectivesDTO;

it('stores and reads runtime context through helpers', function (): void {
    runtime_context_replace(runtime_context_page([
        'route' => '/articles/1',
        'resolved' => ['type' => 'archive'],
        'page' => [
            'id' => 1,
            'title' => '文章标题',
            'pagination' => [
                'total' => 10,
                'last_page' => 1,
                'current_page' => 1,
                'per_page' => 10,
            ],
        ],
    ]));

    expect(runtime_context('route'))->toBe('/articles/1')
        ->and(runtime_context('resolved.type'))->toBe('archive')
        ->and(runtime_context('page.id'))->toBe(1)
        ->and(runtime_context('page.pagination.total'))->toBe(10);
});

it('merges runtime context with existing data', function (): void {
    runtime_context_replace(runtime_context_page([
        'route' => '/articles/1',
        'resolved' => ['type' => 'archive'],
        'page' => [
            'id' => 1,
            'title' => '原始标题',
        ],
    ]));

    runtime_context_merge([
        'page' => [
            'title' => '覆盖标题',
        ],
        'seo' => [
            'title' => 'SEO 标题',
        ],
    ]);

    expect(runtime_context('page.title'))->toBe('覆盖标题')
        ->and(runtime_context('seo.title'))->toBe('SEO 标题')
        ->and(runtime_context('route'))->toBe('/articles/1');
});

it('prefers dto context over request context', function (): void {
    runtime_context_replace(runtime_context_page([
        'route' => '/request',
        'resolved' => ['type' => 'category'],
        'page' => [
            'id' => 2,
            'title' => '请求上下文',
        ],
    ]));

    $dto = DirectivesDTO::build([
        '__pt_context' => runtime_context_page([
            'route' => '/dto',
            'resolved' => ['type' => 'archive'],
            'page' => [
                'id' => 3,
                'title' => 'DTO 上下文',
            ],
        ]),
    ]);

    $context = runtime_context_from_dto($dto);

    expect(data_get($context, 'route'))->toBe('/dto')
        ->and(data_get($context, 'resolved.type'))->toBe('archive')
        ->and(data_get($context, 'page.id'))->toBe(3);
});

it('clears runtime context', function (): void {
    runtime_context_replace(runtime_context_page([
        'route' => '/clear-me',
        'resolved' => ['type' => 'archive'],
        'page' => [
            'id' => 9,
        ],
    ]));

    runtime_context_forget();

    expect(runtime_context('route'))->toBeNull()
        ->and(runtime_context('page.id'))->toBe(0)
        ->and(runtime_context('resolved.type'))->toBe('');
});
