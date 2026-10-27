<?php

namespace Pos\Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Pos\Models\Staff;
use Pos\Services\PrescriptionScanner;
use Pos\Services\PrescriptionService;

class PrescriptionTest extends TestCase
{
    use RefreshDatabase;

    private Staff $staff;

    public function createApplication(): Application
    {
        $app = require dirname(__DIR__).'/bootstrap/app.php';
        $this->traitsUsedByTest = class_uses_recursive(static::class);
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('prescription_fixtures');
        $this->staff = $this->pharmacist();
        config(['pos.synthetic_prescriptions_enabled' => true, 'pos.prescription_retention_days' => 30, 'pos.synthetic_prescription_hashes' => [hash('sha256', $this->content())]]);
    }

    public function test_upload_stays_private_until_scan_and_download_is_audited(): void
    {
        $this->actingAs($this->staff)->withSession(['pos_login_at' => time(), 'pos_last_seen' => time()]);
        $response = $this->post('/local-fixtures/prescriptions', ['file' => $this->file()], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('status', 'quarantined');
        $id = $response->json('id');
        $this->get('/local-fixtures/prescriptions/'.$id)->assertForbidden();
        $this->cleanScanner();
        $this->assertSame('clean', app(PrescriptionService::class)->scan($id));
        $response = $this->get('/local-fixtures/prescriptions/'.$id)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertSame($this->content(), $response->streamedContent());
        $this->assertDatabaseHas('pos_audit', ['action' => 'prescription.synthetic_download', 'source_id' => $id]);
        $this->assertFalse(config('filesystems.disks.prescription_fixtures.serve'));
    }

    public function test_cross_branch_cashier_and_expired_access_are_denied(): void
    {
        $result = app(PrescriptionService::class)->upload($this->staff, $this->file());
        $this->cleanScanner();
        app(PrescriptionService::class)->scan($result['id']);
        $other = $this->pharmacist();
        $this->actingAs($other)->withSession(['pos_login_at' => time(), 'pos_last_seen' => time()])->get('/local-fixtures/prescriptions/'.$result['id'])->assertNotFound();
        $this->staff->update(['role' => 'cashier']);
        $this->actingAs($this->staff)->get('/local-fixtures/prescriptions/'.$result['id'])->assertForbidden();
        $this->staff->update(['role' => 'pharmacist']);
        DB::table('pos_prescriptions')->update(['retained_until' => now()->subDay()]);
        $this->get('/local-fixtures/prescriptions/'.$result['id'])->assertForbidden();
    }

    public function test_unconfigured_scanner_never_marks_fixture_clean(): void
    {
        $result = app(PrescriptionService::class)->upload($this->staff, $this->file());
        try {
            app(PrescriptionService::class)->scan($result['id']);
            $this->fail('Missing scanner must fail.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('not provisioned', $e->getMessage());
        }
        $this->assertDatabaseHas('pos_prescriptions', ['id' => $result['id'], 'status' => 'quarantined']);
    }

    public function test_unknown_content_and_production_access_are_blocked(): void
    {
        $this->actingAs($this->staff)->withSession(['pos_login_at' => time(), 'pos_last_seen' => time()]);
        $this->post('/local-fixtures/prescriptions', ['file' => UploadedFile::fake()->createWithContent('unknown.pdf', "%PDF-1.4\nNot an allowlisted fixture")], ['Accept' => 'application/json'])->assertUnprocessable();
        $this->assertDatabaseCount('pos_prescriptions', 0);
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.1'])->get('/local-fixtures/prescriptions/'.Str::uuid())->assertForbidden();
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1']);
        $this->app['env'] = 'production';
        $this->get('/local-fixtures/prescriptions/'.Str::uuid())->assertStatus(503);
    }

    public function test_tampered_clean_fixture_cannot_be_downloaded(): void
    {
        $result = app(PrescriptionService::class)->upload($this->staff, $this->file());
        $this->cleanScanner();
        app(PrescriptionService::class)->scan($result['id']);
        $path = DB::table('pos_prescriptions')->value('private_path');
        Storage::disk('prescription_fixtures')->put($path, 'changed');
        $this->actingAs($this->staff)->withSession(['pos_login_at' => time(), 'pos_last_seen' => time()])->get('/local-fixtures/prescriptions/'.$result['id'])->assertConflict();
    }

    private function cleanScanner(): void
    {
        $scanner = \Mockery::mock(PrescriptionScanner::class);
        $scanner->shouldReceive('isClean')->once()->andReturn(true);
        $this->app->instance(PrescriptionScanner::class, $scanner);
    }

    private function pharmacist(): Staff
    {
        $branch = (string) Str::uuid();
        DB::table('pos_branches')->insert(['id' => $branch, 'code' => Str::random(8), 'name' => 'Synthetic pharmacy', 'business_type' => 'pharmacy']);

        return Staff::create(['id' => (string) Str::uuid(), 'branch_id' => $branch, 'name' => 'Synthetic pharmacist', 'email' => Str::random(8).'@synthetic.test', 'password' => 'fixture-password-only', 'role' => 'pharmacist', 'active' => true]);
    }

    private function content(): string
    {
        return "%PDF-1.4\n% SYNTHETIC TEST DATA ONLY - NO PATIENT INFORMATION\n%%EOF";
    }

    private function file(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('synthetic.pdf', $this->content());
    }
}
