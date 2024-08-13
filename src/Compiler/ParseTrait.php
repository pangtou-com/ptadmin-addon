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

/** 未找到标志 */
const TAG_NO_STATE = 0;

/** 已找到标志位置 */
const TAG_STATE = 1;

trait ParseTrait
{
    private $start;

    /**
     * @var string 当前找到的标志
     */
    private $tagChar;

    /**
     * @var int 当前循环状态
     *          状态说明：
     *          0    未找到标志
     *          1    已找到开始标志 ，等待结束标志
     */
    private $currentState = TAG_NO_STATE;

    private $temp = '';

    /**
     * 参数解析.
     */
    protected function parse(): void
    {
        $len = mb_strlen($this->expression, $this->encoding);
        $tag = '';
        for ($i = 0; $i < $len; ++$i) {
            $char = mb_substr($this->expression, $i, 1, $this->encoding);
            // 当字符为空并且字符为特殊字符时, 则认为字符无效
            if ('' === $this->temp && \in_array($char, ["\n", "\t", "\r"], true)) {
                continue;
            }

            // 当字符为空，并且没有找到标志位时，则认为字符无效
            if (blank($char) && TAG_NO_STATE === $this->currentState) {
                continue;
            }

            // 找到结束标记
            if ($this->endTag($char)) {
                $this->addResult($tag, $this->temp);
                // 一般情况下结束标记后为逗号，所以需要跳过逗号
                $i = $this->findEndTagIndex($i, $len);

                continue;
            }

            // 找到赋值符号，并且当前状态为未找到标志位
            if ($this->symbol($char) && TAG_NO_STATE === $this->currentState) {
                $tag = $this->temp;
                $this->addResult($tag, '');
                $i = $this->findVarStartTag($i, $len, $tag);

                continue;
            }
            $this->setTemp($this->temp.$char);
        }

        if ('' !== $tag && '' !== $this->temp) {
            $this->addResult($tag, $this->temp);
        }
    }

    /**
     * 找到结束标记所在位置.
     *
     * @param $currentIndex
     * @param $len
     *
     * @return int
     */
    private function findEndTagIndex($currentIndex, $len): int
    {
        $i = $currentIndex + 1;
        for (; $i < $len; ++$i) {
            $char = mb_substr($this->expression, $i, 1, $this->encoding);
            if (',' === $char) {
                return $i;
            }
        }

        return $currentIndex;
    }

    /**
     * 写入数据.
     *
     * @param $tag
     * @param $temp
     */
    private function addResult($tag, $temp): void
    {
        $tag = trim($tag);
        $this->result[$tag] = $temp;

        // 写入数据后需要清理临时数据
        $this->setTemp('');
    }

    /**
     * 找变量的开始标记.
     *
     * @param $currentIndex
     * @param $len
     * @param mixed $currentTag
     *
     * @return int
     */
    private function findVarStartTag($currentIndex, $len, $currentTag): int
    {
        $i = $currentIndex + 1;
        $temp = '';
        for (; $i < $len; ++$i) {
            $char = mb_substr($this->expression, $i, 1, $this->encoding);
            if ($this->startTag($char)) {
                // 当未找到下一个标志位置，但是找到结束位置
                if (',' === $char && TAG_NO_STATE === $this->currentState) {
                    $this->addResult($currentTag, trim($temp));
                    $this->cleanUp();
                }

                return $i;
            }
            $temp .= $char;
        }

        return $currentIndex;
    }

    private function symbol($char): bool
    {
        if ('=' === $char) {
            return true;
        }

        return false;
    }

    /**
     * 开始位标签.
     * 3种情况
     * 1、字符串
     * 2、数组
     * 3、变量.
     *
     * @param $char
     *
     * @return bool
     */
    private function startTag($char): bool
    {
        if (',' === $char) {
            return true;
        }
        if ('"' === $char || "'" === $char) {
            $this->currentState = TAG_STATE;
            $this->tagChar = $char;

            return true;
        }

        // 处理数组类型
        if ('[' === $char) {
            $this->currentState = TAG_STATE;
            $this->tagChar = ']';

            $this->setTemp($char);

            return true;
        }

        // 处理变量类型
        if ('$' === $char) {
            $this->currentState = TAG_STATE;

            $this->setTemp($char);
            // 找到变量类型后已逗号结束
            $this->tagChar = ',';

            return true;
        }

        return false;
    }

    /**
     * 结束标签.
     *
     * @param $char
     *
     * @return bool
     */
    private function endTag($char): bool
    {
        if ($this->tagChar === $char && TAG_STATE === $this->currentState) {
            $this->cleanUp();
            // 当为数组的情况下，需要将结束标志加上
            if (']' === $char) {
                $this->setTemp($this->temp.']');
            }

            return true;
        }

        return false;
    }

    /**
     * 清理数据.
     */
    private function cleanUp(): void
    {
        $this->tagChar = '';
        $this->currentState = TAG_NO_STATE;
    }

    /**
     * 设置临时数据.
     *
     * @param $temp
     */
    private function setTemp($temp): void
    {
        $this->temp = $temp;
    }
}
