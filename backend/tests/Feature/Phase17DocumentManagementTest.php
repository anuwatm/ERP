<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentVersion;
use App\Models\Permission;
use App\Models\Role;
use App\Models\StoredFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase17DocumentManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('local');
    }

    public function test_document_upload_versions_and_private_download_work(): void
    {
        $user = User::factory()->create();
        $this->grant($user);
        $session = ['auth.password_confirmed_at' => time()];

        $this->actingAsOrgUser($user)->withSession($session)->post(route('documents.store'), ['title' => 'Supplier agreement', 'sensitivity' => 'org_internal', 'file' => UploadedFile::fake()->create('agreement.pdf', 12, 'application/pdf')])->assertRedirect();
        $document = Document::firstOrFail();
        $this->assertSame('clean', $document->currentVersion->scan_status);
        $this->actingAsOrgUser($user)->get(route('documents.versions.download', $document->currentVersion))->assertOk()->assertDownload('agreement.pdf');

        $this->actingAsOrgUser($user)->withSession($session)->post(route('documents.versions.store', $document), ['file' => UploadedFile::fake()->create('agreement-v2.pdf', 12, 'application/pdf'), 'change_note' => 'Renewal update'])->assertRedirect();
        $this->assertSame(2, DocumentVersion::where('document_id', $document->id)->count());
        $this->assertSame(2, $document->fresh()->currentVersion->version_no);
    }

    public function test_pending_scan_and_cross_org_download_are_blocked(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $this->grant($owner);
        $this->grant($other);
        $document = Document::create(['org_id' => $owner->org_id, 'owner_user_id' => $owner->id, 'document_no' => 'DOC-1', 'title' => 'Restricted', 'sensitivity' => 'org_internal', 'status' => 'active']);
        $version = DocumentVersion::create(['org_id' => $owner->org_id, 'document_id' => $document->id, 'version_no' => 1, 'storage_key' => 'missing.pdf', 'original_name' => 'missing.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 1, 'checksum_sha256' => str_repeat('a', 64), 'scan_status' => 'pending_scan']);
        $document->update(['current_version_id' => $version->id]);

        $this->actingAsOrgUser($owner)->get(route('documents.versions.download', $version))->assertNotFound();
        $this->actingAsOrgUser($other)->get(route('documents.versions.download', $version))->assertNotFound();
    }

    public function test_finance_confidential_document_requires_finance_role_and_expiry_notifies_owner(): void
    {
        $owner = User::factory()->create();
        $reader = User::factory()->create(['org_id' => $owner->org_id, 'branch_id' => $owner->branch_id, 'division_id' => $owner->division_id, 'department_id' => $owner->department_id]);
        $this->grant($owner);
        $this->grant($reader);
        $category = DocumentCategory::create(['org_id' => $owner->org_id, 'code' => 'contract', 'name' => 'Contract', 'default_sensitivity' => 'finance_confidential', 'expiry_tracking_enabled' => true, 'status' => true]);
        $document = Document::create(['org_id' => $owner->org_id, 'owner_user_id' => $owner->id, 'category_id' => $category->id, 'document_no' => 'DOC-2', 'title' => 'Finance contract', 'sensitivity' => 'finance_confidential', 'status' => 'active', 'expires_at' => now()->addDay(), 'renewal_alert_days' => 3]);
        Storage::disk('local')->put('docs/finance.pdf', 'x');
        $version = DocumentVersion::create(['org_id' => $owner->org_id, 'document_id' => $document->id, 'version_no' => 1, 'storage_key' => 'docs/finance.pdf', 'original_name' => 'finance.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 1, 'checksum_sha256' => str_repeat('b', 64), 'scan_status' => 'clean']);
        $document->update(['current_version_id' => $version->id]);
        $this->actingAsOrgUser($reader)->get(route('documents.versions.download', $version))->assertForbidden();
        Artisan::call('documents:check-expiry');
        $this->assertDatabaseHas('in_app_notifications', ['user_id' => $owner->id, 'type' => 'document.expiry']);
    }

    public function test_legacy_backfill_is_idempotent_and_preserves_storage_key(): void
    {
        $user = User::factory()->create();
        Storage::disk('local')->put('tenants/test/legacy.pdf', 'legacy');
        $file = StoredFile::create(['org_id' => $user->org_id, 'storage_key' => 'tenants/test/legacy.pdf', 'file_name' => 'legacy.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 6, 'category' => 'receipt', 'entity_type' => 'expense', 'entity_id' => (string) Str::uuid(), 'uploaded_by' => $user->id]);
        Artisan::call('documents:backfill-legacy');
        Artisan::call('documents:backfill-legacy');
        $document = Document::where('document_no', 'LEGACY-'.$file->id)->firstOrFail();
        $this->assertSame('tenants/test/legacy.pdf', $document->currentVersion->storage_key);
        $this->assertSame(1, DocumentVersion::where('document_id', $document->id)->count());
        $this->assertSame(1, $document->links()->count());
    }

    private function grant(User $user): void
    {
        $role = Role::firstOrCreate(['org_id' => $user->org_id, 'code' => 'document_manager'], ['name' => 'Document manager', 'is_system' => true]);
        foreach (['documents.view', 'documents.manage', 'documents.download'] as $code) {
            $permission = Permission::firstOrCreate(['code' => $code], ['module' => 'documents', 'action' => 'manage']);
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }
        $user->roles()->syncWithoutDetaching([$role->id]);
    }
}
