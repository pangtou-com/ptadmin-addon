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

namespace PTAdmin\Addon;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PTAdmin\Addon\Exception\AddonException;
use PTAdmin\Addon\Service\AddonManager;

/**
 * @method static string getVerify($code)      验证应用是否购买
 * @method static string getAddonBuy($code)    购买应用
 * @method static string getDownload($code)    下载应用
 * @method static string getAddonExists($code) 验证应用标识是否存在
 * @method static string getUserinfo()         获取会员信息
 * @method static string putAddonUpload()      上传插件
 * @method static string login($data = [])     上传插件
 */
class AddonApi
{
    private const BASE_URL = 'https://www.pangtou.com/api-addon/';
    private const TOKEN_KEY = 'ptadmin:addon_user_keys';

    /**
     * @param $method
     * @param $parameters
     *
     * @return array|mixed
     */
    public static function __callStatic($method, $parameters)
    {
        $method = Str::kebab(mb_substr($method, 3, mb_strlen($method) - 1));

        return (new static())->send($method, $parameters);
    }

    /**
     * 登陆.
     *
     * @param array $data
     *
     * @return array|mixed
     */
    public static function cloudLogin(array $data)
    {
        $results = (new static())->send('login', $data, false);
        Cache::put(self::TOKEN_KEY, serialize($results), now()->addDays());
        unset($results['token']);

        return $results;
    }

    public static function getCloudUserinfo()
    {
        $data = Cache::get(self::TOKEN_KEY);
        if (!blank($data)) {
            return unserialize($data);
        }

        return [];
    }

    /**
     * 验证是否存在code.
     *
     * @param $code
     *
     * @return bool
     */
    public static function getAddonCodeExists($code): bool
    {
        $results = (new static())->send("addon-exists/{$code}");

        return isset($results['is_exists']) && true === $results['is_exists'];
    }

    /**
     * 退出云市场.
     *
     * @return array|mixed
     */
    public static function cloudLogout()
    {
        $res = (new static())->send('logout');
        Cache::forget(self::TOKEN_KEY);

        return $res;
    }

    /**
     * 获取链接并下载.
     *
     * @param array $data
     *
     * @return array
     */
    public static function getAddonDownloadUrl(array $data): array
    {
        $results = (new static())->send('download', $data);
        if (!isset($results['url'])) {
            throw new AddonException('获取下载地址失败');
        }

        return $results;
    }

    /**
     * 获取云市场数据.
     *
     * @param array $data
     *
     * @return array|mixed
     */
    public static function getCloudMarket(array $data = [])
    {
        return self::getCacheData('addon-market', $data, function ($data) {
            return (new static())->send('cloud', $data, false);
        }, 60);
    }

    public static function getCacheData($type, $data, $callback, $ttl = 10)
    {
        $suffix = implode('', $data);
        $hash = sha1(request()->fingerprint().$suffix);
        $key = $type.':'.$hash;
        if (Cache::has($key)) {
            $results = @unserialize(Cache::get($key));
            if (null !== $results) {
                return $results;
            }
        }
        $results = $callback($data);
        Cache::put($key, serialize($results), now()->addMinutes($ttl));

        return $results;
    }

    /**
     * 获取我的插件.
     *
     * @param $data
     *
     * @return array|mixed
     */
    public static function getMyAddon($data)
    {
        return self::getCacheData('my-addon', $data, function ($data) {
            $result = (new static())->send('my-addon', $data);
            $addons = AddonManager::getInstance()->getInstalledAddonsCode();
            foreach ($result['results'] as &$value) {
                $value['is_install'] = (int) \in_array($value['addon_code'], $addons, true);
                $value['is_enable'] = (int) AddonManager::getInstance()->hasAddon($value['addon_code']);
            }
            unset($value);

            return $result;
        });
    }

    /**
     * 插件上传.
     *
     * @return array
     */
    public static function addonUpload(): array
    {
        return [];
    }

    /**
     * 发送请求
     *
     * @param $method
     * @param array $data
     * @param bool  $needLogin
     *
     * @return array|mixed
     */
    public function send($method, array $data = [], bool $needLogin = true)
    {
        $res = Http::withHeaders([
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ]);
        if ($needLogin) {
            $res = $res->withToken($this->getToken());
        }

        //TODO 这里设置为false来忽略SSL证书验证
        /**
         * $res = $res->withOptions([
         * 'verify' => false,
         * ]);.
         */
        $res = $res->post($this->getUrl($method), $this->addonArgsEncrypt($data));
        if (200 === $res->status()) {
            $results = $res->json();
            if (\in_array($results['code'], [401, 20000], true)) {
                Cache::forget(self::TOKEN_KEY);
            }
            if (0 !== $results['code']) {
                throw new AddonException($results['message']);
            }

            return $res->json('data');
        }

        throw new AddonException("请求失败,状态值为：【{$res->status()}】");
    }

    private function getToken(): string
    {
        $data = Cache::get(self::TOKEN_KEY);
        if (blank($data)) {
            throw new Exception\AddonException('未登录平台，请先登录平台后操作');
        }
        $data = unserialize($data);

        return Str::replace('Bearer ', '', $data['token'] ?? '');
    }

    private function getUrl($method): string
    {
        return self::BASE_URL.$method;
    }

    private function addonArgsEncrypt(array $param = []): array
    {
        $param['time'] = time();
        $param['state'] = \Illuminate\Support\Str::random();
        $param['sign'] = \PTAdmin\Addon\AesUtil::encrypt($param);

        return $param;
    }
}
