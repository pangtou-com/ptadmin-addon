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

namespace PTAdmin\Addon\Compiler;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\View\Compilers\BladeCompiler;
use PTAdmin\Addon\Addon;
use PTAdmin\Addon\Compiler\Concerns\PTCompileExtend;
use PTAdmin\Addon\Exception\DirectivesException;
use PTAdmin\Addon\Service\AddonDirectivesManage;

class PTCompiler extends BladeCompiler
{
    use PTCompileExtend;

    /**
     * @var array<int, array{empty_var:string, empty_attribute:string, has_empty_block:bool, loop_context_key:string|null}>
     */
    private array $ptLoopStack = [];

    private int $ptLoopCounter = 0;

    /**
     * 拷贝原始对象
     *
     * @param $baseCompiler
     */
    public function cloneBaseCompiler($baseCompiler): void
    {
        $allow = ['extensions', 'customDirectives', 'conditions', 'precompilers', 'path', 'compilers', 'rawTags',
            'contentTags', 'escapedTags', 'echoFormat', 'footer', 'rawBlocks', 'classComponentAliases',
            'classComponentNamespaces', 'compilesComponentTags', 'cachePath', 'firstCaseInSwitch', 'echoHandlers',
            'lastSection', 'forElseCounter',
        ];
        $reflector = new \ReflectionObject($baseCompiler);
        $props = $reflector->getProperties(\ReflectionProperty::IS_PROTECTED);
        foreach ($props as $prop) {
            $prop->setAccessible(true);
            if (\in_array($prop->getName(), $allow, true)) {
                $this->{$prop->getName()} = $prop->getValue($baseCompiler);
            }
        }
    }

    /**
     * 确定给定路径上的视图是否已过期。
     *
     * @param $path
     *
     * @return bool
     */
    public function isExpired($path): bool
    {
        if (false !== config('app.debug')) {
            return true;
        }

        return parent::isExpired($path);
    }

    /**
     * 编译以“@”开头的Blade语句。
     *
     * @param $value
     *
     * @return string
     */
    protected function compileStatements($value): string
    {
        $content = preg_replace_callback(
            '/\B@(@?\w+(?::\w+)?(::\w+)?)(\.\w+)?([ \t]*)(\( ( (?>[^()]+) | (?3) )* \))?/x',
            function ($match) {
                return $this->compilePTAdmin($match);
            },
            $value
        );

        return parent::compileStatements($content);
    }

    /**
     * 编译PT指令.
     *
     * @param array $match
     *
     * @return string
     */
    protected function compilePTAdmin(array $match): string
    {
        $prefix = strtoupper(mb_substr($match[1] ?? '', 0, 2, 'UTF-8'));
        if ('PT' !== $prefix) {
            return $match[0] ?? '';
        }
        /** 将参数解析为类名和方法名 */
        $data = $this->parserAction($match[1]);
        if (!$data['name']) {
            return $match[0];
        }
        if ($this->isEmpty($match[1])) {
            return $this->empty($match, $data);
        }
        // 当为结束标签时返回结束标签内容
        if ($this->isEnd($match[1])) {
            return $this->end($match, $data);
        }

        $instance = AddonDirectivesManage::getInstance();

        // 判断是否为插件自定义指令
        if (Addon::hasAddon($data['name'])) {
            $parse = Parser::make(Arr::get($match, 5));
            if ($parse->isOutput()) {
                return $this->outputCompile($parse, $data);
            }

            if ($instance->isLoop($data['name'], $data['method'])) {
                return $this->loopCompile($parse, $data);
            }

            if ($instance->isOutput($data['name'], $data['method'])) {
                return $this->echoCompile($parse, $data);
            }

            return $this->ifCompile($parse, $data);
        }

        // 判断是否为系统指令
        $compileAction = 'PTCompile'.ucfirst($data['name']);

        if (method_exists($this, $compileAction)) {
            try {
                return $this->{$compileAction}($match);
            } catch (\Exception $exception) {
                throw new DirectivesException($exception->getMessage());
            }
        }

        $directive = $this->resolveShortDirectiveAction($data);
        if (null !== $directive) {
            $parse = Parser::make(Arr::get($match, 5));
            if ($parse->isOutput()) {
                return $this->outputCompile($parse, $directive);
            }

            if ($instance->isLoop($directive['name'], $directive['method'])) {
                return $this->loopCompile($parse, $directive);
            }

            if ($instance->isOutput($directive['name'], $directive['method'])) {
                return $this->echoCompile($parse, $directive);
            }

            return $this->ifCompile($parse, $directive);
        }

        return $this->callLaravelDirective($match);
    }

    /**
     * 通过增加pt前缀的方式调用原始的指令.
     *
     * @param $match
     *
     * @return string
     */
    protected function callLaravelDirective($match): string
    {
        $match[1] = mb_substr($match[1], 3);
        if (Str::contains($match[1], '@')) {
            $match[0] = isset($match[5]) ? $match[1].$match[5] : $match[1];
        } elseif (isset($this->customDirectives[$match[1]])) {
            $match[0] = $this->callCustomDirective($match[1], Arr::get($match, 5));
        } elseif (method_exists($this, $method = 'compile'.ucfirst($match[1]))) {
            $match[0] = $this->{$method}(Arr::get($match, 5));
        }

        return isset($match[5]) ? $match[0] : $match[0].$match[2];
    }

    /**
     * SEO 标题指令。
     */
    protected function PTCompileTitle(array $match): string
    {
        return $this->compileHostSeoEchoDirective($match, 'seo_title', true);
    }

    /**
     * SEO keywords 指令。
     */
    protected function PTCompileKeywords(array $match): string
    {
        return $this->compileHostSeoEchoDirective($match, 'seo_meta_keywords');
    }

    /**
     * SEO description 指令。
     */
    protected function PTCompileDescription(array $match): string
    {
        return $this->compileHostSeoEchoDirective($match, 'seo_meta_description');
    }

    /**
     * SEO canonical 指令。
     */
    protected function PTCompileCanonical(array $match): string
    {
        return $this->compileHostSeoEchoDirective($match, 'seo_link_canonical');
    }

    /**
     * SEO robots 指令。
     */
    protected function PTCompileRobots(array $match): string
    {
        return $this->compileHostSeoEchoDirective($match, 'seo_meta_robots');
    }

    /**
     * SEO 聚合指令。
     */
    protected function PTCompileSeo(array $match): string
    {
        $action = $this->parserAction($match[1] ?? '');
        $method = strtolower((string) ($action['method'] ?? ''));
        $parse = Parser::make(Arr::get($match, 5));

        if ('social' === $method) {
            return $parse->isParamEmpty()
                ? "<?php echo \\seo_social(); ?>"
                : "<?php echo \\seo_social({$this->buildHostSeoOverrideExpression($parse)}); ?>";
        }

        if ('jsonld' === $method) {
            return $parse->isParamEmpty()
                ? "<?php echo \\seo_jsonld_render(); ?>"
                : "<?php echo \\seo_jsonld_render({$this->buildHostSeoOverrideExpression($parse)}); ?>";
        }

        if ('head' === $method) {
            if ($parse->isParamEmpty()) {
                return implode("\n", [
                    $this->compileHostSeoHeadLine('\\seo_favicon()'),
                    $this->compileHostSeoHeadLine('\\seo_meta_keywords()'),
                    $this->compileHostSeoHeadLine('\\seo_meta_description()'),
                    $this->compileHostSeoHeadLine('\\seo_link_canonical()'),
                    $this->compileHostSeoHeadLine('\\seo_meta_robots()'),
                    $this->compileHostSeoHeadLine('\\seo_social()'),
                    $this->compileHostSeoHeadLine('\\seo_jsonld_render()'),
                ]);
            }

            $overrides = $this->buildHostSeoOverrideExpression($parse);
            $overrideVar = '$__ptSeoHead';

            return "<?php {$overrideVar} = {$overrides}; ?>\n".implode("\n", [
                $this->compileHostSeoHeadLine(
                    "\\seo_favicon(data_get({$overrideVar}, 'favicon'), ['mode' => data_get({$overrideVar}, 'favicon_mode', 'replace')])",
                    "data_get({$overrideVar}, 'with_favicon', true)"
                ),
                $this->compileHostSeoHeadLine(
                    "\\seo_meta_keywords(data_get({$overrideVar}, 'keywords'), ['mode' => data_get({$overrideVar}, 'keywords_mode', 'append')])",
                    "data_get({$overrideVar}, 'with_keywords', true)"
                ),
                $this->compileHostSeoHeadLine(
                    "\\seo_meta_description(data_get({$overrideVar}, 'description'), ['mode' => data_get({$overrideVar}, 'description_mode', 'replace')])",
                    "data_get({$overrideVar}, 'with_description', true)"
                ),
                $this->compileHostSeoHeadLine(
                    "\\seo_link_canonical(data_get({$overrideVar}, 'canonical'), ['mode' => data_get({$overrideVar}, 'canonical_mode', 'replace')])",
                    "data_get({$overrideVar}, 'with_canonical', true)"
                ),
                $this->compileHostSeoHeadLine(
                    "\\seo_meta_robots(data_get({$overrideVar}, 'robots'), ['mode' => data_get({$overrideVar}, 'robots_mode', 'replace')])",
                    "data_get({$overrideVar}, 'with_robots', true)"
                ),
                $this->compileHostSeoHeadLine("\\seo_social({$overrideVar})", "data_get({$overrideVar}, 'with_social', true)"),
                $this->compileHostSeoHeadLine("\\seo_jsonld_render({$overrideVar})", "data_get({$overrideVar}, 'with_jsonld', true)"),
            ]);
        }

        return "<?php \\apply_seo_overrides({$this->buildHostSeoOverrideExpression($parse)}); ?>";
    }

    private function compileHostSeoHeadLine(string $expression, ?string $condition = null): string
    {
        $output = "\$__ptSeoLine = {$expression}; if ('' !== \$__ptSeoLine) echo \$__ptSeoLine.PHP_EOL;";
        if (null !== $condition) {
            $output = "if ({$condition}) { {$output} }";
        }

        return "<?php {$output} ?>";
    }

    /**
     * 模板调试输出指令。
     */
    protected function PTCompileDump(array $match): string
    {
        $parse = Parser::make(Arr::get($match, 5));

        return "<?php echo app(\\PTAdmin\\Addon\\Service\\TemplateDumpRenderer::class)->render({$this->buildDumpAttributesExpression($parse)}); ?>";
    }

    private function buildDumpAttributesExpression(Parser $parse): string
    {
        $attributes = [];
        foreach ($parse->getAll() as $key => $value) {
            if ('id' === $key) {
                continue;
            }

            $attributes[] = "'".$key."' => ".$this->normalizeHostDirectiveValue($value);
        }

        return [] === $attributes ? '[]' : '['.implode(', ', $attributes).']';
    }

    /**
     * 解析出是否为结束标签.
     *
     * @pt:end // 简介默认为foreach关闭
     * @pt:endarc    // 根据配置关闭
     *
     * @param $match
     * @param null|mixed $data
     *
     * @return null|string
     */
    protected function end($match, $data = null): ?string
    {
        if (null === $data) {
            $data = $this->parserAction($match[1]);
        }
        // @pt:end 的支持
        if ('end' === strtolower($data['name']) && null === $data['method']) {
            return $this->compilePtLoopEnd();
        }
        $instance = AddonDirectivesManage::getInstance();
        if (isset($data['name']) && Addon::hasAddon($data['name'])) {
            $data['method'] = mb_substr($data['method'], 3);
            if ($instance->isLoop($data['name'], $data['method'])) {
                return $this->compilePtLoopEnd();
            }

            return '<?php endif; ?>';
        }

        if (isset($data['name']) && null === $data['method']) {
            $method = mb_substr((string) $data['name'], 3);
            $directive = $this->resolveShortDirectiveAction(['name' => $method, 'method' => null]);
            if (null !== $directive) {
                if ($instance->isLoop($directive['name'], $directive['method'])) {
                    return $this->compilePtLoopEnd();
                }

                return '<?php endif; ?>';
            }
        }

        return $this->callLaravelDirective($match);
    }

    protected function empty($match, $data = null): string
    {
        if ([] === $this->ptLoopStack) {
            throw new DirectivesException('@pt:empty must be used inside a loop directive.');
        }

        $index = \count($this->ptLoopStack) - 1;
        if ($this->ptLoopStack[$index]['has_empty_block']) {
            throw new DirectivesException('@pt:empty can only be used once inside the same loop directive.');
        }

        $this->ptLoopStack[$index]['has_empty_block'] = true;
        $emptyVar = $this->ptLoopStack[$index]['empty_var'];

        $contextPop = $this->compileLoopContextPop($this->ptLoopStack[$index]['loop_context_key']);

        return "<?php {$contextPop} endforeach; \$__env->popLoop(); \$loop = \$__env->getLastLoop(); if ({$emptyVar}): ?>";
    }

    /**
     * 解析出指令调用插件的名称和方法.
     *
     * @param $action
     *
     * @return array
     */
    protected function parserAction($action): array
    {
        $method = $name = null;
        if (($index = strpos($action, '::')) !== false) {
            $method = mb_substr($action, $index + 2, mb_strlen($action), 'UTF-8');
        }
        if (($key = strpos($action, ':')) !== false) {
            $len = mb_strlen($action);
            if (false !== $index) {
                $len = $index - $key - 1;
            }

            $name = mb_substr($action, $key + 1, $len, 'UTF-8');
        }

        return ['name' => $name, 'method' => $method];
    }

    /**
     * @param array{name:string|null, method:string|null} $data
     *
     * @return array{name:string, method:string}|null
     */
    private function resolveShortDirectiveAction(array $data): ?array
    {
        $method = trim((string) ($data['name'] ?? ''));
        if ('' === $method || null !== $data['method']) {
            return null;
        }

        $addonCode = AddonDirectivesManage::getInstance()->resolveDirectiveAddon($method);
        if (null === $addonCode) {
            return null;
        }

        return [
            'name' => $addonCode,
            'method' => $method,
        ];
    }

    private function compileHostSeoEchoDirective(array $match, string $helper, bool $escape = false): string
    {
        $parse = Parser::make(Arr::get($match, 5));
        $call = $this->buildHostSeoHelperCall($parse, $helper);

        if ($escape) {
            return "<?php echo e({$call}); ?>";
        }

        return "<?php echo {$call}; ?>";
    }

    private function buildHostSeoHelperCall(Parser $parse, string $helper): string
    {
        if ($parse->isParamEmpty()) {
            return '\\'.$helper.'()';
        }

        $valueExpression = $parse->hasAttribute('value')
            ? $this->normalizeHostDirectiveValue($parse->getAttribute('value'))
            : 'null';

        $options = [];
        foreach (['mode'] as $key) {
            if (!$parse->hasAttribute($key)) {
                continue;
            }

            $options[] = "'".$key."' => ".$this->normalizeHostDirectiveValue($parse->getAttribute($key));
        }

        $optionsExpression = [] === $options ? '[]' : '['.implode(', ', $options).']';

        return '\\'.$helper.'('.$valueExpression.', '.$optionsExpression.')';
    }

    private function buildHostSeoOverrideExpression(Parser $parse): string
    {
        $attributes = [];
        foreach ($parse->getAll() as $key => $value) {
            $attributes[] = "'".$key."' => ".$this->normalizeHostDirectiveValue($value);
        }

        return [] === $attributes ? '[]' : '['.implode(', ', $attributes).']';
    }

    /**
     * @param mixed $value
     */
    private function normalizeHostDirectiveValue($value): string
    {
        if (\is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (null === $value) {
            return 'null';
        }

        if (\is_int($value) || \is_float($value)) {
            return (string) $value;
        }

        $string = trim((string) $value);
        if ('' === $string) {
            return "''";
        }

        if (preg_match('/^-?\d+(?:\.\d+)?$/', $string) === 1) {
            return $string;
        }

        if (Str::startsWith($string, ['$', '['])) {
            return $string;
        }

        if (\in_array(strtolower($string), ['true', 'false', 'null'], true)) {
            return strtolower($string);
        }

        if (Str::contains($string, ["'"])) {
            $string = Str::replace("'", "\\'", $string);
        }

        return "'".$string."'";
    }

    /**
     * 编译为循环语句.
     *
     * @param Parser $parse
     * @param mixed  $name  调用方法名称
     *
     * @return string
     */
    protected function loopCompile(Parser $parse, $name): string
    {
        $directiveName = $name;
        $name = $this->wrapName($directiveName);
        $emptyVar = '$__ptLoopEmpty_'.$this->ptLoopCounter++;
        $initLoop = "<?php \$__currentLoopData = \\PTAdmin\\Addon\\Service\\AddonDirectivesActuator::handle({$name} {$this->buildDirectiveExpression($parse, $directiveName)}); {$emptyVar} = true; \$__env->addLoop(\$__currentLoopData);?>";

        $loopContextKey = $this->resolveDirectiveLoopContext((string) ($directiveName['name'] ?? ''), (string) ($directiveName['method'] ?? ''));
        $iterateLoop = "{$emptyVar} = false; \$__env->incrementLoopIndices(); \$loop = \$__env->getLastLoop(); ".$this->compileLoopContextPush($loopContextKey, $parse->getIteration());
        $empty = '';
        if ($parse->hasAttribute('empty')) {
            $empty = "<?php if (blank(\$__currentLoopData)): echo e({$parse->getEmpty()}); endif;?>";
        }

        $this->ptLoopStack[] = [
            'empty_var' => $emptyVar,
            'empty_attribute' => $empty,
            'has_empty_block' => false,
            'loop_context_key' => $loopContextKey,
        ];

        return "{$initLoop} <?php foreach(\$__currentLoopData as {$parse->getIteration()}): {$iterateLoop} ?>";
    }

    private function compilePtLoopEnd(): string
    {
        $loop = array_pop($this->ptLoopStack);
        if (!\is_array($loop)) {
            return '<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop();  ?>';
        }

        if ($loop['has_empty_block']) {
            return '<?php endif; ?>';
        }

        return '<?php '.$this->compileLoopContextPop($loop['loop_context_key']).' endforeach; $__env->popLoop(); $loop = $__env->getLastLoop();  ?>'.$loop['empty_attribute'];
    }

    /**
     * 返回参数类型编译，当指令参数设置了：out=name时，将结果返回.而不是循环输出.
     *
     * @param Parser $parse
     * @param mixed  $name  调用方法名称
     *
     * @return string
     */
    protected function outputCompile(Parser $parse, $name): string
    {
        $directiveName = $name;
        $name = $this->wrapName($directiveName);

        return "<?php {$parse->getOutput()} = \\PTAdmin\\Addon\\Service\\AddonDirectivesActuator::handle({$name} {$this->buildDirectiveExpression($parse, $directiveName)}); ?>";
    }

    /**
     * 编译为直接输出语句.
     *
     * @param Parser $parse
     * @param mixed  $name  调用方法名称
     */
    protected function echoCompile(Parser $parse, $name): string
    {
        $directiveName = $name;
        $name = $this->wrapName($directiveName);

        return "<?php echo \\PTAdmin\\Addon\\Service\\AddonDirectivesActuator::handle({$name} {$this->buildDirectiveExpression($parse, $directiveName)}); ?>";
    }

    /**
     * 编译if语句.
     *
     * @param Parser $parse
     * @param mixed  $name  调用方法名称
     *
     * @return string
     */
    protected function ifCompile(Parser $parse, $name): string
    {
        $directiveName = $name;
        $name = $this->wrapName($directiveName);

        return "<?php if(\\PTAdmin\\Addon\\Service\\AddonDirectivesActuator::handle({$name} {$this->buildDirectiveExpression($parse, $directiveName)})): ?>";
    }

    /**
     * @param array{name:string|null, method:string|null} $name
     */
    private function buildDirectiveExpression(Parser $parse, array $name): string
    {
        $extraAttributes = [];
        $context = $this->resolveDirectiveContext((string) ($name['name'] ?? ''), (string) ($name['method'] ?? ''));
        if (null !== $context) {
            $extraAttributes['__pt_context'] = "\\runtime_context_current()";
        }

        return $parse->buildExpression($extraAttributes);
    }

    private function resolveDirectiveContext(string $addonCode, string $method): ?string
    {
        if ('' === $addonCode || '' === $method) {
            return null;
        }

        $definition = AddonDirectivesManage::getInstance()->getDirective($addonCode, $method);
        if (!\is_array($definition)) {
            return null;
        }

        $context = trim((string) ($definition['context'] ?? ''));

        return '' === $context ? null : $context;
    }

    private function resolveDirectiveLoopContext(string $addonCode, string $method): ?string
    {
        if ('' === $addonCode || '' === $method) {
            return null;
        }

        $definition = AddonDirectivesManage::getInstance()->getDirective($addonCode, $method);
        if (!\is_array($definition)) {
            return null;
        }

        $key = trim((string) ($definition['loop_context'] ?? ''));

        return '' === $key ? null : $key;
    }

    private function compileLoopContextPush(?string $key, string $iteration): string
    {
        if (null === $key || '' === $key) {
            return '';
        }

        return "\\pt_directive_context_push('".str_replace("'", "\\'", $key)."', {$iteration});";
    }

    private function compileLoopContextPop(?string $key): string
    {
        if (null === $key || '' === $key) {
            return '';
        }

        return "\\pt_directive_context_pop('".str_replace("'", "\\'", $key)."');";
    }

    /**
     * 判断是否为结束标签.
     *
     * @param $action
     *
     * @return bool
     */
    private function isEnd($action): bool
    {
        if (false !== strpos($action, '::')) {
            $action = explode('::', $action);
        } else {
            $action = explode(':', $action);
        }
        $action = end($action);

        return 'end' === strtolower(mb_substr($action, 0, 3));
    }

    private function isEmpty($action): bool
    {
        if (false !== strpos($action, '::')) {
            $action = explode('::', $action);
        } else {
            $action = explode(':', $action);
        }
        $action = end($action);

        return 'empty' === strtolower((string) $action);
    }

    /**
     * 包装方法名称.
     *
     * @param $name
     *
     * @return string
     */
    private function wrapName($name): string
    {
        $str = '';
        foreach ($name as $value) {
            $str .= "'{$value}',";
        }

        return $str;
    }
}
