<?php

declare(strict_types=1);

namespace PTAdmin\Addon\Commands;

use PTAdmin\Addon\Exception\AddonException;
use PTAdmin\Addon\Service\Action\AddonAction;

class AddonFrontendPull extends BaseAddonCommand
{
    protected $signature = 'addon:frontend:pull
        {code : 插件编码}
        {--template=module : 前端模板标识，支持 module / micro-app}
        {--ref=main : 模板版本或分支，默认 main}
        {--source= : 指定模板源，支持 official / github，留空时按区域自动选择}
        {--f|force : 强制覆盖已存在 Frontend 目录}';

    protected $description = '拉取插件前端模板到 Frontend 目录';

    public function handle(): int
    {
        $template = $this->normalizeTemplate((string) $this->option('template'));
        $source = $this->normalizeSource((string) $this->option('source'));

        AddonAction::pullFrontend(
            strtolower((string) $this->argument('code')),
            $template,
            (string) $this->option('ref'),
            $source,
            (bool) $this->option('force')
        );

        return 0;
    }

    private function normalizeTemplate(string $template): string
    {
        $normalized = strtolower(trim($template));
        $aliases = [
            '' => 'module',
            'module' => 'module',
            'modules' => 'module',
            'micro-app' => 'micro-app',
            'micro_app' => 'micro-app',
            'microapp' => 'micro-app',
        ];

        if (isset($aliases[$normalized])) {
            return $aliases[$normalized];
        }

        throw new AddonException('前端模板仅支持：module、micro-app');
    }

    private function normalizeSource(string $source): string
    {
        $normalized = strtolower(trim($source));
        $aliases = [
            '' => '',
            'auto' => '',
            'github' => 'github',
            'official' => 'official',
        ];

        if (isset($aliases[$normalized])) {
            return $aliases[$normalized];
        }

        throw new AddonException('前端模板源仅支持：official、github、auto');
    }
}
