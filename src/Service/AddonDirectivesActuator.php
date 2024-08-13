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

use Illuminate\Support\Facades\Cache;
use PTAdmin\Addon\Exception\AddonException;

/**
 * 插件指令执行器.
 * 1、获取插件指令
 * 2、验证是否缓存
 * 3、执行插件指令.
 */
class AddonDirectivesActuator
{
    /** @var string 插件名称 */
    private $addon_name;

    /** @var DirectivesDTO 插件指令参数对象 */
    private $transfer;

    /** @var float|int 默认缓存时间 */
    private $cache_ttl = 60 * 60 * 2;

    private function __construct($name, DirectivesDTO $transfer)
    {
        $this->addon_name = $name;
        $this->transfer = $transfer;
    }

    public static function handle($name, $method, DirectivesDTO $transfer)
    {
        if (!AddonDirectivesManage::getInstance()->has($name)) {
            throw new AddonException("未定义的标签指令【{$name}】");
        }
        if (blank($method)) {
            $method = AddonDirectives::DEFAULT_METHOD;
        }

        return (new self($name, $transfer))->call($method);
    }

    /**
     * 执行插件指令.
     *
     * @param $method
     *
     * @return null|mixed
     */
    private function call($method)
    {
        $provider = AddonDirectivesManage::getInstance()->getProvider($this->addon_name);
        // 插件是否允许缓存结果, debug模式时不允许缓存
        if ($provider->isAllowCaching($method) && false !== $this->transfer->getCache() && false === config('app.debug')) {
            $cacheKey = $provider->getCacheKey($method, $this->transfer);
            if (null !== ($cache = $this->getAddonResultCache($cacheKey))) {
                return $cache;
            }
            $results = $provider->execute($method, $this->transfer);
            $this->setAddonResultCache($cacheKey, $results, $this->transfer->get('cache_ttl', $this->cache_ttl));

            return $results;
        }

        return $provider->execute($method, $this->transfer);
    }

    /**
     * 获取插件指令结果缓存.
     *
     * @param $key
     *
     * @return mixed
     */
    private function getAddonResultCache($key)
    {
        $result = Cache::get($key);

        return null !== $result ? unserialize($result) : null;
    }

    /**
     * 设置插件指令结果缓存.
     *
     * @param $key
     * @param $result
     * @param mixed $ttl
     */
    private function setAddonResultCache($key, $result, $ttl): void
    {
        Cache::put($key, $result ? serialize($result) : null, $ttl);
    }
}
