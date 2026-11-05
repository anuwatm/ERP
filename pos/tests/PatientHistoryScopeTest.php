<?php

namespace Pos\Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Pos\Models\Staff;
use Pos\Services\PatientHistoryScope;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class PatientHistoryScopeTest extends TestCase
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
        $branch = (string) Str::uuid();
        DB::table('pos_branches')->insert(['id' => $branch, 'code' => 'PATIENT', 'name' => 'Synthetic Pharmacy', 'business_type' => 'pharmacy']);
        $this->staff = Staff::create(['id' => (string) Str::uuid(), 'branch_id' => $branch, 'name' => 'Synthetic pharmacist', 'email' => 'patient-scope@fixture.test', 'password' => 'synthetic-password', 'role' => 'pharmacist', 'active' => true]);
        config(['pos.instance_id' => (string) Str::uuid(), 'pos.local_pharmacy_enabled' => true]);
    }

    private function scope(): array
    {
        return DB::transaction(fn () => app(PatientHistoryScope::class)->lock($this->staff));
    }

    private function denied(int $status): void
    {
        try {
            $this->scope();
            $this->fail('Patient scope unexpectedly allowed.');
        } catch (HttpExceptionInterface $exception) {
            $this->assertSame($status, $exception->getStatusCode());
        }
    }

    public function test_default_off_and_pharmacy_switch_are_both_required(): void
    {
        $this->denied(503);
        config(['pos.local_patient_history_enabled' => true, 'pos.local_pharmacy_enabled' => false]);
        $this->denied(503);
        config(['pos.local_pharmacy_enabled' => true]);
        $this->assertSame($this->staff->branch_id, $this->scope()['branch']->id);
    }

    public function test_production_and_unprovisioned_instance_are_blocked(): void
    {
        config(['pos.local_patient_history_enabled' => true]);
        app()->instance('env', 'production');
        try {
            $this->denied(503);
        } finally {
            app()->instance('env', 'testing');
        }
        config(['pos.instance_id' => null]);
        $this->denied(503);
    }

    public function test_retail_restaurant_and_inactive_branch_are_blocked(): void
    {
        config(['pos.local_patient_history_enabled' => true]);
        foreach (['retail', 'restaurant'] as $type) {
            DB::table('pos_branches')->where('id', $this->staff->branch_id)->update(['business_type' => $type]);
            $this->denied(404);
        }
        DB::table('pos_branches')->where('id', $this->staff->branch_id)->update(['business_type' => 'pharmacy', 'active' => false]);
        $this->denied(404);
    }

    public function test_inactive_changed_branch_or_changed_role_session_is_blocked(): void
    {
        config(['pos.local_patient_history_enabled' => true]);
        DB::table('pos_staff')->where('id', $this->staff->id)->update(['active' => false]);
        $this->denied(403);
        $other = (string) Str::uuid();
        DB::table('pos_branches')->insert(['id' => $other, 'code' => 'OTHER', 'name' => 'Other synthetic pharmacy', 'business_type' => 'pharmacy']);
        DB::table('pos_staff')->where('id', $this->staff->id)->update(['active' => true, 'branch_id' => $other]);
        $this->denied(403);
        DB::table('pos_staff')->where('id', $this->staff->id)->update(['branch_id' => $this->staff->branch_id, 'role' => 'cashier']);
        $this->denied(403);
    }

    public function test_scope_returns_current_actor_and_branch_not_patient_data(): void
    {
        config(['pos.local_patient_history_enabled' => true]);
        // RefreshDatabase itself holds a transaction; the scope is not a patient-data API.
        $scope = $this->scope();
        $this->assertSame(['staff', 'branch'], array_keys($scope));
        $this->assertSame($this->staff->id, $scope['staff']->id);
        $this->assertSame('pharmacy', $scope['branch']->business_type);
    }
}
