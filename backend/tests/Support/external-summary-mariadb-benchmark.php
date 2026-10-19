<?php

// Manual opt-in benchmark. Creates/drops ONLY its own randomly named local schema.
require __DIR__.'/../../vendor/autoload.php';

use App\Models\Customer;
use App\Models\User;
use App\Services\ExternalSummaryService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

if (! in_array('--run-isolated', $argv, true)) {
    exit("Usage: php tests/Support/external-summary-mariadb-benchmark.php --run-isolated\n");
}
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$originalDefault = config('database.default');
$connection = DB::connection('mysql');
$settings = $connection->getConfig();
if (! in_array($settings['host'], ['localhost', '127.0.0.1', '::1'], true)) {
    throw new RuntimeException('Benchmark requires a local database host.');
}
$name = 'erp_api_bench_'.bin2hex(random_bytes(8));
if (! preg_match('/\Aerp_api_bench_[a-f0-9]{16}\z/', $name) || $name === $settings['database']) {
    throw new RuntimeException('Unsafe benchmark schema name.');
}
$admin = $connection->getPdo();
$originalCounts = [];
foreach (['organizations', 'users', 'migrations', 'projects', 'tasks', 'invoices', 'payments', 'deals'] as $table) {
    $originalCounts[$table] = $connection->table($table)->count();
}
$connection->beforeExecuting(function (string $sql): void {
    if (! preg_match('/\A\s*(SELECT|SHOW|EXPLAIN)\b/i', $sql)) {
        throw new RuntimeException('Writes to original database are prohibited by benchmark guard.');
    }
});
$created = false;
$failure = null;
$report = ['engine' => $connection->selectOne('SELECT VERSION() AS version')->version,
    'kind' => 'isolated synthetic service benchmark; excludes HTTP/auth and concurrent load',
    'per_org' => ['deals' => 10000, 'invoices' => 10000, 'payments' => 10000, 'projects' => 2000, 'tasks' => 10000],
    'organizations' => 2, 'summaries' => []];

try {
    $admin->exec("CREATE DATABASE `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $created = true;
    config(['database.connections.summary_benchmark' => array_merge($settings, ['name' => 'summary_benchmark', 'database' => $name, 'url' => null, 'read' => null, 'write' => null]),
        'database.default' => 'summary_benchmark', 'cache.default' => 'array', 'queue.default' => 'sync']);
    Schema::clearResolvedInstance('db.schema');
    $app->forgetInstance('db.schema');
    $isolated = DB::connection('summary_benchmark');
    if ($isolated->getName() !== 'summary_benchmark' || $isolated->selectOne('SELECT DATABASE() AS db')->db !== $name
        || Schema::getFacadeRoot()->getConnection()->getName() !== 'summary_benchmark') {
        throw new RuntimeException('Isolated connection verification failed before migration.');
    }
    $isolated->beforeExecuting(function () use ($isolated, $name): void {
        if ($isolated->getPdo()->query('SELECT DATABASE()')->fetchColumn() !== $name) {
            throw new RuntimeException('Benchmark connection left the isolated schema.');
        }
    });
    if (Artisan::call('migrate', ['--force' => true, '--database' => 'summary_benchmark']) !== 0) {
        throw new RuntimeException('Isolated migration failed.');
    }
    $users = [User::factory()->create(), User::factory()->create()];
    foreach ($users as $user) {
        $customer = Customer::create(['org_id' => $user->org_id, 'customer_code' => '000001', 'company_name' => 'Synthetic benchmark customer']);
        $invoiceIds = [];
        $projectIds = [];
        foreach (['projects' => 2000, 'deals' => 10000, 'invoices' => 10000, 'payments' => 10000, 'tasks' => 10000] as $table => $size) {
            $batch = [];
            for ($i = 0; $i < $size; $i++) {
                $id = (string) Str::orderedUuid();
                $row = ['id' => $id, 'org_id' => $user->org_id, 'created_at' => '2026-10-10 00:00:00', 'updated_at' => '2026-10-10 00:00:00'];
                $row += match ($table) {
                    'projects' => ['customer_id' => $customer->id, 'project_code' => sprintf('%06d', $i + 1), 'name' => 'Synthetic project', 'status' => 'active', 'progress_percent' => $i % 2 ? 25 : 75, 'due_date' => '2026-10-17'],
                    'deals' => ['customer_id' => $customer->id, 'title' => 'Synthetic deal', 'stage' => 'new', 'value_amount' => '1.25', 'currency' => 'THB', 'probability' => 0],
                    'invoices' => ['customer_id' => $customer->id, 'invoice_no' => 'BENCH-'.$i, 'status' => 'sent', 'issue_date' => '2026-10-01', 'due_date' => '2026-10-17', 'total' => '0.30', 'base_total' => '0.30', 'balance_due' => '0.20', 'base_balance_due' => '0.20', 'currency' => 'THB', 'base_currency' => 'THB'],
                    'payments' => ['invoice_id' => $invoiceIds[$i], 'entry_type' => 'receipt', 'amount' => '0.10', 'base_amount' => '0.10', 'invoice_base_amount' => '0.10', 'exchange_rate' => '1.00', 'currency' => 'THB', 'base_currency' => 'THB', 'payment_date' => '2026-10-01', 'payment_method' => 'cash', 'idempotency_key' => $id],
                    'tasks' => ['project_id' => $projectIds[$i % 2000], 'title' => 'Synthetic task', 'status' => $i % 2 ? 'blocked' : 'todo', 'priority' => 'normal', 'due_date' => '2026-10-17'],
                };
                if ($table === 'invoices') {
                    $invoiceIds[] = $id;
                } elseif ($table === 'projects') {
                    $projectIds[] = $id;
                }
                $batch[] = $row;
                if (count($batch) === 500 || $i === $size - 1) {
                    DB::table($table)->insert($batch);
                    $batch = [];
                }
            }
        }
    }
    $service = app(ExternalSummaryService::class);
    $now = CarbonImmutable::parse('2026-10-18 12:00:00', 'Asia/Bangkok');
    foreach (['sales', 'finance', 'projects'] as $domain) {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $result = $service->$domain($users[0]->org_id, '2026-10-01', '2026-10-31', 'Asia/Bangkok', $now);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        $valid = match ($domain) {
            'sales' => $result['deals_created_by_current_status'][0]['count'] === 10000 && $result['deals_created_by_current_status'][0]['amount'] === '12500.00',
            'finance' => $result['period']['invoiced_revenue'][0]['amount'] === '3000.00' && $result['period']['cash_received'][0]['net'] === '1000.00' && $result['snapshot']['outstanding_ar'][0]['amount'] === '2000.00',
            'projects' => $result['snapshot']['active_projects'] === 2000 && $result['snapshot']['overdue_tasks'] === 5000 && $result['snapshot']['average_active_project_progress_percent'] === '50.0',
        };
        if (! $valid) {
            throw new RuntimeException('Synthetic summary correctness check failed for '.$domain);
        }
        $plans = [];
        foreach ($queries as $query) {
            $plans[] = ['sql' => $query['query'], 'plan' => DB::select('EXPLAIN '.$query['query'], $query['bindings'])];
        }
        $samples = [];
        for ($i = 0; $i < 5; $i++) {
            $start = hrtime(true);
            $service->$domain($users[0]->org_id, '2026-10-01', '2026-10-31', 'Asia/Bangkok', $now);
            $samples[] = round((hrtime(true) - $start) / 1e6, 2);
        }
        sort($samples);
        $report['summaries'][$domain] = ['correctness' => 'passed', 'query_count' => count($queries), 'median_ms' => $samples[2], 'max_ms' => max($samples), 'samples_ms' => $samples, 'explain' => $plans];
    }
} catch (Throwable $exception) {
    $failure = $exception;
    $report['error'] = ['class' => $exception::class, 'code' => (string) $exception->getCode()];
} finally {
    DB::disconnect('summary_benchmark');
    config(['database.default' => $originalDefault]);
    if ($created) {
        // Name is generated here, guarded above, and CREATE succeeded in this run.
        $admin->exec("DROP DATABASE `$name`");
        $report['isolated_schema_removed'] = true;
    }
    $afterCounts = [];
    foreach (array_keys($originalCounts) as $table) {
        $afterCounts[$table] = $connection->table($table)->count();
    }
    $report['original_table_counts_unchanged'] = $originalCounts === $afterCounts;
}
echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
exit($failure ? 1 : 0);
