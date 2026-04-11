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

namespace PTAdmin\Addon\Service\Action;

use Illuminate\Support\Str;
use Illuminate\Filesystem\Filesystem;
use PTAdmin\Addon\Addon;
use PTAdmin\Addon\Exception\AddonException;
use PTAdmin\Addon\Service\AddonAdminResourceSynchronizer;
use PTAdmin\Addon\Service\AddonDirectivesManage;
use PTAdmin\Addon\Service\AddonHooksManage;
use PTAdmin\Addon\Service\AddonInjectsManage;

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
        $addons = Addon::getInstalledAddons();
        if (isset($addons[$this->getCode()])) {
            return $this->addon_path = $addons[$this->getCode()]['base_path'];
        }

        throw new AddonException(__('ptadmin-addon::messages.addon.not_exists', ['code' => $this->code]));
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function backupCurrentAddon(string $code = null): ?string
    {
        $code = $code ?? $this->code;
        if (!Addon::hasInstalledAddon($code)) {
            return null;
        }

        $filesystem = new Filesystem();
        $currentPath = Addon::getAddonPath($code);
        $backupPath = $this->getStorePath('backup'.\DIRECTORY_SEPARATOR.basename($currentPath));
        $filesystem->ensureDirectoryExists(\dirname($backupPath));
        $filesystem->copyDirectory($currentPath, $backupPath);

        return $backupPath;
    }

    public function findBackupAddonPath(): ?string
    {
        $base = $this->getStorePath('backup');
        if (!is_dir($base)) {
            return null;
        }

        $dirs = array_diff(scandir($base), ['.', '..']);
        foreach ($dirs as $dir) {
            $path = $base.\DIRECTORY_SEPARATOR.$dir;
            if (is_dir($path)) {
                return $path;
            }
        }

        return null;
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
        if (Addon::hasInstalledAddon($code) && !$force) {
            throw new AddonException(__('ptadmin-addon::messages.addon.installed_force', ['code' => $code]));
        }

        $obj = new self($code);
        if ($force) {
            $obj->backupCurrentAddon();
        }

        return $obj->addTask(AddonDownload::class, $versionId, $force)->addTask('refresh')->addTask(AddonInstall::class)->action();
    }

    public static function installLocal(string $packageFile, bool $force = false)
    {
        $obj = new self('__local__');

        return $obj->addTask(AddonLocalInstall::class, $packageFile, $force)->addTask('refresh')->action();
    }

    /**
     * 登录平台.
     *
     * @param $user
     * @param $password
     */
    public static function login($user, $password): void
    {
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
     * @param string $code  插件code
     * @param bool   $force 是否强制更新
     *
     * @return null|array|mixed
     */
    public static function upgrade(string $code, $versionId = 0, bool $force = false)
    {
        $obj = new self($code);

        return $obj->addTask(AddonUpgrade::class, $versionId, $force)->addTask('refresh')->action();
    }

    /**
     * 启用插件.
     *
     * @param $code
     */
    public static function enable($code): void
    {
        if (!Addon::hasInstalledAddon($code)) {
            throw new AddonException(__('ptadmin-addon::messages.addon.not_exists', ['code' => $code]));
        }
        $disableFile = Addon::getAddonPath($code, 'disable');
        if (!file_exists($disableFile)) {
            return;
        }

        $filesystem = new Filesystem();
        $bootstrap = Addon::getAddonBootstrap($code);
        if (null !== $bootstrap) {
            $bootstrap->enable();
        }
        app(AddonAdminResourceSynchronizer::class)->sync((string) $code);
        $filesystem->delete($disableFile);
        (new self($code))->refresh();
    }

    /**
     * 禁用插件.
     *
     * @param $code
     */
    public static function disable($code): void
    {
        if (!Addon::hasInstalledAddon($code)) {
            throw new AddonException(__('ptadmin-addon::messages.addon.not_exists', ['code' => $code]));
        }
        $disableFile = Addon::getAddonPath($code, 'disable');
        if (file_exists($disableFile)) {
            return;
        }

        $filesystem = new Filesystem();
        $bootstrap = Addon::getAddonBootstrap($code);
        if (null !== $bootstrap) {
            $bootstrap->disable();
        }
        app(AddonAdminResourceSynchronizer::class)->disable((string) $code);
        $filesystem->put($disableFile, '');
        (new self($code))->refresh();
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
            throw new AddonException(__('ptadmin-addon::messages.addon.dependency_uninstall_first', ['code' => $this->code]));
        }

        return true;
    }

    private function refresh(): bool
    {
        Addon::reset();
        AddonDirectivesManage::getInstance()->reset();
        AddonInjectsManage::getInstance()->reset();
        AddonHooksManage::getInstance()->reset();

        return true;
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
