<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentLink;
use App\Models\DocumentVersion;
use App\Models\Permission;
use App\Models\RetentionPolicy;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Phase181DocumentComplianceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('local');
    }

    public function test_document_link_and_download_require_parent_visibility(): void
    {
        $owner = User::factory()->create();
        $restricted = User::factory()->create([
            'org_id' => $owner->org_id,
            'branch_id' => $owner->branch_id,
            'division_id' => $owner->division_id,
            'department_id' => $owner->department_id,
        ]);
        $this->grant($owner, ['documents.manage', 'documents.download', 'customers.view']);
        $this->grant($restricted, ['documents.manage', 'documents.download', 'customers.view']);
        $customer = Customer::create(['org_id' => $owner->org_id, 'customer_code' => '000001', 'company_name' => 'Restricted customer', 'owner_id' => $owner->id, 'created_by' => $owner->id]);
        $document = $this->document($owner);

        $this->actingAsOrgUser($restricted)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('documents.links.store', $document), ['linkable_type' => 'customer', 'linkable_id' => $customer->id, 'role' => 'supporting'])
            ->assertForbidden();

        DocumentLink::create(['org_id' => $owner->org_id, 'document_id' => $document->id, 'linkable_type' => 'customer', 'linkable_id' => $customer->id, 'role' => 'supporting', 'linked_by' => $owner->id]);

        $this->actingAsOrgUser($restricted)->get(route('documents.versions.download', $document->currentVersion))->assertForbidden();
        $this->actingAsOrgUser($owner)->get(route('documents.versions.download', $document->currentVersion))->assertOk();
    }

    public function test_category_policy_calculates_retention_and_applies_legal_hold(): void
    {
        $user = User::factory()->create();
        $this->grant($user, ['documents.manage', 'documents.download']);
        $policy = RetentionPolicy::create(['org_id' => $user->org_id, 'code' => 'tax', 'name' => 'Tax', 'minimum_retention_days' => 1825, 'effective_from' => now()->toDateString(), 'legal_hold_required' => true]);
        $category = DocumentCategory::create(['org_id' => $user->org_id, 'retention_policy_id' => $policy->id, 'code' => 'vat', 'name' => 'VAT', 'default_sensitivity' => 'finance_confidential', 'expiry_tracking_enabled' => true, 'default_renewal_alert_days' => 30, 'status' => true]);

        $this->actingAsOrgUser($user)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('documents.store'), ['title' => 'VAT invoice', 'category_id' => $category->id, 'sensitivity' => 'org_internal', 'expires_at' => now()->toDateString(), 'file' => UploadedFile::fake()->create('vat.pdf', 10, 'application/pdf')])
            ->assertRedirect();

        $document = Document::firstOrFail();
        $this->assertTrue($document->legal_hold);
        $this->assertSame(now()->addDays(1825)->toDateString(), $document->retention_until->toDateString());
        $this->assertSame(30, $document->renewal_alert_days);
    }

    public function test_retention_command_archives_purges_and_respects_legal_hold(): void
    {
        $user = User::factory()->create();
        $archivable = Document::create(['org_id' => $user->org_id, 'owner_user_id' => $user->id, 'document_no' => 'DOC-ARCHIVE', 'title' => 'Archive me', 'sensitivity' => 'org_internal', 'status' => 'active', 'retention_until' => now()->subDay()]);
        $held = Document::create(['org_id' => $user->org_id, 'owner_user_id' => $user->id, 'document_no' => 'DOC-HOLD', 'title' => 'Held', 'sensitivity' => 'org_internal', 'status' => 'active', 'legal_hold' => true, 'retention_until' => now()->subDay()]);

        Artisan::call('documents:enforce-retention');
        $this->assertSame('archived', $archivable->fresh()->status);
        $this->assertSame('active', $held->fresh()->status);

        Artisan::call('documents:enforce-retention', ['--purge' => true]);
        $this->assertDatabaseMissing('documents', ['id' => $archivable->id]);
        $this->assertDatabaseHas('documents', ['id' => $held->id, 'status' => 'active']);
    }

    public function test_retention_command_quarantines_failed_current_scan(): void
    {
        $user = User::factory()->create();
        $document = Document::create(['org_id' => $user->org_id, 'owner_user_id' => $user->id, 'document_no' => 'DOC-SCAN', 'title' => 'Failed scan', 'sensitivity' => 'org_internal', 'status' => 'active']);
        $version = DocumentVersion::create(['org_id' => $user->org_id, 'document_id' => $document->id, 'version_no' => 1, 'storage_key' => 'documents/failed.pdf', 'original_name' => 'failed.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 1, 'checksum_sha256' => str_repeat('f', 64), 'scan_status' => 'failed', 'uploaded_by' => $user->id]);
        $document->update(['current_version_id' => $version->id]);

        Artisan::call('documents:enforce-retention');

        $this->assertSame('quarantined', $document->fresh()->status);
    }

    private function document(User $user): Document
    {
        $document = Document::create(['org_id' => $user->org_id, 'owner_user_id' => $user->id, 'document_no' => 'DOC-ACCESS', 'title' => 'Restricted customer file', 'sensitivity' => 'org_internal', 'status' => 'active']);
        Storage::disk('local')->put('documents/access.pdf', 'test');
        $version = DocumentVersion::create(['org_id' => $user->org_id, 'document_id' => $document->id, 'version_no' => 1, 'storage_key' => 'documents/access.pdf', 'original_name' => 'access.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 4, 'checksum_sha256' => str_repeat('a', 64), 'scan_status' => 'clean', 'uploaded_by' => $user->id]);
        $document->update(['current_version_id' => $version->id]);

        return $document->fresh(['currentVersion']);
    }

    /** @param array<int, string> $permissions */
    private function grant(User $user, array $permissions): void
    {
        $role = Role::firstOrCreate(['org_id' => $user->org_id, 'code' => 'document_compliance'], ['name' => 'Document compliance', 'is_system' => true]);
        foreach ($permissions as $code) {
            $permission = Permission::firstOrCreate(['code' => $code], ['module' => 'documents', 'action' => 'view']);
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }
        $user->roles()->syncWithoutDetaching([$role->id]);
    }
}
