<?php

declare(strict_types=1);

namespace Tests\Unit\Setup;

use Setup\Installer;
use Tests\TestCase;

require_once __DIR__.'/../../../setup/Installer.php';

class InstallerTest extends TestCase
{
    private string|false $originalSetupToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalSetupToken = getenv('SETUP_TOKEN');
    }

    protected function tearDown(): void
    {
        $this->originalSetupToken === false
            ? putenv('SETUP_TOKEN')
            : putenv('SETUP_TOKEN='.$this->originalSetupToken);

        parent::tearDown();
    }

    public function test_environment_setup_token_enables_the_installer(): void
    {
        putenv('SETUP_TOKEN='.str_repeat('a', 32));

        $installer = new Installer(base_path());

        $this->assertTrue($installer->isEnabled());
        $this->assertTrue($installer->verifyToken(str_repeat('a', 32)));
        $this->assertFalse($installer->verifyToken(str_repeat('b', 32)));
    }

    public function test_preflight_reports_an_existing_environment_file(): void
    {
        $installer = new Installer(base_path());
        $checks = collect($installer->preflight())->keyBy('label');

        $this->assertArrayHasKey('DOM extension', $checks);
        $this->assertArrayHasKey('SimpleXML extension', $checks);
        $this->assertArrayHasKey('cURL extension', $checks);
        $this->assertArrayHasKey('Clean release directory', $checks);
        $this->assertFalse($checks['Clean release directory']['ok']);
        $this->assertSame('.env already exists', $checks['Clean release directory']['detail']);
    }
}
