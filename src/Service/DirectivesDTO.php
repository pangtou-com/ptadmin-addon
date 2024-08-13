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

namespace PTAdmin\Addon\Service;

use Illuminate\Support\Str;

/**
 * 指令参数传递.
 */
class DirectivesDTO
{
    private $param;

    private function __construct($param)
    {
        $this->param = $param;
    }

    public function __call($name, $arguments)
    {
        $name = lcfirst(Str::afterLast($name, 'get'));
        if (!isset($this->param[$name])) {
            return $arguments[0] ?? null;
        }

        return $this->param[$name];
    }

    public function __get($name)
    {
        return $this->param[$name] ?? null;
    }

    public function __toString(): string
    {
        return md5(serialize($this->param));
    }

    /**
     * 获取限制条数.
     *
     * @param int $default
     *
     * @return int|mixed
     */
    public function getLimit(int $default = 10)
    {
        if (blank($default)) {
            $default = 10;
        }

        return $this->param['limit'] ?? $default;
    }

    public function getOrder($default = ['id' => 'desc']): array
    {
        $result = [];
        $order = $this->param['order'] ?? $default;
        if (!\is_array($order)) {
            $order = explode(',', $order);
        }

        foreach ($order as $key => $val) {
            if (is_numeric($key)) {
                $val = explode('|', $val);
                $result[trim($val[0])] = trim($val[1] ?? 'desc');
            } else {
                $result[trim($key)] = trim($val);
            }
        }

        return $result;
    }

    /**
     * 是否缓存.
     *
     * @return bool
     */
    public function getCache(): ?bool
    {
        return $this->param['cache'] ?? null;
    }

    public function getLang()
    {
        return $this->param['lang'] ?? config('app.locale', 'zh-CN');
    }

    public function get($key, $default = null)
    {
        return $this->param[$key] ?? $default;
    }

    public function all()
    {
        return $this->param;
    }

    public static function build($param = []): self
    {
        return new self($param);
    }
}
