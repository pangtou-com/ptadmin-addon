<?php

declare(strict_types=1);

namespace PTAdmin\AddonTests\Unit;

use Illuminate\Filesystem\Filesystem;
use PTAdmin\Addon\Service\AddonPackageSourceResolver;
use PTAdmin\AddonTests\TestCase;

class AddonPackageSourceResolverTest extends TestCase
{
    public function test_delivery_license_is_materialized_in_addon_directory(): void
    {
        $filesystem = new Filesystem();
        $base = storage_path('framework/testing/addon-delivery-package');
        $filesystem->deleteDirectory($base);
        $filesystem->ensureDirectoryExists($base.'/backend/.ptadmin');
        $filesystem->put($base.'/manifest.json', json_encode([
            'code' => 'demo-addon',
            'base_path' => 'DemoAddon',
        ], JSON_THROW_ON_ERROR));
        $filesystem->put($base.'/release.json', json_encode([
            'version' => '1.0.0',
        ], JSON_THROW_ON_ERROR));
        $filesystem->put($base.'/backend/.ptadmin/license.json', '{"kind":"delivery"}');

        try {
            $target = (new AddonPackageSourceResolver($filesystem))->resolve($base);

            self::assertSame($base.'/source/DemoAddon', $target);
            self::assertFileExists($target.'/.ptadmin/license.json');
            self::assertSame('{"kind":"delivery"}', file_get_contents($target.'/.ptadmin/license.json'));
        } finally {
            $filesystem->deleteDirectory($base);
        }
    }
}
