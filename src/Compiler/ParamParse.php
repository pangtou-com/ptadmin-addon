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
use PTAdmin\Addon\Exception\DirectivesException;

class ParamParse
{
    use ParseTrait;

    private $expression;
    private $id = '$field';
    private $encoding = 'UTF-8';
    private $result = [];

    private function __construct($expression)
    {
        $this->expression = $expression;
        if (!$this->expression) {
            return;
        }
        $this->eraseBracketNoise();
        $this->parse();
    }

    public static function make($expression): self
    {
        return new self($expression);
    }

    /**
     * 空字符串展示内容.
     *
     * @return string
     */
    public function getEmpty(): string
    {
        return $this->result['empty'] ?? config('view.empty', '');
    }

    /**
     * 迭代器字段名称.
     *
     * @return string
     */
    public function getIteration(): string
    {
        if (isset($this->result['id'])) {
            return Str::start($this->result['id'], '$');
        }

        return $this->id;
    }

    /**
     * 解析表达式.
     *
     * @return string
     */
    public function getExpression(): string
    {
        if (!$this->result) {
            return '\\PTAdmin\\Addon\\Service\\DirectivesDTO::build()';
        }
        $keys = array_keys($this->result);
        $sorted = Arr::sort($keys);
        $result = '';
        $exclude = ['empty', 'id'];
        foreach ($sorted as $key) {
            if (\in_array($key, $exclude, true)) {
                $this->checkId($key);

                continue;
            }
            $str = $this->result[$key];
            if (\is_string($str) && !Str::startsWith($str, ['$', '[', 'false', 'true'])) {
                if (Str::contains($str, ["'"])) {
                    $str = Str::replace("'", "\\'", $str);
                }
                $str = "'{$str}'";
            }

            $str = "'{$key}' => {$str}";

            $result = '' === $result ? $str : $result.', '.$str;
        }

        return "\\PTAdmin\\Addon\\Service\\DirectivesDTO::build([{$result}])";
    }

    /**
     * 去除多余的括号.
     */
    private function eraseBracketNoise(): void
    {
        $this->expression = mb_substr($this->expression, 1, mb_strlen($this->expression) - 2, $this->encoding);
    }

    /**
     * 校验ID参数的有效性，避免出现错误 要求ID必须为字符串.
     *
     * @param $key
     */
    private function checkId($key): void
    {
        if ('id' !== $key) {
            return;
        }
        $val = $this->result['id'];
        $reg = '/^[A-Za-z_]\w*$/';
        if (!preg_match($reg, $val)) {
            throw new DirectivesException("表达式中【{$this->expression}】【id】参数无效，必须由字母、数字和下划线组成，且不能以数字开头");
        }
    }
}
