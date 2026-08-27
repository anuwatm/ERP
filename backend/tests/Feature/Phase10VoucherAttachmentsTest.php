<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\StoredFile;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase10VoucherAttachmentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('local');
    }

    public function test_voucher_proof_can_upload_and_download_with_parent_permission(): void
    {
        $finance = User::factory()->create();
        $this->attachRole($finance, 'finance', ['vouchers.update', 'vouchers.view']);
        $voucher = $this->voucherFor($finance);

        $this->actingAsOrgUser($finance)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('vouchers.attachment.store', $voucher), [
                'attachment' => UploadedFile::fake()->create('pv-proof.pdf', 20, 'application/pdf'),
            ])->assertRedirect();

        $voucher->refresh();
        $file = StoredFile::findOrFail($voucher->attachment_file_id);
        $this->assertSame('voucher', $file->entity_type);
        $this->assertSame($voucher->id, $file->entity_id);
        $this->assertSame('voucher_proof', $file->category);
        $this->assertStringStartsWith('tenants/'.$finance->org_id.'/', $file->storage_key);
        $this->assertStringNotContainsString('pv-proof', $file->storage_key);
        Storage::disk('local')->assertExists($file->storage_key);
        $this->assertDatabaseHas('audit_logs', ['action' => 'voucher.attachment.upload', 'entity_id' => $voucher->id]);

        $this->actingAsOrgUser($finance)->get(route('files.download', $file))->assertOk();
        $this->assertTrue(AuditLog::where('action', 'file.download')->where('entity_id', $file->id)->exists());
    }

    public function test_voucher_proof_download_requires_voucher_view_permission(): void
    {
        $finance = User::factory()->create();
        $viewer = User::factory()->create(['org_id' => $finance->org_id]);
        $this->attachRole($finance, 'finance', ['vouchers.update', 'vouchers.view']);
        $this->attachRole($viewer, 'viewer', ['dashboard.view']);
        $voucher = $this->voucherFor($finance);
        $file = $this->upload($finance, $voucher);

        $this->actingAsOrgUser($viewer)->get(route('files.download', $file))->assertForbidden();
    }

    public function test_voucher_proof_is_org_scoped(): void
    {
        $finance = User::factory()->create();
        $other = User::factory()->create();
        $this->attachRole($finance, 'finance', ['vouchers.update', 'vouchers.view']);
        $this->attachRole($other, 'finance', ['vouchers.view']);
        $file = $this->upload($finance, $this->voucherFor($finance));

        $this->actingAsOrgUser($other)->get(route('files.download', $file))->assertForbidden();
    }

    public function test_voucher_proof_rejects_invalid_file_type(): void
    {
        $finance = User::factory()->create();
        $this->attachRole($finance, 'finance', ['vouchers.update']);

        $this->actingAsOrgUser($finance)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('vouchers.attachment.store', $this->voucherFor($finance)), [
                'attachment' => UploadedFile::fake()->create('proof.php', 1, 'text/x-php'),
            ])->assertSessionHasErrors('attachment');

        $this->assertSame(0, StoredFile::count());
    }

    private function upload(User $user, Voucher $voucher): StoredFile
    {
        $this->actingAsOrgUser($user)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('vouchers.attachment.store', $voucher), [
                'attachment' => UploadedFile::fake()->create('proof.png', 10, 'image/png'),
            ])->assertRedirect();

        return StoredFile::firstOrFail();
    }

    private function voucherFor(User $user): Voucher
    {
        return Voucher::create([
            'org_id' => $user->org_id,
            'voucher_no' => Str::upper(Str::random(8)),
            'type' => 'payment',
            'status' => 'issued',
            'voucher_date' => '2026-08-26',
            'amount' => '100.00',
            'created_by' => $user->id,
        ]);
    }

    private function attachRole(User $user, string $code, array $permissions): Role
    {
        $role = Role::create(['org_id' => $user->org_id, 'code' => $code, 'name' => Str::headline($code), 'is_system' => true]);
        foreach ($permissions as $permissionCode) {
            $parts = explode('.', $permissionCode);
            $permission = Permission::firstOrCreate(['code' => $permissionCode], ['module' => $parts[0], 'action' => $parts[count($parts) - 1]]);
            $role->permissions()->attach($permission->id);
        }
        $user->roles()->attach($role->id);

        return $role;
    }
}
