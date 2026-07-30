<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class Phase3ProductsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_existing_roles_receive_products_manage_permission_from_migration(): void
    {
        $owner = User::factory()->create();
        $role = Role::create([
            'org_id' => $owner->org_id,
            'code' => 'finance',
            'name' => 'Finance',
            'is_system' => true,
        ]);
        $owner->roles()->attach($role->id);

        (require database_path('migrations/2026_07_27_000002_backfill_products_manage_permission.php'))->up();

        $this->actingAsOrgUser($owner)->get(route('products.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Finance/Products'));
    }

    public function test_finance_user_can_create_product_service_catalog_item(): void
    {
        $finance = User::factory()->create();
        $this->attachRole($finance, 'finance', ['products.manage']);

        $this->actingAsOrgUser($finance)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('products.store'), [
                'sku' => 'SVC-ERP-001',
                'name' => 'ERP Implementation',
                'type' => 'service',
                'category' => 'Implementation',
                'unit' => 'project',
                'price' => '150000.00',
                'cost' => '60000.00',
                'is_active' => true,
                'description' => 'Phase-based setup service',
            ])->assertRedirect();

        $product = Product::where('org_id', $finance->org_id)->where('sku', 'SVC-ERP-001')->firstOrFail();
        $this->assertSame('ERP Implementation', $product->name);
        $this->assertSame('service', $product->type);
        $this->assertFalse($product->track_inventory);
        $this->assertTrue(AuditLog::where('action', 'product.create')->where('entity_id', $product->id)->exists());
    }

    public function test_blank_sku_is_saved_as_null_and_can_be_reused(): void
    {
        $finance = User::factory()->create();
        $this->attachRole($finance, 'finance', ['products.manage']);

        foreach (['First Service', 'Second Service'] as $name) {
            $this->actingAsOrgUser($finance)->withSession(['auth.password_confirmed_at' => time()])
                ->post(route('products.store'), [
                    'sku' => '',
                    'name' => $name,
                    'type' => 'service',
                    'price' => '100.00',
                    'cost' => '',
                    'is_active' => true,
                ])->assertRedirect();
        }

        $this->assertSame(2, Product::where('org_id', $finance->org_id)->whereNull('sku')->count());
        $this->assertSame('0.00', Product::where('name', 'First Service')->firstOrFail()->cost);
    }

    public function test_products_are_listed_only_inside_current_organization(): void
    {
        $finance = User::factory()->create();
        $otherOrgUser = User::factory()->create();
        $this->attachRole($finance, 'finance', ['products.manage']);

        Product::create(['org_id' => $finance->org_id, 'sku' => 'OWN-001', 'name' => 'Owned Service', 'type' => 'service']);
        Product::create(['org_id' => $otherOrgUser->org_id, 'sku' => 'HID-001', 'name' => 'Hidden Service', 'type' => 'service']);

        $this->actingAsOrgUser($finance)->get(route('products.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Finance/Products')
                ->has('products', 1)
                ->where('products.0.name', 'Owned Service')
            );
    }

    public function test_sku_is_unique_per_organization(): void
    {
        $finance = User::factory()->create();
        $otherOrgUser = User::factory()->create();
        $this->attachRole($finance, 'finance', ['products.manage']);

        Product::create(['org_id' => $finance->org_id, 'sku' => 'DUP-001', 'name' => 'Existing Item', 'type' => 'service']);
        Product::create(['org_id' => $otherOrgUser->org_id, 'sku' => 'DUP-001', 'name' => 'Other Org Item', 'type' => 'service']);

        $this->actingAsOrgUser($finance)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('products.store'), [
                'sku' => 'DUP-001',
                'name' => 'Duplicate Item',
                'type' => 'service',
                'price' => '100.00',
                'cost' => '0.00',
                'is_active' => true,
            ])->assertSessionHasErrors('sku');
    }

    public function test_product_update_delete_and_cross_org_guard(): void
    {
        $finance = User::factory()->create();
        $otherOrgUser = User::factory()->create();
        $this->attachRole($finance, 'finance', ['products.manage']);
        $product = Product::create(['org_id' => $finance->org_id, 'sku' => 'SVC-001', 'name' => 'Old Name', 'type' => 'service']);
        $otherProduct = Product::create(['org_id' => $otherOrgUser->org_id, 'sku' => 'SVC-002', 'name' => 'Other Name', 'type' => 'service']);

        $this->actingAsOrgUser($finance)->withSession(['auth.password_confirmed_at' => time()])
            ->patch(route('products.update', $otherProduct), [
                'sku' => 'SVC-002',
                'name' => 'Blocked Update',
                'type' => 'service',
                'price' => '100.00',
                'cost' => '0.00',
                'is_active' => true,
            ])->assertForbidden();

        $this->actingAsOrgUser($finance)->withSession(['auth.password_confirmed_at' => time()])
            ->patch(route('products.update', $product), [
                'sku' => 'SVC-001',
                'name' => 'New Name',
                'type' => 'package',
                'category' => 'Bundle',
                'unit' => 'set',
                'price' => '2500.00',
                'cost' => '1000.00',
                'is_active' => false,
            ])->assertRedirect();

        $this->assertSame('New Name', $product->refresh()->name);
        $this->assertFalse($product->is_active);
        $this->assertTrue(AuditLog::where('action', 'product.update')->where('entity_id', $product->id)->exists());

        $this->actingAsOrgUser($finance)->withSession(['auth.password_confirmed_at' => time()])
            ->delete(route('products.destroy', $product))->assertRedirect();

        $this->assertSoftDeleted('products', ['id' => $product->id]);
        $this->assertTrue(AuditLog::where('action', 'product.delete')->where('entity_id', $product->id)->exists());
    }

    private function attachRole(User $user, string $code, array $permissions): Role
    {
        $role = Role::create([
            'org_id' => $user->org_id,
            'code' => $code,
            'name' => Str::headline($code),
            'is_system' => true,
        ]);

        foreach ($permissions as $permissionCode) {
            $parts = explode('.', $permissionCode);
            $permission = Permission::firstOrCreate(
                ['code' => $permissionCode],
                ['module' => $parts[0], 'action' => $parts[count($parts) - 1]]
            );
            $role->permissions()->attach($permission->id);
        }

        $user->roles()->attach($role->id);

        return $role;
    }
}
