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

use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PTAdmin\Addon\Exception\DatabaseBackupException;

/**
 * 数据库备份操作.恢复操作.
 */
class Database
{
    /** @var string 数据表前缀占位符 */
    public static $prefix = '#PREFIX#';

    /** @var string 数据表结构文件 */
    public static $table_struct = 'ptadmin_table_struct';

    protected $backupFilesystem;

    public function __construct(DatabaseBackupFilesystem $backupFilesystem)
    {
        $this->backupFilesystem = $backupFilesystem;
    }

    /**
     * 备份所有数据表.
     */
    public function dumpAllTable(): void
    {
        $data = $this->getTables();
        foreach ($data as $datum) {
            $this->dumpTable($datum['table_name']);
        }
    }

    /**
     * 备份表结构.
     *
     * @param mixed      $allow_table 自定义允许备份的表
     * @param null|mixed $filename    备份文件名
     */
    public function dumpTableStruct($allow_table = [], $filename = null): void
    {
        $this->backupFilesystem->clearTable(self::$table_struct);
        $table = $this->getTables();
        foreach ($table as $val) {
            if (\count($allow_table) > 0 && !\in_array($val['table_name'], $allow_table, true)) {
                continue;
            }
            $exportSql = $this->createSql($val['table_name']);
            $this->backupFilesystem->write($exportSql, $filename ?? self::$table_struct);
        }
    }

    /**
     * 备份单个数据表.
     *
     * @param string $table
     */
    public function dumpTable(string $table): void
    {
        $this->backupFilesystem->clearTable($table);
        $exportSql = $this->exportDataSql($table);
        if ($exportSql) {
            $this->backupFilesystem->write($exportSql, $table);
        }
    }

    /**
     * 分页备份数据表.
     *
     * @param string $table
     * @param mixed  $page
     * @param mixed  $limit
     *
     * @return bool
     */
    public function dumpPageTable(string $table, $page, $limit): bool
    {
        if ($page <= 1) {
            $this->backupFilesystem->clearTable($table);
        }
        $exportSql = $this->exportDataSql($table, $page, $limit);
        if ($exportSql) {
            $this->backupFilesystem->write($exportSql, $table);

            return true;
        }

        return false;
    }

    /**
     * 根据备份文件恢复表结构.
     *
     * @param $dir
     * @param mixed $filename
     *
     * @throws Exception
     */
    public function restoreTableStruct($dir, $filename = null): void
    {
        if (is_dir($dir)) {
            if (null === $filename) {
                $filename = static::$table_struct.'.sql';
            }
            $dir = $dir.\DIRECTORY_SEPARATOR.$filename;
        }

        $this->restoreData($dir.\DIRECTORY_SEPARATOR.$filename);
    }

    /**
     * 恢复目录下所有数据.
     *
     * @param $dir
     *
     * @throws Exception
     */
    public function restoreDirDataAll($dir): void
    {
        if (!is_dir($dir)) {
            throw new DatabaseBackupException('目录不存在');
        }
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            if (Str::endsWith($file, '.sql')) {
                $this->restoreData($dir.\DIRECTORY_SEPARATOR.$file);
            }
        }
    }

    /**
     * 按文件恢复数据.
     *
     * @param $file
     */
    public function restoreData($file): void
    {
        if (!is_file($file)) {
            return;
        }
        $content = file_get_contents($file);
        $content = Str::of($content)->replace(static::$prefix, $this->getPrefix());
        DB::beginTransaction();

        try {
            DB::unprepared((string) $content);
            DB::commit();
        } catch (Exception $exception) {
            DB::rollBack();

            throw $exception;
        }
    }

    /**
     * 导出表数据sql.
     *
     * @param string $table 表名称
     * @param int    $page  当前读取位置
     * @param int    $limit 当前读取位置
     */
    private function exportDataSql(string $table, int $page = 0, int $limit = 1000): ?string
    {
        $filterMap = app('db')->setTablePrefix('')->table($table);
        if (0 !== $page) {
            $filterMap->offset($this->getOffset($page, $limit))->limit($limit);
        }
        $result = $filterMap->get();
        if (!$result->count()) {
            return null;
        }

        // 拼接insert语句
        $fields = array_keys(collect($result[0])->toArray());
        $fields = '`'.implode('`, `', $fields).'`';
        $table = get_table_name($table);
        $insertSql = "INSERT INTO `{$table}` ( {$fields} ) VALUES \n";
        $insertSql = $this->replacePrefix($insertSql, $table);

        $batch = [];
        foreach ($result as $val) {
            $batch[] = $this->implodeInsertSql(collect($val)->toArray());
        }
        $batchSql = implode(",\n", $batch).";\n";

        return $insertSql.$batchSql;
    }

    /**
     * 获取当前数据库所有表.
     *
     * @return array
     */
    private function getTables(): array
    {
        $sql = 'select table_name from information_schema.TABLES where TABLE_SCHEMA= ?';
        $result = DB::select($sql, [DB::connection()->getDatabaseName()]);

        return collect($result)->map(function ($item) {
            return collect($item)->toArray();
        })->toArray();
    }

    private function getPrefix(): ?string
    {
        return config('database.prefix');
    }

    /**
     * 将数据表字段替换为增加占位符的方式.
     *
     * @param $sql
     * @param $table
     *
     * @return string
     */
    private function replacePrefix($sql, $table): string
    {
        if (blank($this->getPrefix())) {
            $preTable = static::$prefix.$table;
        } else {
            $preTable = Str::replaceFirst($this->getPrefix(), static::$prefix, $table);
        }

        return Str::replaceFirst($table, $preTable, $sql);
    }

    /**
     * 生成创建数据表的sql语句(包含表结构和索引).
     *
     * @param string $table 数据表名称
     *
     * @return string
     */
    private function createSql(string $table): string
    {
        $table = get_table_name($table);

        $tableIf = "DROP TABLE IF EXISTS `{$table}`;\n";
        $tableIf = $this->replacePrefix($tableIf, $table);
        $tmpSql = DB::select("SHOW CREATE TABLE `{$table}`");
        $tmpSql = data_get($tmpSql[0], 'Create Table');
        $tmpSql = $this->replacePrefix($tmpSql, $table);

        return $tableIf.$tmpSql.";\n";
    }

    /**
     * 获取偏移量.
     *
     * @param int   $page
     * @param mixed $limit
     *
     * @return int
     */
    private function getOffset(int $page, $limit): int
    {
        $page = max(($page - 1), 0);

        return $page * $limit;
    }

    private function implodeInsertSql($result): string
    {
        $sql = [];
        foreach ($result as $val) {
            $sql[] = (null === $val) ? 'NULL' : "'{$val}'";
        }

        return '('.implode(',', $sql).')';
    }
}
