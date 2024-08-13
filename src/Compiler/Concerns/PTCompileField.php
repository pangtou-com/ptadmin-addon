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

trait PTCompileField
{
    protected function PTCompileField($expression): string
    {
        if (!$expression) {
            return '';
        }
        $data = $this->parserAction($expression[1]);
        $field = '$'.$data['name'];
        // 判断赋值类型支持多种类型的数据处理
        if (isset($expression[6]) && $expression[6]) {
            $exp = explode(',', $expression[6]);
            $key = array_shift($exp);
            $default = $this->getDefaultParam($exp);
            if (null !== $default) {
                return "<?php echo e(data_get({$field},'{$key}', {$default})); ?>";
            }

            return "<?php echo e(data_get({$field},'{$key}')); ?>";
        }

        // 简易模式的处理处理：@PT:field.keywords1
        if (isset($expression[3]) && $expression[3]) {
            $exp = mb_strcut($expression[3], 1, mb_strlen($expression[3]));

            return "<?php echo e({$field}['{$exp}']); ?>";
        }

        return $expression[0];
    }

    /**
     * 获取表达式中存在默认值的情况.
     *
     * @param $exp
     *
     * @return null|string
     */
    private function getDefaultParam($exp): ?string
    {
        if (\count($exp) > 0) {
            $exp = trim(implode(',', $exp));
            $p = mb_substr($exp, 0, 1);
            if ('$' === $p) {
                return $exp;
            }
            if ("'" !== $p) {
                $exp = "'".$exp;
            }
            if ("'" !== mb_substr($exp, -1)) {
                $exp = $exp."'";
            }

            return $exp;
        }

        return null;
    }
}
