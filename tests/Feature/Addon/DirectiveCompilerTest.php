<?php

declare(strict_types=1);

namespace PTAdmin\AddonTests\Feature\Addon;

use PTAdmin\Addon\Addon;
use PTAdmin\Addon\Service\AddonDirectivesManage;
use PTAdmin\Addon\Service\AddonHooksManage;
use PTAdmin\Addon\Service\AddonInjectsManage;
use PTAdmin\Addon\Service\AddonManager;
use PTAdmin\Addon\Service\DirectiveDefinition;
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

    AddonDirectivesManage::getInstance()->register(
        'test',
        DirectiveDefinition::make('arc')
            ->handler(TestRuntimeDirectives::class)
            ->method('arc')
            ->type('loop')
            ->cacheable(false)
    );
});

function renderBladeSnippet(string $template): string
{
    $compiled = app('blade.compiler')->compileString($template);
    $__env = app('view');

    ob_start();
    eval('?>'.$compiled);

    return trim(ob_get_clean());
}

it('compiles plugin loop directives with generic end tag', function (): void {
    $compiled = app('blade.compiler')->compileString(
        "@pt:test::arc(limit=2,id=item)\n{{ \$item['title'] }}\n@pt:end"
    );

    expect($compiled)->toContain("\\PTAdmin\\Addon\\Service\\AddonDirectivesActuator::handle('test','arc'")
        ->and($compiled)->toContain('foreach($__currentLoopData as $item)')
        ->and($compiled)->toContain('endforeach; $__env->popLoop(); $loop = $__env->getLastLoop();');
});

it('compiles plugin loop directives with explicit end directive', function (): void {
    $compiled = app('blade.compiler')->compileString(
        "@pt:test::arc(limit=2,id=item)\n{{ \$item['title'] }}\n@pt:test::endarc"
    );

    expect($compiled)->toContain("\\PTAdmin\\Addon\\Service\\AddonDirectivesActuator::handle('test','arc'")
        ->and($compiled)->toContain('foreach($__currentLoopData as $item)')
        ->and($compiled)->toContain('endforeach; $__env->popLoop(); $loop = $__env->getLastLoop();');
});

it('renders plugin loop directives and executes directive handler', function (): void {
    $output = renderBladeSnippet(
        "@pt:test::arc(limit=2,id=item)\n{{ \$item['title'] }}|\n@pt:test::endarc"
    );

    expect($output)->toContain('arc-1|')
        ->and($output)->toContain('arc-2|');
});

it('compiles plugin directives to assigned output variables', function (): void {
    $compiled = app('blade.compiler')->compileString(
        "@pt:test::arc(limit=2,out=field)\n{{ \$field[0]['title'] }}"
    );

    expect($compiled)->toContain("\$field = \\PTAdmin\\Addon\\Service\\AddonDirectivesActuator::handle('test','arc'")
        ->and($compiled)->not->toContain('foreach($__currentLoopData as');
});

it('renders assigned output variables from plugin directives', function (): void {
    $output = renderBladeSnippet(
        "@pt:test::arc(limit=2,out=field)\n{{ \$field[0]['title'] }}|{{ \$field[1]['title'] }}"
    );

    expect($output)->toContain('arc-1|arc-2');
});

it('compiles empty fallback text for loop directives', function (): void {
    $compiled = app('blade.compiler')->compileString(
        "@pt:test::arc(limit=0,id=item,empty=\"暂无数据\")\n{{ \$item['title'] }}\n@pt:end"
    );

    expect($compiled)->toContain("if (blank(\$__currentLoopData)): echo e('暂无数据'); endif;")
        ->and($compiled)->toContain('foreach($__currentLoopData as $item)');
});

it('renders empty fallback text for loop directives', function (): void {
    $output = renderBladeSnippet(
        "@pt:test::arc(limit=0,id=item,empty=\"暂无数据\")\n{{ \$item['title'] }}\n@pt:end"
    );

    expect($output)->toContain('暂无数据')
        ->and($output)->not->toContain('arc-1');
});
