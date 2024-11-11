<?php

declare(strict_types=1);

/**
 *  PTAdmin
 *  ============================================================================
 *  版权所有 2022-2024 重庆胖头网络技术有限公司，并保留所有权利。
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
use PTAdmin\Addon\Compiler\Concerns\PTCompileExtend;
use PTAdmin\Addon\Exception\DirectivesException;
use PTAdmin\Addon\Service\AddonDirectivesManage;

class PTCompiler extends BladeCompiler
{
    use PTCompileExtend;

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
        // 当为结束标签时返回结束标签内容
        if ($this->isEnd($match[1])) {
            return $this->end($match, $data);
        }

        $instance = AddonDirectivesManage::getInstance();

        // 判断是否为插件自定义指令
        if ($instance->has($data['name'])) {
            if ($instance->isLoop($data['name'], $data['method'])) {
                return $this->loopCompile($match, $data);
            }

            return $this->ifCompile($match, $data);
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
     * 解析出是否为结束标签.
     *
     * @pt:end // 简介默认为foreach关闭
     * @pt:endarc // 默认为foreach关闭
     * @pt:demo::endarc    // 根据配置关闭
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
            return '<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop();  ?>';
        }
        $instance = AddonDirectivesManage::getInstance();
        if (isset($data['name']) && $instance->has($data['name'])) {
            $data['method'] = mb_substr($data['method'], 3);
            if ($instance->isLoop($data['name'], $data['method'])) {
                return '<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop();  ?>';
            }

            return '<?php endif; ?>';
        }

        return $this->callLaravelDirective($match);
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
     * 编译为循环语句.
     *
     * @param $match
     * @param mixed $name
     *
     * @return string
     */
    protected function loopCompile($match, $name): string
    {
        $parse = ParamParse::make(Arr::get($match, 5));
        $name = $this->getParam($name);
        $initLoop = "<?php \$__currentLoopData = \\PTAdmin\\Addon\\Service\\AddonDirectivesActuator::handle({$name} {$parse->getExpression()}); \$__env->addLoop(\$__currentLoopData);?>";

        $iterateLoop = '$__env->incrementLoopIndices(); $loop = $__env->getLastLoop();';
        $empty = '';
        if (!blank($parse->getEmpty())) {
            $char = mb_substr($parse->getEmpty(), 0, 1, 'UTF-8');
            if ('$' === $char) {
                $empty = "<?php if (blank(\$__currentLoopData)): echo e({$parse->getEmpty()}); endif;?>";
            } else {
                $empty = "<?php if (blank(\$__currentLoopData)): echo e('{$parse->getEmpty()}'); endif;?>";
            }
        }

        return "{$initLoop} {$empty} <?php foreach(\$__currentLoopData as {$parse->getIteration()}): {$iterateLoop} ?>";
    }

    /**
     * 返回参数类型编译，当指令参数设置了：out=name时，将结果返回.而不是循环输出
     * TODO 待处理.
     *
     * @param $match
     * @param $name
     *
     * @return string
     */
    protected function outCompile($match, $name): string
    {
        $parse = ParamParse::make(Arr::get($match, 5));
        $name = $this->getParam($parse->getExpression());

        return "<?php echo e({$name}); ?>";
    }

    /**
     * 编译if语句.
     *
     * @param $match
     * @param mixed $name
     *
     * @return string
     */
    protected function ifCompile($match, $name): string
    {
        $parse = ParamParse::make(Arr::get($match, 5));
        $name = $this->getParam($name);

        return "<?php if(\\PTAdmin\\Addon\\Service\\AddonDirectivesActuator::handle({$name} {$parse->getExpression()})): ?>";
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

    private function getParam($name): string
    {
        $str = '';
        foreach ($name as $value) {
            $str .= "'{$value}',";
        }

        return $str;
    }
}
