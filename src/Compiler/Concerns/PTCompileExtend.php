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

namespace PTAdmin\Addon\Compiler\Concerns;

use Illuminate\Support\Str;
use PTAdmin\Addon\Compiler\ParamParse;

trait PTCompileExtend
{
    public function test($str)
    {
        return $this->PTCompileEchos($str);
    }

    /**
     * 自定义模版输出内容：.
     *
     * 1、默认书写方式
     *
     *      { $field.field|default }
     * 2、支持函数调用方式
     *
     * 2-1、支持函数参数的写法
     *      { $field.field(default="dd", limit=10, pl="ccc", dateformat="") }
     * 2-2、函数参数可换行书写
     *      { $field.field(
     *                  default="dd",
     *                  limit="cc"
     *                  )
     *      }.
     *
     * 3、忽略编译，增加@后 后续的内容不做编译转换
     *
     *      @{ $field.field }.
     * 4、不进行html编码处理
     *      {: $field.field|default }.
     *
     * @param $value
     *
     * @return null|string|string[]
     */
    protected function PTCompileEchos($value)
    {
        // 忽略组件编译结果
        if (false !== strpos($value, '##BEGIN-COMPONENT-CLASS##')) {
            return $value;
        }
        $pattern = '/(@)?\{(:)?[ ]*(\$[a-zA-Z_](?:.+?)(\.[a-zA-Z_](?:.+?))*(\([^\)]*\))*)[ ]*}+/';
        $callback = function ($matches) {
            // @开头，不进行编译
            if ('@' === $matches[1]) {
                return mb_substr($matches[0], 1);
            }
            if (isset($matches[5]) && '' !== $matches[5]) {
                $matches[3] = str_replace($matches[5], '', $matches[3]);
            }
            $out = $this->getOutputContent($matches);
            // 当未使用 {: $field } 调用时，默认为转译处理
            // 默认为转译处理
            if (':' !== $matches[2]) {
                $out = sprintf($this->echoFormat, $out);
            }

            return "<?php echo {$out}; ?>";
        };

        return preg_replace_callback($pattern, $callback, $value);
    }

    /**
     * 获取自定义的输出方法.
     *
     * @return array
     */
    protected function getEchoMethods(): array
    {
        return array_merge(parent::getEchoMethods(), ['PTCompileEchos']);
    }

    /**
     * 获取组装的输出语句内容.
     *
     * @param array $matches
     *
     * @return string
     */
    private function getOutputContent(array $matches): string
    {
        $parse = ParamParse::make($matches[5] ?? '');
        $default = $this->getDefaultValue($parse, $matches);
        list($name, $key) = $this->getVariableName($matches);
        $params = [$name, $key];
        if (null !== $default) {
            $params[] = $default;
        }
        if (null === $key) {
            $out = "blank({$name}) ? {$default} : {$name}";
        } else {
            $params = implode(', ', $params);
            $out = "data_get({$params})";
        }

        // 支持时间格式化
        if ($parse->hasAttribute('format_date') && \function_exists('format_date')) {
            $out = "format_date({$out}, '{$parse->getAttribute('format_date')}')";
        }
        // 截取字符串
        if ($parse->hasAttribute('limit') && (int) $parse->getAttribute('limit') > 0) {
            $limit = (int) $parse->getAttribute('limit');

            $out = "\\Illuminate\\Support\\Str::limit({$out}, {$limit}, '{$parse->getAttribute('pl', '...')}')";
        }
        // 调用函数
        if ($parse->hasAttribute('func')) {
            $out = "{$parse->getAttribute('func')}({$out})";
        }

        return $out;
    }

    /**
     * 获取变量名称.
     *
     * @param array $matches
     *
     * @return array 变量名称和key信息
     */
    private function getVariableName(array $matches): array
    {
        $out = $matches[3];
        if (false !== strpos($matches[3], '|')) {
            $out = Str::before($matches[3], '|');
        }
        $out = explode('.', $out);
        $name = array_shift($out);

        return [
            $name,
            \count($out) > 0 ? '"'.implode('.', $out).'"' : null,
        ];
    }

    /**
     * 获取表达式中存在默认值的情况.
     *
     * @param ParamParse $parse   参数解析对象
     * @param mixed      $matches
     *
     * @return null|mixed
     */
    private function getDefaultValue(ParamParse $parse, $matches)
    {
        $default = null;
        if (false !== strpos($matches[3], '|')) {
            $default = Str::after($matches[3], '|');
        }
        if ($parse->hasAttribute('default')) {
            $default = $parse->getAttribute('default');
        }
        if (null === $default) {
            return null;
        }
        $p = mb_substr($default, 0, 1);
        // 当默认值为一个变量时，直接返回
        if ('$' === $p) {
            return $default;
        }
        if ("'" !== $p) {
            $default = "'".$default;
        }
        if ("'" !== mb_substr($default, -1)) {
            $default = $default."'";
        }

        return $default;
    }
}
