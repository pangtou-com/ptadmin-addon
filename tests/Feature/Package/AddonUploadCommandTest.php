<?php

declare(strict_types=1);

namespace PTAdmin\Addon\Tests\Feature\Package;

use PTAdmin\Addon\Commands\AddonUpload;
use PTAdmin\AddonTests\TestCase;

class AddonUploadCommandTest extends TestCase
{
    public function test_upload_command_registers_the_release_version_option_without_conflicting_with_artisan(): void
    {
        $command = new AddonUpload();

        $this->assertTrue($command->getDefinition()->hasOption('ver'));
        $this->assertTrue($command->getDefinition()->hasOption('skip-build'));
        $this->assertTrue($command->getDefinition()->hasOption('title'));
        $this->assertTrue($command->getDefinition()->hasOption('description'));
        $this->assertTrue($command->getDefinition()->hasOption('changelog-file'));
        $this->assertTrue($command->getDefinition()->hasOption('major'));
        $this->assertFalse($command->getDefinition()->hasOption('version'));

        $this->artisan('help', ['command' => 'addon:upload'])
            ->assertExitCode(0);
    }
}
