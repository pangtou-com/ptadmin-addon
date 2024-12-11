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

use Illuminate\Support\Facades\Http;
use PTAdmin\Addon\AddonApi;
use PTAdmin\Addon\Service\AddonConfigManager;

/**
 * 插件下载安装.
 */
final class AddonDownload extends AbstractAction
{
    /** @var int 当前进度 */
    private $progress = 0;

    public function handle($versionId = 0): ?string
    {
        $this->filesystem->ensureDirectoryExists($this->action->getStorePath());
        $data = $this->getDownloadUrl([
            'code' => $this->code,
            'addon_version_id' => $versionId,
        ]);
        if (null === $data) {
            return null;
        }
        $this->downloadAddon($data['url']);

        return $this->move();
    }

    private function move(): ?string
    {
        $base = $this->getUnzipDirname();
        if ('' === $base) {
            $this->error("插件【{$this->code}】解压失败，请检查插件是否完整或联系官方");

            return null;
        }
        $manage = new AddonConfigManager();
        $info = $manage->readAddonConfig($base);
        if (null === $info) {
            $this->error("插件【{$this->code}】未找到配置文件，请检查插件是否完整或联系官方");

            return null;
        }
        $this->info("开始安装插件【{$info['title']}】");
        $target = base_path('addons'.\DIRECTORY_SEPARATOR.basename($base));
        $this->filesystem->ensureDirectoryExists($target);
        $this->filesystem->moveDirectory($base, $target);

        return $base;
    }

    private function getDownloadUrl($data): ?array
    {
        return AddonApi::getAddonDownloadUrl($data);
    }

    private function downloadAddon($url): void
    {
        $response = Http::withOptions([
            'progress' => function ($total, $downloaded): void {
                if ($total > 0) {
                    $progress = (int) ($downloaded / $total * 100);
                    if ($progress !== $this->progress) {
                        $this->progress = $progress;
                        $this->info("下载进度：【{$progress}%】");
                    }
                }
            },
        ])->get($url);
        if ($response->successful()) {
            file_put_contents($this->getDownloadFilename(), $response->body());
            $this->unzip($this->getDownloadFilename(), $this->action->getStorePath());
        } else {
            $this->error('插件下载失败:'.json_encode($response->json()), $response->json());
        }
    }

    private function getDownloadFilename(): string
    {
        return $this->action->getStorePath($this->filename);
    }

    private function getUnzipDirname(): string
    {
        clearstatcache();
        $base = $this->action->getStorePath();
        $dirs = scandir($base);
        foreach ($dirs as $dir) {
            if ('.' !== $dir && '..' !== $dir && is_dir($base.\DIRECTORY_SEPARATOR.$dir)) {
                return $base.\DIRECTORY_SEPARATOR.$dir;
            }
        }

        return '';
    }
}
