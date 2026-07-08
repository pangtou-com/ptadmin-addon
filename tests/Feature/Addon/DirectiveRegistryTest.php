<?php

declare(strict_types=1);

namespace PTAdmin\AddonTests\Feature\Addon;

use PTAdmin\Addon\Addon;
use PTAdmin\Addon\Service\AddonDirectivesActuator;
use PTAdmin\Addon\Service\AddonDirectivesManage;
use PTAdmin\Addon\Service\AddonHooksManage;
use PTAdmin\Addon\Service\AddonInjectsManage;
use PTAdmin\Addon\Service\AddonManager;
use PTAdmin\Addon\Service\DirectiveDefinition;
use PTAdmin\Addon\Service\DirectivesDTO;
use PTAdmin\AddonTests\Feature\Addon\testSrc\addons\Test\TestRuntimeDirectives;

beforeEach(function (): void {
    $app = $this->app;
    $app->setBasePath(__DIR__.\DIRECTORY_SEPARATOR.'testSrc');
    $app->forgetInstance('addon');
    $app->singleton('addon', function () {
        return new AddonManager();
    });
    Addon::clearResolvedInstance('addon');
    AddonDirectivesManage::getInstance()->reset();
    AddonInjectsManage::getInstance()->reset();
    AddonHooksManage::getInstance()->reset();
    $this->addon = new AddonManager();
});

it('prefers runtime registered directives over bootstrapped defaults', function (): void {
    AddonDirectivesManage::getInstance()->getDirectives('test');
    AddonDirectivesManage::getInstance()->register(
        'test',
        DirectiveDefinition::make('auth')
            ->handler(TestRuntimeDirectives::class)
            ->method('auth')
            ->type('if')
            ->cacheable(false)
    );

    $result = AddonDirectivesActuator::handle('test', 'auth', DirectivesDTO::build());

    expect($result)->toBeTrue();
});

it('supports unregistering bootstrapped directives', function (): void {
    $manage = AddonDirectivesManage::getInstance();
    $manage->getDirectives('test');
    $manage->unregister('test', 'auth');

    expect($manage->isLoop('test', 'lists'))->toBeTrue()
        ->and($manage->getDirectives('test'))->toHaveKey('lists')
        ->and($manage->getDirectives('test'))->not->toHaveKey('auth');
});

it('persists directive context definitions', function (): void {
    AddonDirectivesManage::getInstance()->register(
        'test',
        DirectiveDefinition::make('page_lists')
            ->handler(TestRuntimeDirectives::class)
            ->method('arc')
            ->type('loop')
            ->context(DirectiveDefinition::CONTEXT_PAGE)
    );

    $definition = AddonDirectivesManage::getInstance()->getDirective('test', 'page_lists');

    expect($definition)->toBeArray()
        ->and($definition['context'] ?? null)->toBe(DirectiveDefinition::CONTEXT_PAGE);
});

it('resolves short directive names to the unique addon definition', function (): void {
    $manage = AddonDirectivesManage::getInstance();

    expect($manage->resolveDirectiveAddon('lists'))->toBe('test')
        ->and($manage->resolveDirectiveAddon('missing'))->toBeNull();
});
