<?php

declare(strict_types=1);

namespace PTAdmin\Addon\Commands;

use PTAdmin\Addon\Exception\AddonException;
use PTAdmin\Addon\Service\Action\AddonAction;

class AddonInit extends BaseAddonCommand
{
    protected $signature = 'addon:init
        {code : 插件编码}
        {--title= : 插件标题}
        {--frontend : 同步拉取前端模板}
        {--frontend-template=module : 前端模板标识，支持 module / micro-app}
        {--frontend-ref=main : 前端模板版本或分支，默认 main}
        {--frontend-source= : 指定前端模板源，仅支持 official，留空时使用 official}
        {--f|force : 强制覆盖已存在目录}';
    protected $description = '初始化插件开发脚手架';

    public function handle(): int
    {
        AddonAction::init(
            strtolower((string) $this->argument('code')),
            (string) $this->option('title'),
            (bool) $this->option('force'),
            (bool) $this->option('frontend'),
            $this->normalizeFrontendTemplate((string) $this->option('frontend-template')),
            (string) $this->option('frontend-ref'),
            $this->normalizeFrontendSource((string) $this->option('frontend-source'))
        );

        return 0;
    }

    private function normalizeFrontendTemplate(string $template): string
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

    private function normalizeFrontendSource(string $source): string
    {
        $normalized = strtolower(trim($source));
        $aliases = [
            '' => '',
            'auto' => '',
            'official' => 'official',
        ];

        if (isset($aliases[$normalized])) {
            return $aliases[$normalized];
        }

        throw new AddonException('前端模板源仅支持：official、auto');
    }
}
