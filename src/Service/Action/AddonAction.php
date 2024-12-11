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

namespace PTAdmin\Addon\Service\Action;

use Illuminate\Support\Str;
use PTAdmin\Addon\Addon;
use PTAdmin\Addon\Exception\AddonException;
use PTAdmin\Addon\Service\AddonConfigManager;
use PTAdmin\Addon\Service\AddonUtil;

class AddonAction
{
    /** @var string */
    private $store_path;

    /** @var string */
    private $addon_path;

    /** @var string */
    private $code;

    /**
     * 任务流
     *
     * @var array
     */
    private $tasks = [];

    private function __construct($code)
    {
        $this->code = $code;
    }

    public function __destruct()
    {
        Addon::refreshCache();
    }

    /**
     * 获取插件存储目录.
     *
     * @param null $path
     *
     * @return string
     */
    public function getStorePath($path = null): string
    {
        if (null === $this->store_path) {
            $this->store_path = storage_path('app'.\DIRECTORY_SEPARATOR.Str::random(6));
        }

        return $this->store_path.(null !== $path ? \DIRECTORY_SEPARATOR.$path : '');
    }

    /**
     * 获取插件路径.
     *
     * @return string
     */
    public function getAddonPath(): string
    {
        if (null !== $this->addon_path) {
            return $this->addon_path;
        }
        $addons = AddonUtil::getAddonsDirs();
        foreach ($addons as $addon) {
            $config = (new AddonConfigManager())->readAddonConfig($addon);
            if ($config['code'] === $this->getCode()) {
                return $this->addon_path = $addon;
            }
        }

        throw new AddonException("插件【{$this->code}】不存在");
    }

    public function getCode(): string
    {
        return $this->code;
    }

    /**
     * 安装插件.
     *
     * @param string $code
     * @param bool   $force
     * @param mixed  $versionId
     *
     * @return null|array|mixed
     */
    public static function install(string $code, $versionId = 0, bool $force = false)
    {
        $obj = new self($code);
        if (true === $force && Addon::hasAddon($code)) {
            $obj->addTask(AddonUninstall::class)->addTask(AddonDownload::class, $versionId)->addTask('refresh');
        } else {
            if (!Addon::hasAddon($code)) {
                $obj->addTask(AddonDownload::class, $versionId)->addTask('refresh');
            }
        }

        return $obj->addTask(AddonInstall::class)->action();
    }

    /**
     * 卸载插件.
     *
     * @param string $code
     * @param bool   $force
     *
     * @return null|array|mixed
     */
    public static function uninstall(string $code, bool $force = false)
    {
        $obj = new self($code);
        if (!$force) {
            $obj->addTask('checkRequired');
        }

        return $obj->addTask(AddonUninstall::class)->action();
    }

    /**
     * 更新插件.
     *
     * @param string $code      插件code
     * @param mixed  $versionId 插件版本ID
     * @param bool   $force     是否强制更新
     *
     * @return null|array|mixed
     */
    public static function upgrade(string $code, $versionId = 0, bool $force = false)
    {
        // 1、获取插件目录
        // 2、下载更新包
        // 3、比对更新包：文件是否有手动更新，是否有更新文件
        // 4、备份待更新资源
        // 5、更新资源
        $obj = new self($code);
        // 1、比对当前版本与实际版本是否有差异
        return $obj->addTask(AddonUpgrade::class)->action();
    }

    /**
     * 启用插件.
     *
     * @param $code
     */
    public static function enable($code): void
    {
        // 1、获取插件目录
        // 2、执行启用
    }

    /**
     * 禁用插件.
     *
     * @param $code
     */
    public static function disable($code): void
    {
        // 1、获取插件目录
        // 2、执行禁用
    }

    /**
     * 上传插件.
     *
     * @param $code
     *
     * @return null|array|mixed
     */
    public static function upload($code)
    {
        $obj = new self($code);

        return $obj->addTask(AddonUpload::class)->action();
    }

    /**
     * 校验插件依赖性.
     *
     * @return bool
     */
    protected function checkRequired(): bool
    {
        if (Addon::hasAddonRequired($this->getCode())) {
            throw new AddonException("插件【{$this->code}】依赖插件，请先卸载依赖插件,或使用 --force 参数强制卸载插件");
        }

        return true;
    }

    private function refresh(): void
    {
        Addon::reset();
    }

    private function addTask($class, ...$params): self
    {
        if (\is_array($class)) {
            $this->tasks[] = $class;
        } else {
            $this->tasks[] = method_exists($this, $class) ? [[$this, $class], $params] : [[new $class($this->getCode(), $this), 'handle'], $params];
        }

        return $this;
    }

    /**
     * 执行任务.
     *
     * @param mixed $res
     *
     * @return mixed
     */
    private function action(...$res)
    {
        if (0 === \count($this->tasks)) {
            return null;
        }
        while (\count($this->tasks) > 0) {
            if (null === $res) {
                break;
            }
            $res = \is_array($res) ? $res : [$res];
            $task = array_shift($this->tasks);
            if (\is_array($task)) {
                if (\is_array($task[0])) {
                    if (isset($task[1])) {
                        array_unshift($res, ...(array) $task[1]);
                    }
                    $task = $task[0];
                }
                $res = \call_user_func_array($task, $res);
            }
        }

        return $res;
    }
}
