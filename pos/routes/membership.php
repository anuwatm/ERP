<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Pos\Services\MemberDraftService;
use Pos\Services\MembershipService;
use Pos\Services\PharmacyPolicy;
use Pos\Services\RestaurantService;

Route::middleware(['pos.staff', 'throttle:30,1'])->prefix('membership')->group(function () {
    $access = function (Request $r, MembershipService $service) {
        abort_unless(in_array($r->ip(), ['127.0.0.1', '::1'], true), 403);

        return $service->actor($r->user());
    };
    Route::get('/', function (Request $r, MembershipService $service) use ($access) {
        $branch = $access($r, $service);

        return response()->view('membership', ['options' => $service->options($r->user()), 'drafts' => DB::table('pos_member_drafts')->where('branch_id', $branch->id)->latest()->limit(50)->get(['id', 'status', 'member_id', 'created_at'])])->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer');
    });
    Route::post('/drafts', function (Request $r, MembershipService $service, MemberDraftService $drafts) use ($access) {
        $access($r, $service);
        $result = $drafts->submit($r->user(), $r->all());
        if ($r->expectsJson()) {
            return response()->json($result, 201);
        }

        return redirect('/membership')->with('status', 'ส่งคำขอแล้ว รอ ERP ตรวจรับ ยังไม่เปิดใช้สิทธิ์สมาชิก');
    });
    Route::post('/quote', function (Request $r, MembershipService $service) use ($access) {
        return DB::transaction(function () use ($r, $service, $access) {
            $branch = $access($r, $service);
            $raw = $r->all();
            if (! empty($raw['kitchen_line_ids'])) {
                $bill = app(RestaurantService::class)->billingInput($r->user(), $raw + ['request_key' => 'preview-'.Str::uuid(), 'cash_tendered' => '0.00']);
                $raw = array_merge($raw, array_intersect_key($bill, array_flip(['items', 'catalog_version'])));
            }
            $d = validator($raw, ['catalog_version' => 'required|string|max:80', 'items' => 'required|array|min:1|max:100', 'items.*.product_id' => 'required|uuid|distinct', 'items.*.quantity' => ['required', 'string', 'regex:/^\d{1,10}(\.\d{1,4})?$/D'], 'items.*.unit' => 'sometimes|string|max:30', 'member_id' => 'nullable|uuid', 'promotion_id' => 'nullable|uuid', 'membership_publication_id' => 'required|uuid', 'kitchen_line_ids' => 'sometimes|array|max:100', 'discount_approval_id' => 'sometimes|uuid'])->validate();
            $catalog = DB::table('pos_catalogs')->where('branch_id', $branch->id)->where('version', $d['catalog_version'])->when(empty($d['kitchen_line_ids']) || $branch->business_type !== 'restaurant', fn ($q) => $q->where('valid_until', '>', now()))->firstOrFail();
            $products = collect(json_decode($catalog->items, true))->keyBy('product_id');
            $lines = [];
            foreach ($d['items'] as $item) {
                $product = $products->get($item['product_id']);
                abort_unless($product && ($product['active'] ?? false) && bccomp($item['quantity'], '0', 4) > 0, 422, 'Product unavailable.');
                $resolved = app(PharmacyPolicy::class)->resolve($r->user(), $branch->business_type, $product, $item);
                $lines[] = ['id' => $item['product_id'], 'kind' => 'merchandise', 'product_id' => $item['product_id'], 'net' => bcadd(bcmul($resolved['unit_price'], $item['quantity'], 8), '0.005', 2), 'tax_rate' => $product['tax_rate']];
            }
            $proof = $service->quote($r->user(), $d, $lines);

            return response()->json(['quote' => $proof['quote'] ?? null])->header('Cache-Control', 'private, no-store');
        });
    });
});
