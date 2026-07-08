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

function registerGlobalSeoDirectiveStubs(): void
{
    if (!\function_exists('seo_title')) {
        eval(<<<'PHP'
function seo_title(?string $value = null, array $options = []): string {
    $base = 'shared title';
    if (null === $value) {
        return $base;
    }

    return 'append' === ($options['mode'] ?? 'replace')
        ? trim($base.' '.$value)
        : $value;
}
PHP);
    }

    if (!\function_exists('seo_favicon')) {
        eval(<<<'PHP'
function seo_favicon(?string $value = null, array $options = []): string {
    $href = null === $value ? '/favicon.ico' : $value;

    return '<link rel="icon" href="'.$href.'" type="image/x-icon">'.PHP_EOL
        .'<link rel="shortcut icon" href="'.$href.'" type="image/x-icon">'.PHP_EOL
        .'<link rel="apple-touch-icon" href="'.$href.'">';
}
PHP);
    }

    if (!\function_exists('seo_meta_keywords')) {
        eval(<<<'PHP'
function seo_meta_keywords(?string $value = null, array $options = []): string {
    $content = null === $value ? 'cms,ptadmin' : ('append' === ($options['mode'] ?? 'append') ? 'cms,ptadmin, '.$value : $value);

    return '<meta name="keywords" content="'.$content.'">';
}
PHP);
    }

    if (!\function_exists('seo_meta_description')) {
        eval(<<<'PHP'
function seo_meta_description(?string $value = null, array $options = []): string {
    $content = null === $value ? 'shared description' : ('append' === ($options['mode'] ?? 'replace') ? 'shared description '.$value : $value);

    return '<meta name="description" content="'.$content.'">';
}
PHP);
    }

    if (!\function_exists('seo_link_canonical')) {
        eval(<<<'PHP'
function seo_link_canonical(?string $value = null, array $options = []): string {
    $content = null === $value ? '/cms' : $value;

    return '<link rel="canonical" href="'.$content.'">';
}
PHP);
    }

    if (!\function_exists('seo_meta_robots')) {
        eval(<<<'PHP'
function seo_meta_robots(?string $value = null, array $options = []): string {
    $content = null === $value ? 'index,follow' : $value;

    return '<meta name="robots" content="'.$content.'">';
}
PHP);
    }

    if (!\function_exists('seo_social')) {
        eval(<<<'PHP'
function seo_social(array $overrides = []): string {
    $title = (string) ($overrides['title'] ?? 'shared title');

    return '<meta property="og:title" content="'.$title.'">'.PHP_EOL
        .'<meta name="twitter:card" content="summary">';
}
PHP);
    }

    if (!\function_exists('seo_jsonld_render')) {
        eval(<<<'PHP'
function seo_jsonld_render(array $overrides = []): string {
    $type = (string) data_get($overrides, 'structured_data.0.@type', 'WebPage');

    return '<script type="application/ld+json">{"@type":"'.$type.'"}</script>';
}
PHP);
    }

    if (!\function_exists('apply_seo_overrides')) {
        eval(<<<'PHP'
function apply_seo_overrides(array $overrides, bool $share = true): array {
    $GLOBALS['ptadmin_test_seo_overrides'] = $overrides;

    return $overrides;
}
PHP);
    }
}

beforeEach(function (): void {
    registerGlobalSeoDirectiveStubs();

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
            ->context(DirectiveDefinition::CONTEXT_PAGE)
            ->loopContext('directives.test.stack')
            ->cacheable(false)
    );
    AddonDirectivesManage::getInstance()->register(
        'test',
        DirectiveDefinition::make('badge')
            ->handler(TestRuntimeDirectives::class)
            ->method('badge')
            ->type(DirectiveDefinition::TYPE_OUTPUT)
            ->cacheable(false)
    );
    runtime_context_replace(runtime_context_page([
        'route' => '/runtime/demo',
        'resolved' => ['type' => 'archive'],
        'page' => [
            'id' => 100,
            'title' => '运行时文章',
            'pagination' => [
                'total' => 20,
                'last_page' => 2,
                'current_page' => 1,
                'per_page' => 10,
            ],
        ],
    ]));
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
        "@pt:arc(limit=2,id=item)\n{{ \$item['title'] }}\n@pt:end"
    );

    expect($compiled)->toContain("\\PTAdmin\\Addon\\Service\\AddonDirectivesActuator::handle('test','arc'")
        ->and($compiled)->toContain('foreach($__currentLoopData as $item)')
        ->and($compiled)->toContain('endforeach; $__env->popLoop(); $loop = $__env->getLastLoop();');
});

it('compiles plugin loop directives with explicit end directive', function (): void {
    $compiled = app('blade.compiler')->compileString(
        "@pt:arc(limit=2,id=item)\n{{ \$item['title'] }}\n@pt:endarc"
    );

    expect($compiled)->toContain("\\PTAdmin\\Addon\\Service\\AddonDirectivesActuator::handle('test','arc'")
        ->and($compiled)->toContain('foreach($__currentLoopData as $item)')
        ->and($compiled)->toContain('endforeach; $__env->popLoop(); $loop = $__env->getLastLoop();');
});

it('renders plugin loop directives and executes directive handler', function (): void {
    $output = renderBladeSnippet(
        "@pt:arc(limit=2,id=item)\n{{ \$item['title'] }}|\n@pt:endarc"
    );

    expect($output)->toContain('arc-1|')
        ->and($output)->toContain('arc-2|');
});

it('renders plugin output directives directly', function (): void {
    $compiled = app('blade.compiler')->compileString('@pt:badge(label="状态")');

    expect($compiled)->toContain('echo \\PTAdmin\\Addon\\Service\\AddonDirectivesActuator::handle');

    $output = renderBladeSnippet('@pt:badge(label="状态")');

    expect($output)->toBe('<strong>状态</strong>');
});

it('uses $field as the default loop variable when id is omitted', function (): void {
    $compiled = app('blade.compiler')->compileString(
        "@pt:arc(limit=2)\n{{ \$field['title'] }}\n@pt:end"
    );

    expect($compiled)->toContain('foreach($__currentLoopData as $field)')
        ->and($compiled)->not->toContain('foreach($__currentLoopData as $arc)')
        ->and($compiled)->toContain("'__pt_context' => \\runtime_context_current()");
});

it('renders plugin loop directives with the default $field variable', function (): void {
    $output = renderBladeSnippet(
        "@pt:arc(limit=2)\n{{ \$field[0]['title'] ?? \$field['title'] }}|\n@pt:end"
    );

    expect($output)->toContain('arc-1|')
        ->and($output)->toContain('arc-2|');
});

it('compiles plugin directives to assigned output variables', function (): void {
    $compiled = app('blade.compiler')->compileString(
        "@pt:arc(limit=2,out=field)\n{{ \$field[0]['title'] }}"
    );

    expect($compiled)->toContain("\$field = \\PTAdmin\\Addon\\Service\\AddonDirectivesActuator::handle('test','arc'")
        ->and($compiled)->not->toContain('foreach($__currentLoopData as');
});

it('renders assigned output variables from plugin directives', function (): void {
    $output = renderBladeSnippet(
        "@pt:arc(limit=2,out=field)\n{{ \$field[0]['title'] }}|{{ \$field[1]['title'] }}"
    );

    expect($output)->toContain('arc-1|arc-2');
});

it('renders shared pt dump directive once inside loops', function (): void {
    $compiled = app('blade.compiler')->compileString('@pt:dump()');

    expect($compiled)->toContain('TemplateDumpRenderer::class');

    $output = renderBladeSnippet(
        "@pt:arc(limit=2,id=archive)\n@pt:dump()\n@pt:end"
    );

    expect(substr_count($output, '@pt:dump(stack)'))->toBe(1)
        ->and($output)->toContain('<th>字段</th><th>类型</th><th>说明</th><th>模板输出</th>')
        ->and($output)->toContain('{$stack.title}')
        ->and($output)->toContain('category')
        ->and($output)->toContain('{$stack.category.title}')
        ->and($output)->toContain('list&lt;string&gt;')
        ->and($output)->toContain('{{ implode(\',\', $stack[\'tags\'] ?? []) }}')
        ->and($output)->toContain('list&lt;object&gt;')
        ->and($output)->toContain('@foreach($stack[\'images\'] ?? [] as $image) {$image.url} @endforeach')
        ->and($output)->toContain('查看帮助文档')
        ->and($output)->toContain('https://docs.pangtou.com?directive=stack');
});

it('renders shared pt dump directive from explicit context and alias', function (): void {
    $output = renderBladeSnippet('@pt:dump(context="page",as="page",docs_url="https://docs.pangtou.com/cms/directives")');

    expect($output)->toContain('@pt:dump(page)')
        ->and($output)->toContain('{$page.title}')
        ->and($output)->toContain('https://docs.pangtou.com/cms/directives?directive=page')
        ->and($output)->toContain('标题');
});

it('passes runtime context through dto payload', function (): void {
    $output = renderBladeSnippet(
        "@pt:arc(limit=1)\n{{ \$field['context_route'] }}|{{ \$field['context_type'] }}\n@pt:end"
    );

    expect($output)->toContain('/runtime/demo|archive');
});

it('compiles empty fallback text for loop directives', function (): void {
    $compiled = app('blade.compiler')->compileString(
        "@pt:arc(limit=0,id=item,empty=\"暂无数据\")\n{{ \$item['title'] }}\n@pt:end"
    );

    expect($compiled)->toContain("if (blank(\$__currentLoopData)): echo e('暂无数据'); endif;")
        ->and($compiled)->toContain('foreach($__currentLoopData as $item)');
});

it('renders empty fallback text for loop directives', function (): void {
    $output = renderBladeSnippet(
        "@pt:arc(limit=0,id=item,empty=\"暂无数据\")\n{{ \$item['title'] }}\n@pt:end"
    );

    expect($output)->toContain('暂无数据')
        ->and($output)->not->toContain('arc-1');
});

it('compiles empty blocks for loop directives', function (): void {
    $compiled = app('blade.compiler')->compileString(
        "@pt:arc(limit=0,id=item)\n{{ \$item['title'] }}\n@pt:empty\n暂无数据\n@pt:end"
    );

    expect($compiled)->toContain('foreach($__currentLoopData as $item)')
        ->and($compiled)->toContain('$__ptLoopEmpty_')
        ->and($compiled)->toContain('if ($__ptLoopEmpty_')
        ->and($compiled)->toContain('暂无数据');
});

it('renders empty blocks for loop directives', function (): void {
    $output = renderBladeSnippet(
        "@pt:arc(limit=0,id=item)\n{{ \$item['title'] }}\n@pt:empty\n暂无数据\n@pt:end"
    );

    expect($output)->toContain('暂无数据')
        ->and($output)->not->toContain('arc-1');
});

it('uses explicit empty blocks before empty attributes', function (): void {
    $output = renderBladeSnippet(
        "@pt:arc(limit=0,id=item,empty=\"参数空数据\")\n{{ \$item['title'] }}\n@pt:empty\n块空数据\n@pt:end"
    );

    expect($output)->toContain('块空数据')
        ->and($output)->not->toContain('参数空数据');
});

it('binds empty blocks to the nearest loop directive', function (): void {
    $output = renderBladeSnippet(
        "@pt:arc(limit=1,id=parent)\n"
        ."P{{ \$parent['title'] }}\n"
        ."@pt:arc(limit=0,id=child)\n"
        ."C{{ \$child['title'] }}\n"
        ."@pt:empty\n"
        ."EMPTY-{{ \$parent['title'] }}\n"
        ."@pt:end\n"
        ."@pt:end"
    );

    expect($output)->toContain('Parc-1')
        ->and($output)->toContain('EMPTY-arc-1')
        ->and($output)->not->toContain('C')
        ->and($output)->not->toContain('EMPTY-arc-2');
});

it('pushes loop items into directive runtime context stacks', function (): void {
    $output = renderBladeSnippet(
        "@pt:arc(limit=1,id=parent)\n"
        ."@pt:arc(limit=1,id=child)\n"
        ."{{ \$child['context_parent_title'] }}\n"
        ."@pt:end\n"
        ."@pt:end"
    );

    expect($output)->toContain('arc-1');
});

it('compiles host seo title and meta directives', function (): void {
    $compiled = app('blade.compiler')->compileString(
        "<title>@pt:title()</title>\n@pt:keywords()\n@pt:description()\n@pt:canonical()\n@pt:robots()"
    );

    expect($compiled)->toContain('echo e(\seo_title());')
        ->and($compiled)->toContain('echo \seo_meta_keywords();')
        ->and($compiled)->toContain('echo \seo_meta_description();')
        ->and($compiled)->toContain('echo \seo_link_canonical();')
        ->and($compiled)->toContain('echo \seo_meta_robots();');
});

it('renders host seo title and meta directives', function (): void {
    $output = renderBladeSnippet(
        "<title>@pt:title()</title>\n@pt:keywords()\n@pt:description()\n@pt:canonical()\n@pt:robots()"
    );

    expect($output)->toContain('<title>shared title</title>')
        ->and($output)->toContain('<meta name="keywords" content="cms,ptadmin">')
        ->and($output)->toContain('<meta name="description" content="shared description">')
        ->and($output)->toContain('<link rel="canonical" href="/cms">')
        ->and($output)->toContain('<meta name="robots" content="index,follow">');
});

it('compiles host seo social and jsonld directives', function (): void {
    $compiled = app('blade.compiler')->compileString(
        "@pt:seo::social()\n@pt:seo::jsonld()\n@pt:seo::head()"
    );

    expect($compiled)->toContain('echo \seo_social();')
        ->and($compiled)->toContain('echo \seo_jsonld_render();')
        ->and($compiled)->toContain('$__ptSeoLine = \seo_favicon();')
        ->and($compiled)->toContain('$__ptSeoLine = \seo_meta_keywords();')
        ->and($compiled)->toContain('$__ptSeoLine = \seo_meta_description();')
        ->and($compiled)->toContain('$__ptSeoLine = \seo_link_canonical();')
        ->and($compiled)->toContain('$__ptSeoLine = \seo_meta_robots();')
        ->and($compiled)->toContain('$__ptSeoLine.PHP_EOL');
});

it('renders host seo social and jsonld directives', function (): void {
    $output = renderBladeSnippet(
        "@pt:seo::social()\n@pt:seo::jsonld()\n@pt:seo::head()"
    );

    expect($output)->toContain('<meta property="og:title" content="shared title">')
        ->and($output)->toContain('<meta name="twitter:card" content="summary">')
        ->and($output)->toContain('"@type":"WebPage"')
        ->and($output)->toContain('<link rel="icon" href="/favicon.ico" type="image/x-icon">')
        ->and($output)->toContain('<link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">')
        ->and($output)->toContain('<link rel="apple-touch-icon" href="/favicon.ico">')
        ->and($output)->toContain('<meta name="keywords" content="cms,ptadmin">')
        ->and($output)->toContain('<meta name="description" content="shared description">')
        ->and($output)->toContain('<link rel="canonical" href="/cms">')
        ->and($output)->toContain('<meta name="robots" content="index,follow">');
});

it('renders host seo head directive with one entry per line', function (): void {
    $output = renderBladeSnippet('@pt:seo::head()');

    expect($output)->toBe(implode(PHP_EOL, [
        '<link rel="icon" href="/favicon.ico" type="image/x-icon">',
        '<link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">',
        '<link rel="apple-touch-icon" href="/favicon.ico">',
        '<meta name="keywords" content="cms,ptadmin">',
        '<meta name="description" content="shared description">',
        '<link rel="canonical" href="/cms">',
        '<meta name="robots" content="index,follow">',
        '<meta property="og:title" content="shared title">',
        '<meta name="twitter:card" content="summary">',
        '<script type="application/ld+json">{"@type":"WebPage"}</script>',
    ]));
});

it('renders host seo head directive with selective output toggles', function (): void {
    $output = renderBladeSnippet(
        '@pt:seo::head(keywords="activity",keywords_mode="append",with_favicon=false,with_social=false,with_jsonld=false,with_robots=false)'
    );

    expect($output)->toBe(implode(PHP_EOL, [
        '<meta name="keywords" content="cms,ptadmin, activity">',
        '<meta name="description" content="shared description">',
        '<link rel="canonical" href="/cms">',
    ]))
        ->and($output)->not->toContain('<meta name="robots" content="index,follow">')
        ->and($output)->not->toContain('twitter:card')
        ->and($output)->not->toContain('application/ld+json');
});

it('renders host seo head directive with favicon override', function (): void {
    $output = renderBladeSnippet(
        '@pt:seo::head(favicon="/custom.ico",with_keywords=false,with_description=false,with_canonical=false,with_robots=false,with_social=false,with_jsonld=false)'
    );

    expect($output)->toBe(implode(PHP_EOL, [
        '<link rel="icon" href="/custom.ico" type="image/x-icon">',
        '<link rel="shortcut icon" href="/custom.ico" type="image/x-icon">',
        '<link rel="apple-touch-icon" href="/custom.ico">',
    ]));
});

it('compiles host seo override directive to apply shared context overrides', function (): void {
    $compiled = app('blade.compiler')->compileString(
        "@pt:seo(title=\"活动专题\",title_mode=\"replace\",keywords=\"activity\",keywords_mode=\"append\")"
    );

    expect($compiled)->toContain("\\apply_seo_overrides(['title' => '活动专题', 'title_mode' => 'replace', 'keywords' => 'activity', 'keywords_mode' => 'append'])");
});

it('renders host seo override directive and forwards attributes', function (): void {
    unset($GLOBALS['ptadmin_test_seo_overrides']);

    renderBladeSnippet(
        "@pt:seo(title=\"活动专题\",title_mode=\"replace\",keywords=\"activity\",keywords_mode=\"append\")"
    );

    expect($GLOBALS['ptadmin_test_seo_overrides'] ?? [])->toBe([
        'title' => '活动专题',
        'title_mode' => 'replace',
        'keywords' => 'activity',
        'keywords_mode' => 'append',
    ]);
});
