<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Pos\Models\Delivery;
use Pos\Models\Staff;
use Pos\Services\DeliveryService;
use Pos\Services\MembershipService;

Route::middleware(['pos.staff', 'throttle:60,1'])->prefix('deliveries')->group(function () {
    $access = function (Request $r, DeliveryService $service): object {
        abort_unless(in_array($r->ip(), ['127.0.0.1', '::1'], true), 403);

        return DB::transaction(fn () => $service->actor($r->user()));
    };
    Route::get('/', function (Request $r, DeliveryService $service) use ($access) {
        $branch = $access($r, $service);
        $q = DB::table('pos_catalogs')->where('branch_id', $branch->id)->where('valid_until', '>', now());
        $catalog = ($branch->active_catalog_version ? $q->where('version', $branch->active_catalog_version) : $q->latest('created_at'))->first();

        return response()->view('deliveries', [
            'deliveries' => Delivery::where('branch_id', $branch->id)->latest()->paginate(20),
            'sales' => DB::table('pos_orders')->where('branch_id', $branch->id)->where('status', 'completed')->whereNotIn('id', Delivery::where('branch_id', $branch->id)->whereNotNull('sale_id')->select('sale_id'))->latest()->limit(50)->get(['id', 'receipt_no']),
            'couriers' => Staff::where('branch_id', $branch->id)->where('active', true)->get(['id', 'name']),
            'catalog' => $catalog,
            'branch' => $branch,
            'taxRates' => config('pos.delivery_fee_tax_rates'),
            'membershipOptions' => config('pos.local_membership_enabled') === true ? app(MembershipService::class)->options($r->user()) : ['publication_id' => null, 'members' => [], 'policies' => []],
            'terminals' => DB::table('pos_terminals')->where('branch_id', $branch->id)->where('active', true)->get(['id', 'code']),
            'products' => $catalog ? array_slice(array_values(array_filter(json_decode($catalog->items, true), fn ($p) => $p['active'] ?? false)), 0, 100) : [],
        ])->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer');
    });
    Route::post('/', function (Request $r, DeliveryService $service) use ($access) {
        $access($r, $service);
        $input = $r->all();
        if (! $r->isJson()) {
            $input['items'] = array_values(array_filter($input['items'] ?? [], fn ($p) => isset($p['product_id'])));
        }
        $delivery = $service->create($r->user(), $input);

        return $r->expectsJson()
            ? response()->json(['id' => $delivery->id, 'status' => $delivery->status, 'version' => $delivery->version], 201)->header('Cache-Control', 'no-store')
            : redirect('/deliveries')->with('delivery_notice', 'บันทึกงานจัดส่งแล้ว ยังไม่ได้สร้างรายการเงินหรือ Stock เพิ่ม');
    });
    Route::post('/{id}/transition', function (Request $r, string $id, DeliveryService $service) use ($access) {
        $access($r, $service);
        $input = $r->all();
        if (! $r->isJson() && isset($input['items'])) {
            $input['items'] = array_values(array_filter($input['items'], fn ($p) => isset($p['product_id'])));
        }
        $delivery = $service->transition($r->user(), $id, $input);

        return $r->expectsJson()
            ? response()->json(['id' => $delivery->id, 'status' => $delivery->status, 'version' => $delivery->version])->header('Cache-Control', 'no-store')
            : redirect('/deliveries')->with('delivery_notice', 'บันทึกการดำเนินการแล้ว COD เป็นหลักฐานเงินรับฝากรอตรวจรับบัญชี');
    });
    Route::get('/{id}/slip', function (Request $r, string $id, DeliveryService $service) use ($access) {
        $branch = $access($r, $service);

        return response()->view('delivery-slip', ['delivery' => Delivery::where('branch_id', $branch->id)->whereKey($id)->firstOrFail(), 'branch' => $branch])->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer');
    });
});
