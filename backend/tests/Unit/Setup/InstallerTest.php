<?php

declare(strict_types=1);

namespace Tests\Unit\Setup;

use App\Services\AttendanceReconciliationService;
use App\Services\Hr\EmployeeImportService;
use RuntimeException;
use Setup\Installer;
use Symfony\Component\Process\Process;
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

    public function test_preflight_reports_an_existing_environment_file_and_zip_extension(): void
    {
        $installer = new Installer(base_path());
        $checks = collect($installer->preflight())->keyBy('label');

        $this->assertArrayHasKey('DOM extension', $checks);
        $this->assertArrayHasKey('SimpleXML extension', $checks);
        $this->assertArrayHasKey('cURL extension', $checks);
        $this->assertArrayHasKey('Zip extension', $checks);
        $this->assertArrayHasKey('Clean release directory', $checks);
        $this->assertFalse($checks['Clean release directory']['ok']);
        $this->assertSame('.env already exists', $checks['Clean release directory']['detail']);
    }

    public function test_setup_lock_returns_404_for_both_wizard_and_system_requirements(): void
    {
        $lockDir = base_path('storage/app/installer');
        $lockFile = $lockDir.'/installed.json';
        $createdDir = false;

        if (! is_dir($lockDir)) {
            mkdir($lockDir, 0755, true);
            $createdDir = true;
        }

        file_put_contents($lockFile, json_encode(['installed_at' => now()->toIso8601String()]));

        try {
            $wizardRes = $this->runScript(base_path('public/setup/index.php'), [
                'server' => ['REQUEST_METHOD' => 'GET', 'SCRIPT_NAME' => '/setup/index.php'],
            ]);
            $this->assertSame(404, $wizardRes['status']);
            $this->assertStringContainsString('Installer unavailable', $wizardRes['output']);

            $reqRes = $this->runScript(dirname(base_path()).'/setup/system-requirements.php', [
                'server' => ['REQUEST_METHOD' => 'GET', 'SCRIPT_NAME' => '/setup/system-requirements.php'],
            ]);
            $this->assertSame(404, $reqRes['status']);
            $this->assertStringContainsString('Installer unavailable', $reqRes['output']);
        } finally {
            @unlink($lockFile);
            if ($createdDir) {
                @rmdir($lockDir);
            }
        }
    }

    public function test_installer_disabled_returns_403_when_no_token_configured(): void
    {
        $res = $this->runScript(base_path('public/setup/index.php'), [
            'server' => ['REQUEST_METHOD' => 'GET', 'SCRIPT_NAME' => '/setup/index.php'],
            'env' => ['SETUP_TOKEN' => null],
        ]);

        $this->assertSame(403, $res['status']);
        $this->assertStringContainsString('Installer disabled', $res['output']);
        $this->assertStringContainsString('Set a <code>SETUP_TOKEN</code>', $res['output']);
    }

    public function test_wizard_csrf_and_token_authorization_flow(): void
    {
        $token = str_repeat('z', 32);
        $csrf = bin2hex(random_bytes(16));
        $script = base_path('public/setup/index.php');

        // 1. POST without valid CSRF
        $invalidCsrfRes = $this->runScript($script, [
            'server' => ['REQUEST_METHOD' => 'POST', 'SCRIPT_NAME' => '/setup/index.php'],
            'post' => ['action' => 'authorize', '_token' => 'invalid_csrf', 'setup_token' => $token],
            'session' => ['csrf' => $csrf],
            'session_name' => 'erp_setup',
            'env' => ['SETUP_TOKEN' => $token],
        ]);
        $this->assertSame(200, $invalidCsrfRes['status']);
        $this->assertStringContainsString('The setup session expired. Refresh the page and try again.', $invalidCsrfRes['output']);

        // 2. POST with valid CSRF but invalid token
        $invalidTokenRes = $this->runScript($script, [
            'server' => ['REQUEST_METHOD' => 'POST', 'SCRIPT_NAME' => '/setup/index.php'],
            'post' => ['action' => 'authorize', '_token' => $csrf, 'setup_token' => 'wrong_token_value'],
            'session' => ['csrf' => $csrf],
            'session_name' => 'erp_setup',
            'env' => ['SETUP_TOKEN' => $token],
        ]);
        $this->assertSame(200, $invalidTokenRes['status']);
        $this->assertStringContainsString('Invalid setup token.', $invalidTokenRes['output']);

        // 3. POST with valid CSRF and valid token (redirects to step 2 with 302)
        $validRes = $this->runScript($script, [
            'server' => ['REQUEST_METHOD' => 'POST', 'SCRIPT_NAME' => '/setup/index.php'],
            'post' => ['action' => 'authorize', '_token' => $csrf, 'setup_token' => $token],
            'session' => ['csrf' => $csrf],
            'session_name' => 'erp_setup',
            'env' => ['SETUP_TOKEN' => $token],
        ]);
        $this->assertSame(302, $validRes['status']);
        $this->assertTrue($validRes['session']['authorized'] ?? false);
    }

    public function test_public_system_requirements_get_accessible_without_token(): void
    {
        $script = dirname(base_path()).'/setup/system-requirements.php';

        $res = $this->runScript($script, [
            'server' => ['REQUEST_METHOD' => 'GET', 'SCRIPT_NAME' => '/setup/system-requirements.php'],
            'env' => ['SETUP_TOKEN' => null],
        ]);

        $this->assertSame(200, $res['status']);
        $this->assertStringContainsString('System Requirements Check', $res['output']);
        $this->assertStringContainsString('ERP · Server Readiness', $res['output']);
        $this->assertStringContainsString('Core requirements', $res['output']);
        // Verify no credentials or phpinfo HTML output leaked
        $this->assertStringNotContainsString('<title>phpinfo()</title>', $res['output']);
        $this->assertStringNotContainsString('DB_PASSWORD', $res['output']);
        $this->assertStringNotContainsString('APP_KEY', $res['output']);
    }

    public function test_system_requirements_safe_fix_authorization_and_csrf(): void
    {
        $script = dirname(base_path()).'/setup/system-requirements.php';
        $validToken = str_repeat('k', 32);
        $csrf = bin2hex(random_bytes(16));

        // 1. Missing / invalid CSRF returns 400 Bad Request
        $badCsrfRes = $this->runScript($script, [
            'server' => ['REQUEST_METHOD' => 'POST', 'SCRIPT_NAME' => '/setup/system-requirements.php'],
            'post' => ['action' => 'repair_directory', '_token' => 'bad_csrf', 'target' => 'Storage writable', 'setup_token' => $validToken],
            'session' => ['system_requirements_csrf' => $csrf],
            'session_name' => 'erp_setup_requirements',
            'env' => ['SETUP_TOKEN' => $validToken],
        ]);
        $this->assertSame(400, $badCsrfRes['status']);
        $this->assertStringContainsString('ไม่สามารถยืนยันคำขอแก้ไขได้', $badCsrfRes['output']);

        // 2. Valid CSRF but missing or invalid SETUP_TOKEN returns 403 Forbidden
        $badTokenRes = $this->runScript($script, [
            'server' => ['REQUEST_METHOD' => 'POST', 'SCRIPT_NAME' => '/setup/system-requirements.php'],
            'post' => ['action' => 'repair_directory', '_token' => $csrf, 'target' => 'Storage writable', 'setup_token' => 'invalid'],
            'session' => ['system_requirements_csrf' => $csrf],
            'session_name' => 'erp_setup_requirements',
            'env' => ['SETUP_TOKEN' => $validToken],
        ]);
        $this->assertSame(403, $badTokenRes['status']);
        $this->assertStringContainsString('ต้องระบุ SETUP_TOKEN ที่ถูกต้อง', $badTokenRes['output']);

        // 3. Valid CSRF and valid SETUP_TOKEN with existing directory reports notice
        $okRes = $this->runScript($script, [
            'server' => ['REQUEST_METHOD' => 'POST', 'SCRIPT_NAME' => '/setup/system-requirements.php'],
            'post' => ['action' => 'repair_directory', '_token' => $csrf, 'target' => 'Storage writable', 'setup_token' => $validToken],
            'session' => ['system_requirements_csrf' => $csrf],
            'session_name' => 'erp_setup_requirements',
            'env' => ['SETUP_TOKEN' => $validToken],
        ]);
        $this->assertSame(200, $okRes['status']);
        $this->assertStringContainsString('โฟลเดอร์มีอยู่แล้ว', $okRes['output']);
    }

    public function test_xlsx_parsing_requires_zip_extension_or_fallback(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'empty_xlsx_');
        file_put_contents($tempFile, 'not_a_valid_zip_archive_file');

        try {
            $empService = app(EmployeeImportService::class);
            if (! class_exists(\ZipArchive::class)) {
                $this->expectException(RuntimeException::class);
                $this->expectExceptionMessage('PHP extension "zip" is required to parse XLSX files');
                $empService->parseXlsx($tempFile);
            } else {
                $rows = $empService->parseXlsx($tempFile);
                $this->assertIsArray($rows);
            }
        } finally {
            @unlink($tempFile);
        }
    }

    public function test_attendance_xlsx_parsing_requires_zip_extension_or_fallback(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'empty_att_xlsx_');
        file_put_contents($tempFile, 'not_a_valid_zip_archive_file');

        try {
            $attService = app(AttendanceReconciliationService::class);
            if (! class_exists(\ZipArchive::class)) {
                $this->expectException(RuntimeException::class);
                $this->expectExceptionMessage('PHP extension "zip" is required to parse XLSX files');
                $attService->parseXlsx($tempFile);
            } else {
                $rows = $attService->parseXlsx($tempFile);
                $this->assertIsArray($rows);
            }
        } finally {
            @unlink($tempFile);
        }
    }

    /**
     * @param array{
     *     server?: array<string, string>,
     *     get?: array<string, mixed>,
     *     post?: array<string, mixed>,
     *     session?: array<string, mixed>,
     *     session_name?: string,
     *     env?: array<string, string|null>
     * } $options
     * @return array{status: int, output: string, headers: array<int, string>, session: array<string, mixed>}
     */
    private function runScript(string $scriptPath, array $options = []): array
    {
        $resultFile = tempnam(sys_get_temp_dir(), 'setup_res_');
        $payload = json_encode([
            'script' => realpath($scriptPath) ?: $scriptPath,
            'server' => array_merge([
                'REQUEST_METHOD' => 'GET',
                'SCRIPT_NAME' => '/'.basename($scriptPath),
                'REMOTE_ADDR' => '127.0.0.1',
            ], $options['server'] ?? []),
            'get' => $options['get'] ?? [],
            'post' => $options['post'] ?? [],
            'session' => $options['session'] ?? [],
            'session_name' => $options['session_name'] ?? 'erp_setup',
            'env' => $options['env'] ?? [],
        ], JSON_THROW_ON_ERROR);

        $runnerPath = base_path('tests/Support/setup-script-runner.php');
        $process = new Process([PHP_BINARY, $runnerPath, $resultFile], base_path());
        $process->setInput($payload);
        $process->run();

        $rawResult = file_exists($resultFile) ? file_get_contents($resultFile) : null;
        @unlink($resultFile);

        if (! $rawResult) {
            throw new RuntimeException('Script runner failed (exit '.$process->getExitCode().'): '.$process->getErrorOutput().' '.$process->getOutput());
        }

        return json_decode($rawResult, true, flags: JSON_THROW_ON_ERROR);
    }
}
