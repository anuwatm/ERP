<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Pos\PosAdminService;
use App\Services\Pos\PosCheckpointService;
use App\Services\Pos\PosProvisioningService;
use App\Services\Pos\PosRecallService;
use App\Services\Pos\PosReconciliationService;
use App\Services\Pos\PosTransport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

class PosController extends Controller
{
    public function reconciliation(Request $request, string $instance, PosReconciliationService $service)
    {
        $filters = $request->validate(['from' => 'sometimes|date_format:Y-m-d', 'to' => 'sometimes|date_format:Y-m-d']);
        $report = $service->report($request->user()->org_id, $instance, $filters['from'] ?? now('Asia/Bangkok')->toDateString(), $filters['to'] ?? now('Asia/Bangkok')->toDateString());

        return Inertia::render('Admin/PosReconciliation', ['report' => $report]);
    }

    public function index(Request $request)
    {
        $ids = DB::table('pos_instances')->where('org_id', $request->user()->org_id)->select('id');

        return Inertia::render('Admin/Pos', [
            'instances' => DB::table('pos_instances')->where('org_id', $request->user()->org_id)->get(['id', 'name', 'branch_id', 'warehouse_id', 'active', 'durable_cursor', 'updated_at', 'last_sync_at', 'last_sync_error', 'next_sync_at', 'recovery_fenced_at', 'checkpoint_cursor']),
            'events' => DB::table('pos_sync_inbox')->whereIn('pos_instance_id', $ids)->latest('updated_at')->limit(100)->get(['id', 'pos_instance_id', 'event_id', 'source_sequence', 'status', 'reason_code', 'attempts', 'acknowledged_at', 'updated_at']),
            'inventoryPending' => DB::table('pos_import_sales')->whereIn('pos_instance_id', $ids)->where('inventory_status', 'pending')->count(),
            'integrityConflicts' => DB::table('pos_sync_conflicts')->whereIn('pos_instance_id', $ids)->count(),
            'transportEnabled' => false,
            'canReconcile' => $request->user()->hasPermissionCode('pos.reconcile'),
            'canManage' => $request->user()->hasPermissionCode('pos.manage'),
            'taxDocumentsEnabled' => app()->environment(['local', 'testing']) && config('pos.local_tax_documents_enabled') === true,
            'productOptions' => $request->user()->hasPermissionCode('pos.manage') ? DB::table('products')->where('org_id', $request->user()->org_id)->whereNull('deleted_at')->where('is_active', true)->orderBy('name')->limit(500)->get(['id', 'name', 'sku']) : [],
            'canProvision' => $request->user()->hasPermissionCode('pos.manage') && $request->user()->hasPermissionCode('inventory.adjust'),
            'setupOptions' => $request->user()->hasPermissionCode('pos.manage') ? [
                'branches' => DB::table('branches')->where('org_id', $request->user()->org_id)->whereNull('deleted_at')->where('status', 'active')->get(['id', 'name', 'code']),
                'warehouses' => DB::table('warehouses')->where('org_id', $request->user()->org_id)->get(['id', 'name']),
                'customers' => DB::table('customers')->where('org_id', $request->user()->org_id)->whereNull('deleted_at')->limit(500)->get(['id', 'company_name as name']),
                'actors' => User::where('org_id', $request->user()->org_id)->where('status', 'active')->get(['id', 'name']),
                'accounts' => DB::table('chart_of_accounts')->where('org_id', $request->user()->org_id)->where('status', 'active')->where('is_postable', true)->get(['code', 'name', 'account_type']),
                'products' => DB::table('products')->where('org_id', $request->user()->org_id)->whereNull('deleted_at')->where('is_active', true)->orderBy('name')->limit(500)->get(['id', 'name']),
                'lots' => DB::table('inventory_lots')->where('org_id', $request->user()->org_id)->orderBy('lot_no')->limit(500)->get(['id', 'lot_no as name', 'product_id']),
                'boms' => DB::table('bill_of_materials')->where('org_id', $request->user()->org_id)->whereNull('deleted_at')->where('is_active', true)->limit(500)->get(['id', 'name', 'product_id']),
            ] : null,
            'recallsEnabled' => app()->environment(['local', 'testing']) && config('pos.local_recalls_enabled') === true,
            'lotOptions' => $request->user()->hasPermissionCode('pos.manage') ? DB::table('inventory_lots as l')->join('products as p', 'p.id', '=', 'l.product_id')->where('l.org_id', $request->user()->org_id)->where('p.org_id', $request->user()->org_id)->orderBy('l.lot_no')->limit(500)->get(['l.id', 'l.lot_no', 'p.name']) : [],
            'lotRecalls' => DB::table('pos_lot_recalls')->where('org_id', $request->user()->org_id)->latest()->limit(100)->get(['id', 'inventory_lot_id', 'reason', 'created_at']),
            'taxSigningOwnerId' => DB::table('pos_tax_settings')->where('org_id', $request->user()->org_id)->value('signing_owner_id'),
            'taxSigningOwners' => $request->user()->hasPermissionCode('pos.manage') ? User::where('org_id', $request->user()->org_id)->where('status', 'active')->orderBy('name')->get(['id', 'name']) : [],
        ]);
    }

    public function taxSettings(Request $request)
    {
        $data = $request->validate(['signing_owner_id' => 'present|nullable|uuid', 'reason' => 'required|string|max:500']);
        DB::transaction(function () use ($request, $data) {
            $orgId = $request->user()->org_id;
            // Serializes first configuration as well as later owner changes.
            DB::table('organizations')->where('id', $orgId)->lockForUpdate()->firstOrFail();
            if ($data['signing_owner_id'] !== null) {
                User::where('org_id', $orgId)->where('status', 'active')->where('id', $data['signing_owner_id'])->lockForUpdate()->firstOrFail();
            }
            $old = DB::table('pos_tax_settings')->where('org_id', $orgId)->first();
            DB::table('pos_tax_settings')->updateOrInsert(['org_id' => $orgId], ['signing_owner_id' => $data['signing_owner_id'], 'updated_by' => $request->user()->id, 'created_at' => $old?->created_at ?? now(), 'updated_at' => now()]);
            $this->audit($request, 'pos.tax.signing_owner_changed', $orgId, ['previous_owner_id' => $old?->signing_owner_id, 'signing_owner_id' => $data['signing_owner_id'], 'reason' => $data['reason'], 'provider_status' => 'not_configured']);
        });

        return back()->with('success', 'บันทึกผู้รับผิดชอบ e-Tax แล้ว ยังไม่เปิดการลงนามหรือส่งเอกสาร');
    }

    public function register(Request $request, PosAdminService $service)
    {
        $service->register($request->user(), $request->all());

        return back()->with('success', 'ลงทะเบียน instance แบบ paused แล้ว ตั้งค่า POS/Connector ให้ตรงกันก่อน activate');
    }

    public function catalog(Request $request, string $instance, PosProvisioningService $service)
    {
        abort_unless(app()->environment(['local', 'testing']), 403);
        DB::table('pos_instances')->where('id', $instance)->where('org_id', $request->user()->org_id)->firstOrFail();
        $d = $request->validate(['version' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{1,80}$/D'], 'product_ids' => 'required|array|min:1|max:5000', 'product_ids.*' => 'required|uuid|distinct', 'tax_rate' => ['required', 'string', 'regex:/^\d{1,2}(\.\d{1,2})?$/D'], 'valid_until' => 'required|date|after:now', 'online_product_ids' => 'present|array|max:5000', 'online_product_ids.*' => 'required|uuid|distinct', 'recipes' => 'present|array|max:5000', 'recipes.*.product_id' => 'required|uuid|distinct', 'recipes.*.bom_id' => 'nullable|uuid']);
        $service->catalog($instance, $d['version'], $d['product_ids'], $d['tax_rate'], new \DateTimeImmutable($d['valid_until']), $d['online_product_ids'], [], array_filter(array_column($d['recipes'], 'bom_id', 'product_id'), fn ($id) => $id !== null));
        $this->audit($request, 'pos.catalog.prepared', $instance, ['version' => $d['version'], 'product_count' => count($d['product_ids'])]);

        return back()->with('success', 'เตรียม catalog แล้ว รอ Connector เผยแพร่');
    }

    public function grant(Request $request, string $instance, PosProvisioningService $service)
    {
        abort_unless(app()->environment(['local', 'testing']), 403);
        DB::table('pos_instances')->where('id', $instance)->where('org_id', $request->user()->org_id)->firstOrFail();
        $d = $request->validate(['product_id' => 'required|uuid', 'quantity' => 'required|string|max:30', 'lot_id' => 'nullable|uuid']);
        $grant = $service->grant($instance, $d['product_id'], $d['quantity'], $d['lot_id'] ?? null);
        $this->audit($request, 'pos.quota.prepared', $instance, ['grant_id' => $grant['id'], 'product_id' => $d['product_id'], 'quantity' => $d['quantity']]);

        return back()->with('success', 'กัน stock quota แล้ว รอ Connector เผยแพร่ ไม่คืนสิทธิ์จาก timeout');
    }

    public function activate(Request $request, string $instance, PosAdminService $service)
    {
        $service->activate($request->user(), $instance);

        return back()->with('success', 'เปิดใช้งาน instance สำหรับ local sync แล้ว');
    }

    public function authority(Request $request, string $instance, PosAdminService $service)
    {
        $service->authority($request->user(), $instance, $request->all());

        return back()->with('success', 'บันทึกสิทธิ์ source staff แล้ว');
    }

    public function signingKey(Request $request, string $instance, PosAdminService $service)
    {
        $service->signingKey($request->user(), $instance, $request->all());

        return back()->with('success', 'ปรับสิทธิ์ verification key แล้ว เก็บ source evidence และ quota เดิมไว้');
    }

    public function recover(Request $request, string $instance, PosAdminService $service)
    {
        $d = $request->validate(['checkpoint_id' => 'required|uuid', 'reason' => 'required|string|max:500']);
        $service->recover($request->user(), $instance, $d['checkpoint_id'], $d['reason']);

        return back()->with('success', 'ตรวจหลักฐานและปลด recovery fence แล้ว');
    }

    public function checkpoint(Request $request, string $instance, PosTransport $transport, PosCheckpointService $service)
    {
        $d = $request->validate(['after_sequence' => 'required|integer|min:0']);
        $row = DB::table('pos_instances')->where('id', $instance)->where('org_id', $request->user()->org_id)->firstOrFail();
        $snapshot = $transport->request($instance, 'checkpoint', ['branch_id' => $row->branch_id, 'after_sequence' => (int) $d['after_sequence']]);
        $result = $service->verify($instance, $snapshot);
        $this->audit($request, 'pos.checkpoint.captured', $instance, ['checkpoint_id' => $result['checkpoint_id'], 'status' => $result['status']]);

        return back()->with('success', 'Checkpoint '.$result['checkpoint_id'].' · '.$result['status'].' · through '.$result['through_sequence'].' / '.$result['high_watermark']);
    }

    public function restaurantVariant(Request $request, PosProvisioningService $service)
    {
        $service->restaurantVariant($request->user(), $request->all());

        return back()->with('success', 'บันทึกขนาด/ตัวเลือกแล้ว เผยแพร่ catalog ใหม่เพื่อให้ POS ใช้ราคาและสูตรของ SKU นี้');
    }

    public function taxProfile(Request $request, PosProvisioningService $service)
    {
        $d = $request->validate(['instance_id' => 'required|uuid', 'version' => 'required|string|max:80', 'branch_no' => ['required', 'string', 'regex:/^\d{5}$/D']]);
        $service->taxProfile($request->user(), $d['instance_id'], $d['version'], $d['branch_no']);

        return back()->with('success', 'เตรียมข้อมูลผู้ออกเอกสารแล้ว รอ Connector เผยแพร่ไป POS');
    }

    public function recall(Request $request, PosRecallService $service)
    {
        $data = $request->validate(['lot_id' => 'required|uuid', 'reason' => 'required|string|max:500']);
        $service->recall($request->user(), $data['lot_id'], $data['reason']);

        return back()->with('success', 'บันทึก lot recall แล้ว รอส่งคำสั่งหยุดขายให้ POS ผ่าน Connector');
    }

    public function pause(Request $request, string $instance)
    {
        $data = $request->validate(['reason' => 'required|string|max:500']);
        DB::transaction(function () use ($request, $instance, $data) {
            DB::table('pos_instances')->where('id', $instance)->where('org_id', $request->user()->org_id)->lockForUpdate()->firstOrFail();
            DB::table('pos_instances')->where('id', $instance)->update(['active' => false, 'updated_at' => now()]);
            $this->audit($request, 'pos.instance.pause', $instance, ['reason' => $data['reason']]);
        });

        return back()->with('success', 'POS instance paused; stock entitlement remains reserved.');
    }

    public function replay(Request $request, string $inbox)
    {
        $data = $request->validate(['reason' => 'required|string|max:500']);
        DB::transaction(function () use ($request, $inbox, $data) {
            $row = DB::table('pos_sync_inbox')->where('id', $inbox)->whereIn('pos_instance_id', DB::table('pos_instances')->where('org_id', $request->user()->org_id)->select('id'))->lockForUpdate()->firstOrFail();
            abort_if($row->status === 'accepted', 422, 'Accepted events cannot be reset.');
            // Retry counter is reset only by an authorized, audited operation; evidence is immutable.
            DB::table('pos_sync_inbox')->where('id', $inbox)->update(['status' => 'pending', 'attempts' => 0, 'acknowledged_at' => null, 'updated_at' => now()]);
            $this->audit($request, 'pos.event.replay', $inbox, ['reason' => $data['reason'], 'previous_status' => $row->status, 'previous_attempts' => $row->attempts]);
        });

        return back()->with('success', 'Event scheduled for validation through the original import pipeline.');
    }

    private function audit(Request $r, string $action, string $id, array $metadata): void
    {
        AuditLog::create(['org_id' => $r->user()->org_id, 'actor_user_id' => $r->user()->id, 'action' => $action, 'entity_type' => 'pos', 'entity_id' => $id, 'after_json' => $metadata, 'request_id' => (string) Str::uuid()]);
    }
}
