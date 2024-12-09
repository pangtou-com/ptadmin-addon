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

use Illuminate\Filesystem\Filesystem;
use PTAdmin\Addon\Exception\AddonException;
use PTAdmin\Addon\Service\Traits\FormatOutputTrait;

abstract class AbstractAction
{
    use FormatOutputTrait;
    /** @var Filesystem */
    protected $filesystem;

    protected $code;

    /** @var AddonAction */
    protected $action;

    /** @var string 压缩文件名 */
    protected $filename;

    public function __construct($code)
    {
        if (!class_exists('ZipArchive')) {
            throw new AddonException('ZipArchive类不存在，请检查PHP环境是否开启Zip扩展');
        }
        $this->code = $code;
        $this->filesystem = new Filesystem();
        $this->filename = $this->code.'_'.time().'.zip';
    }

    public function __destruct()
    {
        if (null !== $this->action) {
            $this->filesystem->deleteDirectory($this->action->getStorePath());
        }
    }

    /**
     * 解压文件.
     *
     * @param $zip_file
     * @param $target
     */
    protected function unzip($zip_file, $target): void
    {
        if (!file_exists($zip_file)) {
            $this->error('下载文件不存在');

            return;
        }

        $this->info('开始解压');
        $zip = new \ZipArchive();
        $zip->open($zip_file);
        $zip->extractTo($target);
        $zip->close();
    }

    /**
     * 添加文件夹和子文件夹到压缩文件包中.
     *
     * @param mixed $folder
     * @param mixed $zipFile
     * @param mixed $exclusiveLength
     */
    protected function folderToZip($folder, &$zipFile, $exclusiveLength): void
    {
        $handle = opendir($folder);
        while (false !== $f = readdir($handle)) {
            if ('.' !== $f && '..' !== $f) {
                $filePath = "{$folder}/{$f}";
                $localPath = substr($filePath, $exclusiveLength);
                if (is_file($filePath)) {
                    $zipFile->addFile($filePath, $localPath);

                    continue;
                }
                if (is_dir($filePath)) {
                    $zipFile->addEmptyDir($localPath);
                    $this->folderToZip($filePath, $zipFile, $exclusiveLength);
                }
            }
        }

        closedir($handle);
    }

    /**
     * 创建zip文件.
     *
     * @param $target
     * @param $zipFilename
     */
    protected function zipDir($target, $zipFilename): void
    {
        $pathInfo = pathinfo($target);
        $parentPath = $pathInfo['dirname'];
        $dirName = $pathInfo['basename'];

        $zip = new \ZipArchive();
        $zip->open($zipFilename, \ZipArchive::CREATE);
        $zip->addEmptyDir($dirName);
        $this->folderToZip($target, $zip, \strlen("{$parentPath}/"));
        $zip->close();
    }

    /**
     * 基于文件内容计算文件夹MD5.
     *
     * @param $folderPath
     *
     * @return false|string
     */
    protected function getFolderMd5($folderPath)
    {
        if (!is_dir($folderPath)) {
            return false;
        }
        $md5Hashes = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($folderPath));
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $md5Hashes[] = md5_file($file->getRealPath());
            }
        }
        sort($md5Hashes);

        return md5(implode('', $md5Hashes));
    }
}
