<?php

namespace Tests\Feature;

use App\Models\BillOfMaterials;
use App\Models\BomItem;
use App\Models\Customer;
use App\Models\Document;
use App\Models\DocumentLink;
use App\Models\DocumentVersion;
use App\Models\IntegrationApiKey;
use App\Models\Permission;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use App\Models\WorkflowApproval;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowDelegation;
use App\Models\WorkflowInstance;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExternalRestrictedReadApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Cache::flush();
        $this->travelTo(now()->setDate(2026, 10, 19)->setTime(5, 0));
    }

    private function issuer(): User
    {
        $user = User::factory()->create();
        $role = Role::create(['org_id' => $user->org_id, 'code' => 'admin', 'name' => 'Admin']);
        foreach (['api_keys.view', 'api_keys.manage', 'documents.view', 'documents.download', 'customers.view', 'workflows.approve',
            'manufacturing.bom.view', 'manufacturing.bom.manage', 'manufacturing.work_orders.view', 'manufacturing.work_orders.manage'] as $code) {
            $permission = Permission::firstOrCreate(['code' => $code], ['module' => 'fixture', 'action' => 'view']);
            $role->permissions()->attach($permission);
        }
        $user->roles()->attach($role);

        return $user;
    }

    private function credential(User $user, array $scopes, array $policy = []): string
    {
        $prefix = 'erp_'.bin2hex(random_bytes(12));
        $secret = $prefix.'.'.bin2hex(random_bytes(32));
        IntegrationApiKey::create(['org_id' => $user->org_id, 'created_by' => $user->id, 'name' => 'Restricted fixture',
            'prefix' => $prefix, 'secret_hash' => hash('sha256', $secret), 'scopes' => $scopes, 'access_policy' => $policy, 'expires_at' => now()->addDays(30)]);

        return $secret;
    }

    private function document(User $user, string $number, string $sensitivity = 'org_internal', string $scan = 'clean'): Document
    {
        $document = Document::create(['org_id' => $user->org_id, 'document_no' => $number, 'title' => 'Selected internal document',
            'owner_user_id' => $user->id, 'sensitivity' => $sensitivity, 'status' => 'active']);
        $version = DocumentVersion::create(['org_id' => $user->org_id, 'document_id' => $document->id, 'version_no' => 1,
            'storage_key' => 'private/'.$number.'.txt', 'original_name' => $number.'.txt', 'mime_type' => 'text/plain',
            'size_bytes' => 12, 'checksum_sha256' => str_repeat('a', 64), 'scan_status' => $scan]);
        Storage::disk('local')->put($version->storage_key, 'safe fixture');
        $document->update(['current_version_id' => $version->id]);

        return $document;
    }

    public function test_document_allowlist_confidentiality_parent_acl_and_private_download(): void
    {
        Storage::fake('local');
        $user = $this->issuer();
        $allowed = $this->document($user, 'ALLOWED');
        $notSelected = $this->document($user, 'NOT-SELECTED');
        $confidential = $this->document($user, 'HR', 'hr_confidential');
        $pending = $this->document($user, 'PENDING', 'org_internal', 'pending_scan');
        $badParent = $this->document($user, 'BAD-PARENT');
        $foreign = User::factory()->create();
        $foreignCustomer = Customer::create(['org_id' => $foreign->org_id, 'customer_code' => 'OUT', 'company_name' => 'Foreign']);
        DocumentLink::create(['org_id' => $user->org_id, 'document_id' => $badParent->id, 'linkable_type' => 'customer', 'linkable_id' => $foreignCustomer->id, 'role' => 'supporting']);
        $token = $this->credential($user, ['documents:read', 'documents:download'], ['document_ids' => [$allowed->id, $confidential->id, $pending->id, $badParent->id]]);
        $this->withToken($token)->getJson('/api/v1/documents')->assertOk()->assertJsonCount(2, 'data')->assertJsonMissingPath('data.0.current_version_id')
            ->assertJsonMissingPath('data.0.current_version.storage_key');
        $this->getJson('/api/v1/documents/'.$notSelected->id)->assertNotFound();
        $this->getJson('/api/v1/documents/'.$confidential->id.'/download')->assertNotFound();
        $this->getJson('/api/v1/documents/'.$badParent->id.'/download')->assertNotFound();
        $this->getJson('/api/v1/documents/'.$pending->id.'/download')->assertNotFound();
        $this->get('/api/v1/documents/'.$allowed->id.'/download')->assertOk()->assertDownload('ALLOWED.txt')->assertHeader('Cache-Control', 'no-store, private');
        $this->withToken($this->credential($user, ['documents:read'], ['document_ids' => [$allowed->id]]))
            ->getJson('/api/v1/documents/'.$allowed->id.'/download')->assertForbidden();
    }

    public function test_document_creation_rejects_unselected_or_confidential_grants_and_corrupt_version_parent(): void
    {
        Storage::fake('local');
        $user = $this->issuer();
        $document = $this->document($user, 'ONE');
        $other = $this->document($user, 'TWO');
        $private = $this->document($user, 'PRIVATE', 'finance_confidential');
        $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()])->postJson(route('settings.api-access.store'),
            ['name' => 'No document policy', 'scopes' => ['documents:read']])->assertUnprocessable();
        $this->postJson(route('settings.api-access.store'), ['name' => 'Private', 'scopes' => ['documents:read'],
            'access_policy' => ['document_ids' => [$private->id]]])->assertUnprocessable();
        $this->postJson(route('settings.api-access.store'), ['name' => 'Selected', 'scopes' => ['documents:read'],
            'access_policy' => ['document_ids' => [$document->id]]])->assertCreated();
        $document->update(['current_version_id' => $other->current_version_id]);
        $token = $this->credential($user, ['documents:read'], ['document_ids' => [$document->id]]);
        $this->withToken($token)->getJson('/api/v1/documents/'.$document->id)->assertNotFound();
    }

    public function test_manufacturing_splits_formula_and_cost_scopes_and_guards_foreign_products(): void
    {
        $user = $this->issuer();
        $product = Product::create(['org_id' => $user->org_id, 'name' => 'Finished', 'type' => 'product', 'unit' => 'pcs']);
        $bom = BillOfMaterials::create(['org_id' => $user->org_id, 'product_id' => $product->id, 'bom_no' => 'B1', 'name' => 'BOM', 'quantity' => '1.0000', 'unit' => 'pcs', 'total_cost' => '15.00']);
        BomItem::create(['org_id' => $user->org_id, 'bill_of_materials_id' => $bom->id, 'product_id' => $product->id, 'quantity' => '2.0000', 'unit' => 'pcs', 'unit_cost' => '7.50', 'total_cost' => '15.00']);
        $foreign = User::factory()->create();
        $foreignProduct = Product::create(['org_id' => $foreign->org_id, 'name' => 'Foreign', 'type' => 'product']);
        BillOfMaterials::create(['org_id' => $user->org_id, 'product_id' => $foreignProduct->id, 'bom_no' => 'BAD', 'name' => 'Corrupt', 'unit' => 'pcs']);
        $order = WorkOrder::create(['org_id' => $user->org_id, 'wo_no' => 'WO1', 'bill_of_materials_id' => $bom->id, 'product_id' => $product->id,
            'planned_quantity' => '10.0000', 'actual_quantity' => '8.0000', 'scrap_quantity' => '2.0000', 'status' => 'in_progress', 'total_cost' => '80.00']);
        $this->withToken($this->credential($user, ['manufacturing:boms:read']))->getJson('/api/v1/manufacturing/boms')->assertOk()->assertJsonCount(1, 'data')->assertJsonMissingPath('data.0.costs');
        $this->getJson('/api/v1/manufacturing/boms/'.$bom->id.'/items')->assertForbidden();
        $this->withToken($this->credential($user, ['manufacturing:boms:read', 'manufacturing:formulas:read']))
            ->getJson('/api/v1/manufacturing/boms/'.$bom->id.'/items')->assertOk()->assertJsonPath('data.0.quantity', '2.0000')->assertJsonMissingPath('data.0.unit_cost');
        $this->withToken($this->credential($user, ['manufacturing:work-orders:read', 'manufacturing:costs:read', 'summary:manufacturing:read']))
            ->getJson('/api/v1/manufacturing/work-orders/'.$order->id)->assertOk()->assertJsonPath('data.costs.total_cost', '80.00')->assertJsonPath('data.costs.currency', null);
        $this->getJson('/api/v1/summaries/manufacturing')->assertOk()->assertJsonPath('data.snapshot.active_boms', 1)
            ->assertJsonPath('data.snapshot.production_quantities_by_unit.0.actual_quantity', '8.0000');
    }

    public function test_approval_reads_follow_assignee_and_current_delegation_without_snapshots_or_write_actions(): void
    {
        $user = $this->issuer();
        $other = User::factory()->create(['org_id' => $user->org_id]);
        $supplier = Supplier::create(['org_id' => $user->org_id, 'supplier_code' => 'S', 'name' => 'Supplier']);
        $order = PurchaseOrder::create(['org_id' => $user->org_id, 'supplier_id' => $supplier->id, 'po_no' => 'PO', 'order_date' => '2026-10-01']);
        $definition = WorkflowDefinition::create(['org_id' => $user->org_id, 'code' => 'PO', 'name' => 'PO workflow', 'subject_type' => 'purchase_order', 'version' => 1]);
        $instance = WorkflowInstance::create(['org_id' => $user->org_id, 'workflow_definition_id' => $definition->id, 'subject_type' => 'purchase_order',
            'subject_id' => $order->id, 'requester_user_id' => $other->id, 'status' => 'pending', 'current_step_no' => 1, 'submitted_at' => now(),
            'definition_snapshot' => ['PRIVATE' => 'PRIVATE_SNAPSHOT'], 'subject_snapshot' => ['PRIVATE' => 'PRIVATE_SNAPSHOT']]);
        $mine = WorkflowApproval::create(['workflow_instance_id' => $instance->id, 'step_no' => 1, 'assigned_user_id' => $user->id, 'status' => 'pending', 'comment' => 'PRIVATE_COMMENT']);
        $delegated = WorkflowApproval::create(['workflow_instance_id' => $instance->id, 'step_no' => 2, 'assigned_user_id' => $other->id, 'status' => 'pending']);
        WorkflowDelegation::create(['org_id' => $user->org_id, 'delegator_user_id' => $other->id, 'delegate_user_id' => $user->id,
            'subject_type' => 'purchase_order', 'is_active' => true, 'starts_at' => now()->subHour(), 'ends_at' => now()->addDay()]);
        $token = $this->credential($user, ['approvals:read']);
        $response = $this->withToken($token)->getJson('/api/v1/approvals')->assertOk()->assertJsonCount(2, 'data');
        $this->assertStringNotContainsString('PRIVATE_SNAPSHOT', $response->getContent());
        $this->assertStringNotContainsString('PRIVATE_COMMENT', $response->getContent());
        $this->getJson('/api/v1/approvals/'.$mine->id)->assertOk();
        $this->postJson('/api/v1/approvals/'.$mine->id, ['action' => 'approved'])->assertStatus(405);
        $this->assertSame('pending', $mine->fresh()->status);
        $this->travel(2)->days();
        $this->getJson('/api/v1/approvals')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/approvals/'.$delegated->id)->assertNotFound();
    }
}
