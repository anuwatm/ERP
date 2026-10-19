<?php

declare(strict_types=1);

namespace Setup;

use App\Models\Organization;
use App\Services\OrganizationProvisioner;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request as LaravelRequest;
use PDO;
use RuntimeException;
use Throwable;

final class Installer
{
    private const LOCK_FILE = 'storage/app/installer/installed.json';

    private const INSTALL_LOCK = 'storage/app/installer/install.lock';

    private const ENABLE_FILE = 'setup/enable.php';

    public function __construct(private readonly string $basePath) {}

    public function isInstalled(): bool
    {
        return is_file($this->path(self::LOCK_FILE));
    }

    public function isEnabled(): bool
    {
        return $this->activationToken() !== null;
    }

    public function verifyToken(string $token): bool
    {
        $expected = $this->activationToken();

        return $expected !== null && $token !== '' && hash_equals($expected, $token);
    }

    /** @return array<int, array{label: string, ok: bool, detail: string}> */
    public function preflight(): array
    {
        $checks = [
            ['PHP 8.3 or newer', version_compare(PHP_VERSION, '8.3.0', '>='), PHP_VERSION],
            ['Composer dependencies (vendor)', is_file($this->path('vendor/autoload.php')), 'vendor/autoload.php'],
            ['PDO MySQL extension', extension_loaded('pdo_mysql'), extension_loaded('pdo_mysql') ? 'available' : 'missing'],
            ['OpenSSL extension', extension_loaded('openssl'), extension_loaded('openssl') ? 'available' : 'missing'],
            ['Mbstring extension', extension_loaded('mbstring'), extension_loaded('mbstring') ? 'available' : 'missing'],
            ['Fileinfo extension', extension_loaded('fileinfo'), extension_loaded('fileinfo') ? 'available' : 'missing'],
            ['DOM extension', extension_loaded('dom'), extension_loaded('dom') ? 'available' : 'missing'],
            ['SimpleXML extension', extension_loaded('simplexml'), extension_loaded('simplexml') ? 'available' : 'missing'],
            ['cURL extension', extension_loaded('curl'), extension_loaded('curl') ? 'available' : 'missing'],
            ['Zip extension', extension_loaded('zip'), extension_loaded('zip') ? 'available' : 'missing'],
            ['Storage writable', $this->writableDirectory('storage'), 'storage/'],
            ['Cache writable', $this->writableDirectory('bootstrap/cache'), 'bootstrap/cache/'],
            ['Environment writable', is_readable($this->path('.env.example')) && is_writable($this->basePath), '.env.example readable and application root writable'],
            ['Clean release directory', ! is_file($this->path('.env')), is_file($this->path('.env')) ? '.env already exists' : 'ready'],
            ['Frontend build present', is_dir($this->path('public/build')), 'public/build/'],
        ];

        return array_map(
            fn (array $check): array => ['label' => $check[0], 'ok' => $check[1], 'detail' => $check[2]],
            $checks
        );
    }

    /** @param array<string, string> $config */
    public function testDatabase(array $config): void
    {
        $pdo = $this->pdo($config);
        $pdo->query('SELECT 1');
    }

    /** @param array<string, string> $config @param array<string, string> $owner */
    public function install(array $config, array $owner, string $remoteAddress, string $userAgent): void
    {
        if ($this->isInstalled()) {
            throw new RuntimeException('This application has already been installed.');
        }

        $this->assertDatabaseEmpty($config);
        $lockPath = $this->path(self::INSTALL_LOCK);
        $this->ensureDirectory(dirname($lockPath));
        $lock = fopen($lockPath, 'c+');
        if ($lock === false || ! flock($lock, LOCK_EX | LOCK_NB)) {
            throw new RuntimeException('Another installation is already running.');
        }

        try {
            $this->writeEnvironment($config);
            $this->runMigrations();
            $this->provisionOwner($owner, $remoteAddress, $userAgent);
            $this->writeInstallLock($config['app_url']);
            @unlink($this->path(self::ENABLE_FILE));
        } catch (Throwable $exception) {
            $this->writeFailureLog($exception);
            throw new RuntimeException('Installation failed. Review storage/logs/setup.log on the server.');
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** @param array<string, string> $config */
    private function assertDatabaseEmpty(array $config): void
    {
        $pdo = $this->pdo($config);
        $statement = $pdo->query("SHOW TABLES LIKE 'migrations'");
        if ($statement !== false && $statement->fetchColumn() !== false) {
            throw new RuntimeException('The selected database already contains Laravel migrations. Refusing to overwrite it.');
        }
    }

    /** @param array<string, string> $config */
    private function pdo(array $config): PDO
    {
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $config['db_host'], $config['db_port'], $config['db_database']);

        return new PDO($dsn, $config['db_username'], $config['db_password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    /** @param array<string, string> $config */
    private function writeEnvironment(array $config): void
    {
        $example = $this->path('.env.example');
        $destination = $this->path('.env');
        if (! is_file($example)) {
            throw new RuntimeException('Missing .env.example.');
        }
        if (is_file($destination)) {
            throw new RuntimeException('An existing .env was found. The web installer only supports a new release directory.');
        }

        $values = [
            'APP_NAME' => $config['app_name'],
            'APP_ENV' => 'production',
            'APP_KEY' => 'base64:'.base64_encode(random_bytes(32)),
            'APP_DEBUG' => 'false',
            'APP_URL' => $config['app_url'],
            'APP_LOCALE' => 'th',
            'APP_FALLBACK_LOCALE' => 'en',
            'APP_FAKER_LOCALE' => 'th_TH',
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => $config['db_host'],
            'DB_PORT' => $config['db_port'],
            'DB_DATABASE' => $config['db_database'],
            'DB_USERNAME' => $config['db_username'],
            'DB_PASSWORD' => $config['db_password'],
            'SESSION_DRIVER' => 'database',
            'CACHE_STORE' => 'database',
            'QUEUE_CONNECTION' => 'database',
            'FILESYSTEM_DISK' => 'local',
        ];

        $content = (string) file_get_contents($example);
        foreach ($values as $key => $value) {
            $encoded = $this->envValue($value);
            $pattern = '/^'.preg_quote($key, '/').'=.*$/m';
            $line = $key.'='.$encoded;
            $content = preg_match($pattern, $content) === 1
                ? (string) preg_replace($pattern, $line, $content)
                : $content.PHP_EOL.$line;
        }

        $temporary = $destination.'.setup-'.bin2hex(random_bytes(6));
        if (file_put_contents($temporary, $content, LOCK_EX) === false || ! @rename($temporary, $destination)) {
            @unlink($temporary);
            throw new RuntimeException('Unable to write .env.');
        }
        @chmod($destination, 0600);
        @unlink($this->path('bootstrap/cache/config.php'));
        @unlink($this->path('bootstrap/cache/services.php'));
    }

    private function runMigrations(): void
    {
        require_once $this->path('vendor/autoload.php');
        $app = require $this->path('bootstrap/app.php');
        /** @var Kernel $kernel */
        $kernel = $app->make(Kernel::class);
        $exitCode = $kernel->call('migrate', ['--force' => true]);
        if ($exitCode !== 0) {
            throw new RuntimeException('Database migration failed.');
        }
    }

    /** @param array<string, string> $owner */
    private function provisionOwner(array $owner, string $remoteAddress, string $userAgent): void
    {
        /** @var OrganizationProvisioner $provisioner */
        $provisioner = app(OrganizationProvisioner::class);
        $request = LaravelRequest::create('/setup', 'POST', [], [], [], [
            'REMOTE_ADDR' => $remoteAddress,
            'HTTP_USER_AGENT' => $userAgent,
        ]);
        $user = $provisioner->registerOwner([
            'organization_name' => $owner['organization_name'],
            'name' => $owner['name'],
            'email' => $owner['email'],
            'password' => $owner['password'],
        ], $request);

        Organization::whereKey($user->org_id)->update([
            'legal_name' => $owner['legal_name'] ?: null,
            'tax_id' => $owner['tax_id'] ?: null,
            'timezone' => 'Asia/Bangkok',
            'currency' => 'THB',
        ]);
    }

    /** @param array<string, string> $config */
    private function writeInstallLock(string $appUrl): void
    {
        $path = $this->path(self::LOCK_FILE);
        $this->ensureDirectory(dirname($path));
        $data = json_encode([
            'installed_at' => gmdate('c'),
            'app_url' => $appUrl,
            'installer_version' => '1.0.0',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($data === false || file_put_contents($path, $data, LOCK_EX) === false) {
            throw new RuntimeException('Unable to persist the installation lock.');
        }
    }

    private function activationToken(): ?string
    {
        $environmentToken = getenv('SETUP_TOKEN');
        if (is_string($environmentToken) && strlen($environmentToken) >= 32) {
            return $environmentToken;
        }

        $file = $this->path(self::ENABLE_FILE);
        if (! is_file($file)) {
            return null;
        }
        $token = require $file;

        return is_string($token) && strlen($token) >= 32 ? $token : null;
    }

    private function writableDirectory(string $relativePath): bool
    {
        $path = $this->path($relativePath);

        return is_dir($path) && is_writable($path);
    }

    private function ensureDirectory(string $path): void
    {
        if (! is_dir($path) && ! mkdir($path, 0750, true) && ! is_dir($path)) {
            throw new RuntimeException('Unable to create installer storage directory.');
        }
    }

    private function envValue(string $value): string
    {
        if ($value === '' || preg_match('/[\s#"\\\\]/', $value) === 1) {
            return '"'.addcslashes($value, '\\"').'"';
        }

        return $value;
    }

    private function writeFailureLog(Throwable $exception): void
    {
        $path = $this->path('storage/logs/setup.log');
        $this->ensureDirectory(dirname($path));
        error_log(sprintf("[%s] %s\n", gmdate('c'), $exception->getMessage()), 3, $path);
    }

    private function path(string $relativePath): string
    {
        return $this->basePath.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    }
}
