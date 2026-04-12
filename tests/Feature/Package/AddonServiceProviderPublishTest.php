<?php

declare(strict_types=1);

namespace PTAdmin\Addon\Tests\Feature\Package;

use Illuminate\Support\ServiceProvider;
use PTAdmin\Addon\Providers\AddonServiceProvider;
use PTAdmin\AddonTests\TestCase;

class AddonServiceProviderPublishTest extends TestCase
{
    public function test_it_registers_ptadmin_publish_groups(): void
    {
        $allPublishes = ServiceProvider::pathsToPublish(AddonServiceProvider::class, 'ptadmin');
        $configPublishes = ServiceProvider::pathsToPublish(AddonServiceProvider::class, 'ptadmin-config');
        $langPublishes = ServiceProvider::pathsToPublish(AddonServiceProvider::class, 'ptadmin-lang');

        $this->assertCount(2, $allPublishes);
        $this->assertCount(1, $configPublishes);
        $this->assertCount(1, $langPublishes);
        $this->assertSame($configPublishes + $langPublishes, $allPublishes);
    }
}
