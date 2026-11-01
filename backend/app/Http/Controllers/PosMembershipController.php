<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\PosMember;
use App\Models\Product;
use App\Services\Pos\PosMemberRequestImportService;
use App\Services\Pos\PosMembershipMasterService;
use App\Services\Pos\PosMembershipPublicationService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PosMembershipController extends Controller
{
    private function guard(Request $r): void
    {
        abort_unless(app()->environment(['local', 'testing']) && config('pos.local_membership_enabled') === true, 503, 'Local membership disabled.');
        abort_unless($r->user()->status === 'active' && $r->user()->hasPermissionCode('pos.manage'), 403);
    }

    public function index(Request $r)
    {
        $this->guard($r);
        $org = $r->user()->org_id;
        $members = PosMember::where('org_id', $org)->latest()->limit(500)->get()->map(fn ($m) => ['id' => $m->id, 'code' => $m->public_code, 'label' => mb_substr($m->contact['name'], 0, 1).'*** · ***'.substr($m->contact['phone'], -4).' · '.$m->tier, 'status' => $m->status]);
        $requests = DB::table('pos_import_member_requests')->where('org_id', $org)->where('status', 'pending_review')->latest()->limit(100)->get()->map(function ($row) use ($r) {
            $contact = json_decode(Crypt::decryptString($row->contact), true);
            $row->label = mb_substr($contact['name'], 0, 1).'*** · ***'.substr($contact['phone'], -4);
            $row->candidates = app(PosMembershipMasterService::class)->candidates($r->user(), $contact['phone']);
            unset($row->contact, $row->source_snapshot);

            return $row;
        });

        return response()->view('pos-membership', ['members' => $members, 'requests' => $requests, 'branches' => Branch::where('org_id', $org)->where('status', 'active')->get(['id', 'name']), 'products' => Product::where('org_id', $org)->where('is_active', true)->orderBy('name')->get(['id', 'name']), 'rules' => DB::table('pos_promotion_rules')->where('org_id', $org)->latest()->get(), 'instances' => DB::table('pos_instances')->where('org_id', $org)->where('active', true)->get(['id', 'name'])])->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer');
    }

    public function register(Request $r, PosMembershipMasterService $service)
    {
        $this->guard($r);
        $service->register($r->user(), ['request_key' => $r->input('request_key'), 'name' => $r->input('member_name'), 'phone' => $r->input('member_phone'), 'tier' => $r->input('tier'), 'consent_confirmed' => $r->input('consent_confirmed')]);

        return redirect()->route('pos-membership.index')->with('status', 'สร้างสมาชิกกลางแล้ว');
    }

    public function promotion(Request $r, PosMembershipMasterService $service)
    {
        $this->guard($r);
        $d = $r->validate(['form_complete' => 'required|in:membership-policy-v1', 'name' => 'required|string|max:120', 'mode' => 'required|in:fixed,percent', 'value' => 'required|string', 'starts_at' => 'required|date', 'ends_at' => 'required|date|after:starts_at', 'branch_ids' => 'required|array|min:1|max:100', 'branch_ids.*' => 'required|uuid|distinct', 'product_ids' => 'sometimes|array|max:1000', 'product_ids.*' => 'required|uuid|distinct', 'business_types' => 'required|array|min:1', 'business_types.*' => 'required|in:retail,restaurant,pharmacy|distinct', 'minimum_spend' => 'required|string', 'minimum_components' => 'required|array|min:1', 'discount_components' => 'required|array|min:1', 'max_uses' => 'nullable|integer|min:1', 'per_member_limit' => 'nullable|integer|min:1']);
        $p = ['id' => (string) Str::uuid(), 'version' => 1, 'mode' => $d['mode'], 'value' => $d['value'], 'currency' => $r->user()->organization->currency, 'starts_at' => Carbon::parse($d['starts_at'], 'Asia/Bangkok')->utc()->format('Y-m-d\TH:i:s\Z'), 'ends_at' => Carbon::parse($d['ends_at'], 'Asia/Bangkok')->utc()->format('Y-m-d\TH:i:s\Z'), 'branch_ids' => $d['branch_ids'], 'business_types' => $d['business_types'], 'member_required' => $r->boolean('member_required'), 'member_tiers' => array_values(array_filter(array_map('trim', explode(',', (string) $r->input('member_tiers', ''))))), 'product_ids' => $d['product_ids'] ?? [], 'minimum_spend' => $d['minimum_spend'], 'minimum_eligible_only' => $r->boolean('minimum_eligible_only'), 'minimum_components' => $d['minimum_components'], 'discount_components' => $d['discount_components'], 'max_uses' => isset($d['max_uses']) ? (int) $d['max_uses'] : null, 'per_member_limit' => isset($d['per_member_limit']) ? (int) $d['per_member_limit'] : null, 'combination' => 'exclusive', 'refund_entitlement' => 'full_only'];
        try {
            $service->definePromotion($r->user(), $d['name'], $p);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['policy' => $e->getMessage()]);
        }

        return redirect()->route('pos-membership.index')->with('status', 'สร้างโปรโมชันแล้ว ยังต้องเผยแพร่ไป POS');
    }

    public function allocate(Request $r, PosMembershipPublicationService $service)
    {
        $this->guard($r);
        $d = $r->validate(['instance_id' => 'required|uuid', 'rule_id' => 'required|uuid', 'member_id' => 'nullable|uuid', 'quantity' => 'required|integer|min:1|max:100', 'request_key' => 'required|uuid']);
        $service->allocate($r->user(), $d['instance_id'], $d['rule_id'], $d['member_id'] ?? null, (int) $d['quantity'], $d['request_key']);

        return redirect()->route('pos-membership.index')->with('status', 'จัดสรรสิทธิ์แล้ว ต้องเผยแพร่ให้ POS ตรวจรับก่อนใช้');
    }

    public function publish(Request $r, PosMembershipPublicationService $service)
    {
        $this->guard($r);
        $d = $r->validate(['instance_id' => 'required|uuid', 'member_ids' => 'sometimes|array|max:500', 'member_ids.*' => 'required|uuid|distinct', 'rule_ids' => 'sometimes|array|max:100', 'rule_ids.*' => 'required|uuid|distinct']);
        $service->create($r->user(), $d['instance_id'], $d['member_ids'] ?? [], $d['rule_ids'] ?? []);

        return redirect()->route('pos-membership.index')->with('status', 'เตรียม publication แล้ว รอ worker ส่งผ่าน connector');
    }

    public function decide(Request $r, string $id, PosMemberRequestImportService $service, PosMembershipPublicationService $publisher)
    {
        $this->guard($r);
        $d = $r->validate(['decision' => 'required|in:approve,reject', 'member_id' => 'nullable|uuid']);
        $row = DB::table('pos_import_member_requests')->where('id', $id)->where('org_id', $r->user()->org_id)->firstOrFail();
        $service->decide($r->user(), $id, $d['decision'], $d['member_id'] ?? null);
        $publisher->forSync($row->pos_instance_id, true, $r->user());

        return redirect()->route('pos-membership.index')->with('status', 'ตรวจรับคำขอแล้ว รอ worker ส่งผลไป POS');
    }

    public function contact(Request $r, string $id)
    {
        $this->guard($r);
        $row = DB::table('pos_import_member_requests')->where('id', $id)->where('org_id', $r->user()->org_id)->firstOrFail();
        AuditLog::create(['org_id' => $r->user()->org_id, 'actor_user_id' => $r->user()->id, 'action' => 'pos.member.request.contact_read', 'entity_type' => 'pos_membership', 'entity_id' => $row->id, 'after_json' => [], 'request_id' => (string) Str::uuid()]);

        return response()->json(json_decode(Crypt::decryptString($row->contact), true))->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer');
    }
}
