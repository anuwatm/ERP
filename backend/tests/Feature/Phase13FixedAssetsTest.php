<?php

namespace Tests\Feature;

use App\Models\AssetCategory;
use App\Models\ChartOfAccount;
use App\Models\Expense;
use App\Models\FixedAsset;
use App\Models\JournalEntry;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\ChartOfAccountProvisioner;
use App\Services\FixedAssetService;
use App\Support\FileAttachmentManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase13FixedAssetsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_expense_capitalization_depreciation_is_idempotent_and_disposal_posts_gl(): void
    {
        $user = User::factory()->create();
        $category = $this->category($user);
        $expense = Expense::create(['org_id' => $user->org_id, 'expense_no' => 'EXP-ASSET-001', 'category' => 'office', 'title' => 'Office equipment', 'amount' => 1000, 'tax_mode' => 'no_tax', 'tax_amount' => 0, 'expense_date' => '2026-08-01', 'status' => 'approved']);
        $service = app(FixedAssetService::class);

        $asset = $service->capitalize($user->org_id, $user->id, [
            'asset_category_id' => $category->id,
            'source_type' => 'expense',
            'source_id' => $expense->id,
            'name' => 'Laptop',
            'available_for_use_date' => '2026-08-01',
            'salvage_value' => 0,
            'useful_life_months' => 10,
        ]);

        $this->assertSame('active', $asset->status);
        $this->assertEquals(1000, $asset->net_book_value);
        $capitalization = JournalEntry::where('source_id', $asset->id)->where('posting_event', 'capitalized')->firstOrFail();
        $this->assertEquals(1000, $capitalization->lines()->whereHas('account', fn ($query) => $query->where('code', '1210'))->value('debit'));
        $this->assertEquals(1000, $capitalization->lines()->whereHas('account', fn ($query) => $query->where('code', '5200'))->value('credit'));

        $this->assertSame(1, $service->runThroughMonth($user->org_id, '2026-08-01', $user->id));
        $this->assertSame(0, $service->runThroughMonth($user->org_id, '2026-08-01', $user->id));
        $this->assertEquals(100, $asset->fresh()->accumulated_depreciation);
        $this->assertEquals(900, $asset->fresh()->net_book_value);
        $this->assertSame(1, JournalEntry::where('source_id', $asset->id)->where('posting_event', 'depreciation:2026-08')->count());

        $disposed = $service->dispose($asset, $user->id, ['status' => 'disposed', 'disposed_at' => '2026-09-10', 'disposal_proceeds' => 500, 'disposal_reason' => 'Sold']);
        $this->assertSame('disposed', $disposed->status);
        $disposal = JournalEntry::where('source_id', $asset->id)->where('posting_event', 'disposed')->firstOrFail();
        $this->assertEquals(1000, $disposal->lines()->whereHas('account', fn ($query) => $query->where('code', '1210'))->value('credit'));
        $this->assertEquals(400, $disposal->lines()->whereHas('account', fn ($query) => $query->where('code', '5300'))->value('debit'));
    }

    public function test_fixed_asset_page_is_permission_and_organization_scoped(): void
    {
        $user = User::factory()->create();
        $this->grant($user, ['fixed_assets.view']);
        $this->actingAsOrgUser($user)->get(route('fixed-assets.index'))->assertOk()->assertInertia(fn ($page) => $page->component('Finance/FixedAssets'));

        $other = User::factory()->create();
        $asset = FixedAsset::create(['org_id' => $other->org_id, 'asset_category_id' => $this->category($other)->id, 'asset_no' => 'FA-OUTSIDE', 'name' => 'Outside asset', 'capitalization_source_type' => 'expense', 'capitalization_source_id' => (string) Str::uuid(), 'acquisition_date' => '2026-08-01', 'available_for_use_date' => '2026-08-01', 'depreciation_start_date' => '2026-08-01', 'cost' => 100, 'salvage_value' => 0, 'useful_life_months' => 12, 'depreciation_method' => 'straight_line', 'accumulated_depreciation' => 0, 'net_book_value' => 100, 'status' => 'active']);
        $this->grant($user, ['fixed_assets.dispose']);
        $this->actingAsOrgUser($user)->withSession(['auth.password_confirmed_at' => time()])->post(route('fixed-assets.dispose', $asset), ['status' => 'written_off', 'disposed_at' => '2026-08-02', 'disposal_reason' => 'No access'])->assertNotFound();
    }

    public function test_dialog_category_and_capitalization_retry_preserves_optional_fields(): void
    {
        $user = User::factory()->create();
        $this->grant($user, ['fixed_assets.manage']);
        $this->category($user);
        $accounts = ChartOfAccount::where('org_id', $user->org_id)->get()->keyBy('code');
        $this->actingAsOrgUser($user)->withSession(['auth.password_confirmed_at' => time()]);
        $headers = ['X-Form-Submission' => (string) Str::uuid()];
        $payload = ['code' => 'RETRY', 'name' => 'Retry equipment', 'asset_account_id' => $accounts['1210']->id, 'accumulated_depreciation_account_id' => $accounts['1219']->id, 'depreciation_expense_account_id' => $accounts['5310']->id, 'default_useful_life_months' => '24'];
        $this->post(route('fixed-assets.categories.store'), array_merge($payload, ['name' => '']), $headers)->assertSessionHasErrors('name');
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $this->post(route('fixed-assets.categories.store'), $payload, $headers)->assertRedirect()->assertSessionHasNoErrors();
        }
        $this->assertSame(1, AssetCategory::where('code', 'RETRY')->count());
        $category = AssetCategory::where('code', 'RETRY')->firstOrFail();
        $expense = Expense::create(['org_id' => $user->org_id, 'expense_no' => 'EXP-DIALOG', 'category' => 'office', 'title' => 'Fixture equipment', 'amount' => 1000, 'tax_mode' => 'no_tax', 'tax_amount' => 0, 'expense_date' => '2026-09-13', 'status' => 'approved']);
        $headers = ['X-Form-Submission' => (string) Str::uuid()];
        $payload = ['asset_category_id' => $category->id, 'source_type' => 'expense', 'source_id' => $expense->id, 'name' => 'Fixture asset', 'available_for_use_date' => '2026-09-13', 'salvage_value' => '100.25', 'useful_life_months' => '', 'location' => 'Room 23', 'custodian_user_id' => $user->id];
        $this->post(route('fixed-assets.store'), array_merge($payload, ['source_id' => '']), $headers)->assertSessionHasErrors('source_id');
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $this->post(route('fixed-assets.store'), $payload, $headers)->assertRedirect()->assertSessionHasNoErrors();
        }
        $this->assertDatabaseCount('fixed_assets', 1);
        $asset = FixedAsset::firstOrFail();
        $this->assertSame('Room 23', $asset->location);
        $this->assertSame($user->id, $asset->custodian_user_id);
        $this->assertEquals(24, $asset->useful_life_months);
        $this->assertEquals('100.25', $asset->salvage_value);
        $this->assertSame(1, JournalEntry::where('source_id', $asset->id)->where('posting_event', 'capitalized')->count());
        $other = User::factory()->create();
        $this->actingAsOrgUser($other)->withSession(['auth.password_confirmed_at' => time()])->post(route('fixed-assets.store'), $payload, $headers)->assertForbidden();
    }

    public function test_asset_proof_upload_validation_replacement_and_scope(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $this->grant($user, ['fixed_assets.manage', 'fixed_assets.view']);
        $asset = FixedAsset::create(['org_id' => $user->org_id, 'asset_category_id' => $this->category($user)->id, 'asset_no' => 'FA-PROOF', 'name' => 'Proof fixture', 'capitalization_source_type' => 'expense', 'capitalization_source_id' => (string) Str::uuid(), 'acquisition_date' => '2026-09-13', 'available_for_use_date' => '2026-09-13', 'depreciation_start_date' => '2026-09-13', 'cost' => 100, 'salvage_value' => 0, 'useful_life_months' => 12, 'depreciation_method' => 'straight_line', 'accumulated_depreciation' => 0, 'net_book_value' => 100, 'status' => 'active']);
        $this->actingAsOrgUser($user)->withSession(['auth.password_confirmed_at' => time()]);
        $url = route('fixed-assets.attachment.store', $asset);
        $this->post($url, ['attachment' => UploadedFile::fake()->create('proof.pdf', 20, 'application/pdf')])->assertRedirect()->assertSessionHasNoErrors();
        $first = $asset->fresh()->attachment;
        $this->assertNotNull($first);
        Storage::disk('local')->assertExists($first->storage_key);
        $this->get(route('files.download', $first))->assertOk();
        $this->mock(FileAttachmentManager::class, function ($mock) {
            $mock->shouldReceive('store')->once()->andThrow(new \RuntimeException('Simulated upload failure'));
            $mock->shouldNotReceive('delete');
        });
        $this->post($url, ['attachment' => UploadedFile::fake()->create('failed.pdf', 20, 'application/pdf')])->assertStatus(500);
        $this->app->forgetInstance(FileAttachmentManager::class);
        $this->assertSame($first->id, $asset->fresh()->attachment_file_id);
        Storage::disk('local')->assertExists($first->storage_key);
        $this->post($url, ['attachment' => UploadedFile::fake()->create('bad.php', 1, 'text/x-php')])->assertSessionHasErrors('attachment');
        $this->post($url, ['attachment' => UploadedFile::fake()->create('large.pdf', 5121, 'application/pdf')])->assertSessionHasErrors('attachment');
        $this->assertSame($first->id, $asset->fresh()->attachment_file_id);
        Storage::disk('local')->assertExists($first->storage_key);
        $this->post($url, ['attachment' => UploadedFile::fake()->create('replacement.pdf', 20, 'application/pdf')])->assertRedirect()->assertSessionHasNoErrors();
        $next = $asset->fresh()->attachment;
        $this->assertNotSame($first->id, $next->id);
        $this->assertSoftDeleted('files', ['id' => $first->id]);
        Storage::disk('local')->assertMissing($first->storage_key);
        Storage::disk('local')->assertExists($next->storage_key);
        $this->get(route('files.download', $next))->assertOk();
        $other = User::factory()->create();
        $this->grant($other, ['fixed_assets.manage', 'fixed_assets.view']);
        $this->actingAsOrgUser($other)->withSession(['auth.password_confirmed_at' => time()]);
        $this->post($url, ['attachment' => UploadedFile::fake()->create('foreign.pdf', 20, 'application/pdf')])->assertNotFound();
        $this->get(route('files.download', $next))->assertForbidden();
    }

    private function category(User $user): AssetCategory
    {
        app(ChartOfAccountProvisioner::class)->ensure($user->org_id);
        $accounts = ChartOfAccount::where('org_id', $user->org_id)->get()->keyBy('code');

        return AssetCategory::create(['org_id' => $user->org_id, 'code' => 'EQUIP', 'name' => 'Equipment', 'asset_account_id' => $accounts['1210']->id, 'accumulated_depreciation_account_id' => $accounts['1219']->id, 'depreciation_expense_account_id' => $accounts['5310']->id, 'default_useful_life_months' => 12, 'status' => 'active']);
    }

    private function grant(User $user, array $codes): void
    {
        $role = Role::firstOrCreate(['org_id' => $user->org_id, 'code' => 'fixed_asset_finance'], ['name' => 'Fixed Asset Finance', 'is_system' => true]);
        foreach ($codes as $code) {
            $parts = explode('.', $code);
            $permission = Permission::firstOrCreate(['code' => $code], ['module' => $parts[0], 'action' => end($parts)]);
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }
        $user->roles()->syncWithoutDetaching([$role->id]);
    }
}
