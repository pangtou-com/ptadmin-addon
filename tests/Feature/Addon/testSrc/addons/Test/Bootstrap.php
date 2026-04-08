<?php

declare(strict_types=1);

namespace Addon\Test;

use PTAdmin\Addon\Service\AddonHooksManage;
use PTAdmin\Addon\Service\AddonInjectsManage;
use PTAdmin\Addon\Service\AddonDirectivesManage;
use PTAdmin\Addon\Service\BaseBootstrap;
use PTAdmin\Addon\Service\DirectiveDefinition;
use PTAdmin\Addon\Service\HookDefinition;
use PTAdmin\Addon\Service\InjectDefinition;
use PTAdmin\AddonTests\Feature\Addon\testSrc\addons\Test\TestAlipayService;
use PTAdmin\AddonTests\Feature\Addon\testSrc\addons\Test\TestDirectives;
use PTAdmin\AddonTests\Feature\Addon\testSrc\addons\Test\TestInjectServices;
use PTAdmin\AddonTests\Feature\Addon\testSrc\addons\Test\TestPaymentService;

class Bootstrap extends BaseBootstrap
{
    public function enable(): void
    {
        file_put_contents(base_path('addons/Test/enable.log'), 'enabled');
    }

    public function disable(): void
    {
        file_put_contents(base_path('addons/Test/disable.log'), 'disabled');
    }

    public function registerDirectives(AddonDirectivesManage $manager): void
    {
        $manager->register(
            'test',
            DirectiveDefinition::make('lists')
                ->title('列表展示')
                ->handler(TestDirectives::class)
                ->method('handle')
                ->type('loop')
                ->cacheable(true)
        );

        $manager->register(
            'test',
            DirectiveDefinition::make('auth')
                ->title('是否访问')
                ->handler(TestDirectives::class)
                ->method('auth')
                ->type('if')
                ->cacheable(true)
        );
    }

    public function registerInjects(AddonInjectsManage $manager): void
    {
        $manager->register(
            'test',
            'payment',
            InjectDefinition::make('wechat_pay')
                ->title('微信支付')
                ->types(['jsapi', 'qrcode'])
                ->handler(TestPaymentService::class)
        );

        $manager->register(
            'test',
            'payment',
            InjectDefinition::make('alipay')
                ->title('支付宝')
                ->types(['web'])
                ->handler(TestAlipayService::class)
        );

        $manager->register(
            'test',
            'auth',
            InjectDefinition::make('qq_login')
                ->title('QQ登录')
                ->types(['pc', 'mobile'])
                ->handler(TestInjectServices::class)
        );

        $manager->register(
            'test',
            'notify',
            InjectDefinition::make('site_notify')
                ->title('站内通知')
                ->types(['site', 'template'])
                ->handler(TestInjectServices::class)
        );

        $manager->register(
            'test',
            'storage',
            InjectDefinition::make('oss_storage')
                ->title('OSS 存储')
                ->types(['oss', 'private'])
                ->handler(TestInjectServices::class)
        );
    }

    public function registerHooks(AddonHooksManage $manager): void
    {
        $manager->register(
            'test',
            HookDefinition::make('payment.success')
                ->handler(TestDirectives::class.'@paymentSuccess')
                ->priority(10)
        );
    }
}
