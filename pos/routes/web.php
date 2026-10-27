<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Pos\Models\Staff;
use Pos\Services\CashMovementService;
use Pos\Services\CheckoutService;
use Pos\Services\DiscountService;
use Pos\Services\ManagerApproval;
use Pos\Services\PrescriptionService;
use Pos\Services\PublicMenuService;
use Pos\Services\RefundService;
use Pos\Services\RestaurantService;
use Pos\Services\ReturnInspectionService;
use Pos\Services\TaxDocumentService;
use Pos\Support\LocalFeatureGate;

Route::get('/', fn () => view('pos'));
Route::middleware(['pos.local-menu', 'throttle:pos-public'])->group(function () {
    Route::get('/menu', fn () => view('menu'));
    Route::post('/menu/catalog', function (Request $r, PublicMenuService $service) {
        $d = $r->validate(['token' => 'required|string|size:64']);

        return response()->json($service->catalog($d['token']));
    });
    Route::post('/menu/requests', function (Request $r, PublicMenuService $service) {
        $d = $r->validate(['token' => 'required|string|size:64', 'request_key' => 'required|string|max:100', 'items' => 'required|array|min:1|max:100']);

        return response()->json($service->submit($d['token'], $d['request_key'], $d['items']));
    });
    Route::post('/menu/status', function (Request $r, PublicMenuService $service) {
        $d = $r->validate(['token' => 'required|string|size:64', 'id' => 'required|uuid']);

        return response()->json($service->status($d['token'], $d['id']));
    });
});
Route::post('/login', function (Request $r) {
    $data = $r->validate(['email' => 'required|email', 'password' => 'required|string|max:1000']);
    abort_unless(Auth::attempt($data + ['active' => true]), 422, 'Invalid credentials.');
    $r->session()->regenerate();
    $r->session()->put(['pos_login_at' => time(), 'pos_last_seen' => time()]);

    return response()->json(['status' => 'authenticated']);
})->middleware('throttle:pos-login');
Route::post('/logout', function (Request $r) {
    Auth::logout();
    $r->session()->invalidate();
    $r->session()->regenerateToken();

    return response()->json(['status' => 'logged_out']);
});
Route::middleware('pos.staff')->group(function () {
    Route::post('/local-voids', function (Request $r, RefundService $service) {
        abort_unless(in_array($r->ip(), ['127.0.0.1', '::1'], true), 403);
        $d = $r->validate(['order_id' => 'required|uuid', 'terminal_id' => 'required|uuid', 'request_key' => 'required|string|max:100', 'manager_password' => 'required|string|max:1000']);

        return DB::transaction(function () use ($r, $d, $service) {
            $order = DB::table('pos_orders')->where('id', $d['order_id'])->where('branch_id', $r->user()->branch_id)->where('status', 'completed')->firstOrFail();
            abort_if(DB::table('pos_refunds')->where('order_id', $order->id)->where('request_key', '!=', $d['request_key'])->exists(), 409, 'A partially refunded sale must use the original refund workflow.');
            $source = json_decode($order->snapshot, true);
            $command = $service->command(['order_id' => $order->id, 'terminal_id' => $d['terminal_id'], 'request_key' => $d['request_key'], 'reason' => 'sale_error', 'items' => array_map(fn ($line) => ['line_id' => $line['line_id'], 'quantity' => $line['quantity'], 'returned_quantity' => '0.0000'], $source['lines'])]);
            $approval = ManagerApproval::verify($r->user(), $d['manager_password'], $command);
            $result = $service->refund($r->user(), $command, $approval);
            if (! DB::table('pos_audit')->where('source_id', $result['refund_id'])->where('action', 'sale.void')->exists()) {
                DB::table('pos_audit')->insert(['actor_id' => $r->user()->id, 'branch_id' => $r->user()->branch_id, 'action' => 'sale.void', 'source_id' => $result['refund_id'], 'metadata' => json_encode(['sale_id' => $order->id, 'stock_policy' => 'no_automatic_restock']), 'created_at' => now()]);
            }

            return response()->json($result);
        });
    })->middleware('throttle:5,1');
    Route::get('/tax-documents', function (Request $r) {
        abort_unless(app()->environment(['local', 'testing']) && config('pos.local_tax_documents_enabled') === true && in_array($r->user()->role, ['cashier', 'manager', 'pharmacist'], true), 403);

        return response()->json(DB::table('pos_orders')->where('branch_id', $r->user()->branch_id)->where('status', 'completed')->latest()->limit(50)->get(['id', 'terminal_id', 'receipt_no', 'total'])->map(function ($sale) {
            $sale->documents = DB::table('pos_tax_documents as d')->leftJoin('pos_etax_queue as q', 'q.document_id', '=', 'd.id')->where('d.order_id', $sale->id)->orderBy('d.created_at')->get(['d.id', 'd.kind', 'd.document_no', 'd.status', 'q.status as etax_status']);

            return $sale;
        }));
    });
    Route::post('/tax-documents', fn (Request $r, TaxDocumentService $s) => response()->json($s->issue($r->user(), $r->all())))->middleware('throttle:20,1');
    Route::get('/tax-documents/{id}/print', function (Request $r, string $id) {
        abort_unless(app()->environment(['local', 'testing']) && config('pos.local_tax_documents_enabled') === true && in_array($r->user()->role, ['cashier', 'manager', 'pharmacist'], true), 403);
        $row = DB::table('pos_tax_documents')->where('id', $id)->where('branch_id', $r->user()->branch_id)->firstOrFail();
        DB::table('pos_audit')->insert(['actor_id' => $r->user()->id, 'branch_id' => $r->user()->branch_id, 'action' => 'tax.document_read', 'source_id' => $id, 'metadata' => '{}', 'created_at' => now()]);

        return response()->view('tax-document', ['document' => json_decode($row->snapshot, true), 'status' => $row->status, 'etax' => DB::table('pos_etax_queue')->where('document_id', $id)->value('status')])->header('Cache-Control', 'private, no-store');
    });
    Route::get('/local-return-inspections', function (Request $r) {
        abort_unless(in_array($r->ip(), ['127.0.0.1', '::1'], true) && app()->environment(['local', 'testing']) && config('pos.local_return_inspections_enabled') === true, 403);
        $staff = $r->user();
        abort_unless($staff->role === 'manager' && $staff->pharmacist_qualified && DB::table('pos_branches')->where('id', $staff->branch_id)->where('business_type', 'pharmacy')->where('active', true)->exists(), 403);
        $options = [];
        foreach (DB::table('pos_refunds')->where('branch_id', $staff->branch_id)->latest()->limit(50)->get() as $refund) {
            $order = DB::table('pos_orders')->where('id', $refund->order_id)->where('branch_id', $staff->branch_id)->firstOrFail();
            $sources = collect(json_decode($order->snapshot, true)['lines'])->keyBy('line_id');
            foreach (json_decode($refund->snapshot, true)['lines'] as $line) {
                if (bccomp($line['returned_quantity'], '0', 4) <= 0) {
                    continue;
                }
                $source = $sources->get($line['source_line_id']);
                if (! $source || ! isset($source['conversion_factor'])) {
                    continue;
                }
                $inspected = DB::table('pos_return_inspections')->where('refund_id', $refund->id)->where('source_line_id', $line['source_line_id'])->get()->reduce(fn ($sum, $i) => bcadd($sum, (string) $i->base_quantity, 4), '0.0000');
                $remaining = bcsub(bcmul($line['returned_quantity'], $source['conversion_factor'], 4), $inspected, 4);
                if (bccomp($remaining, '0', 4) <= 0) {
                    continue;
                }
                foreach ($source['allocations'] as $allocation) {
                    if (empty($allocation['lot_id'])) {
                        continue;
                    }
                    $options[] = ['refund_id' => $refund->id, 'source_line_id' => $line['source_line_id'], 'grant_id' => $allocation['grant_id'], 'lot_id' => $allocation['lot_id'], 'name' => $source['name'], 'remaining_base_quantity' => $remaining, 'original_lot_quantity' => $allocation['quantity']];
                }
            }
        }

        return response()->json($options);
    });
    Route::post('/local-return-inspections', function (Request $r, ReturnInspectionService $service) {
        abort_unless(in_array($r->ip(), ['127.0.0.1', '::1'], true), 403);
        $r->validate(['manager_password' => 'required|string|max:1000']);
        $command = $service->command($r->except('manager_password'));
        $approval = ManagerApproval::verify($r->user(), $r->input('manager_password'), $command);

        return response()->json($service->inspect($r->user(), $command, $approval));
    })->middleware('throttle:5,1');
    Route::prefix('restaurant')->middleware('throttle:60,1')->group(function () {
        Route::post('/customer-requests/{id}', function (Request $r, string $id, RestaurantService $s) {
            $d = $r->validate(['terminal_id' => 'required|uuid']);

            return response()->json($s->fromCustomerRequest($r->user(), $id, $d['terminal_id']));
        });
        Route::post('/tables', function (Request $r, RestaurantService $s) {
            $d = $r->validate(['label' => 'required|string|max:60']);

            return response()->json(['id' => $s->createTable($r->user(), $d['label'])]);
        });
        Route::post('/tables/{id}/open', fn (Request $r, string $id, RestaurantService $s) => response()->json(['id' => $s->openTable($r->user(), $id)]));
        Route::post('/sessions/{id}/close', function (Request $r, string $id, RestaurantService $s) {
            $s->closeTable($r->user(), $id);

            return response()->json(['status' => 'closed']);
        });
        Route::post('/orders', fn (Request $r, RestaurantService $s) => response()->json($s->createOrder($r->user(), $r->all())));
        Route::post('/lines/{id}/{action}', fn (Request $r, string $id, string $action, RestaurantService $s) => response()->json($s->advance($r->user(), $id, $action)));
        Route::post('/bill-preview', fn (Request $r, RestaurantService $s) => response()->json($s->preview($r->user(), $r->all())));
        Route::post('/checkout', fn (Request $r, RestaurantService $s, CheckoutService $checkout) => response()->json($checkout->checkout($r->user(), $s->billingInput($r->user(), $r->all()))));
        Route::post('/cancel', function (Request $r, RestaurantService $s) {
            $d = $r->validate(['manager_password' => 'required|string|max:1000']);
            $command = $s->cancelCommand($r->except('manager_password'));
            $approval = ManagerApproval::verify($r->user(), $d['manager_password'], $command);

            return response()->json($s->cancel($r->user(), $command, $approval));
        });
    });
    Route::post('/local-discount-approvals', function (Request $r, DiscountService $service) {
        abort_unless(in_array($r->ip(), ['127.0.0.1', '::1'], true), 403);
        $credentials = $r->validate(['manager_email' => 'required|email|max:255', 'manager_password' => 'required|string|max:1000']);
        $manager = Staff::where('email', $credentials['manager_email'])->where('branch_id', $r->user()->branch_id)->where('active', true)->where('role', 'manager')->first();
        abort_unless($manager, 403, 'Manager re-authentication required.');
        $command = $service->command($r->user(), $r->except('manager_email', 'manager_password'));
        $approval = ManagerApproval::verify($manager, $credentials['manager_password'], $command);

        return response()->json($service->approve($r->user(), $manager, $command, $approval));
    })->middleware('throttle:5,1');
    Route::post('/local-cash-movements', function (Request $r, CashMovementService $service) {
        abort_unless(in_array($r->ip(), ['127.0.0.1', '::1'], true), 403);
        $r->validate(['manager_password' => 'required|string|max:1000']);
        $command = $service->command($r->except('manager_password'));
        $approval = ManagerApproval::verify($r->user(), $r->input('manager_password'), $command);

        return response()->json($service->move($r->user(), $command, $approval));
    })->middleware('throttle:5,1');
    Route::post('/local-refunds', function (Request $r, RefundService $service) {
        abort_unless(in_array($r->ip(), ['127.0.0.1', '::1'], true), 403);
        $r->validate(['manager_password' => 'required|string|max:1000']);
        $command = $service->command($r->except('manager_password'));
        $approval = ManagerApproval::verify($r->user(), $r->input('manager_password'), $command);

        return response()->json($service->refund($r->user(), $command, $approval));
    })->middleware('throttle:5,1');
    Route::middleware('pos.local-menu')->group(function () {
        Route::post('/menu-tokens', function (Request $r, PublicMenuService $service) {
            $d = $r->validate(['minutes' => 'required|integer|min:1|max:1440', 'table_session_id' => 'nullable|uuid']);

            return response()->json($service->issue($r->user(), $d['minutes'], $d['table_session_id'] ?? null));
        });
        Route::post('/menu-tokens/{id}/revoke', function (Request $r, string $id, PublicMenuService $service) {
            $service->revoke($r->user(), $id);

            return response()->json(['status' => 'revoked']);
        });
        Route::post('/customer-requests/{id}/accept', fn (Request $r, string $id, PublicMenuService $service) => response()->json($service->accept($r->user(), $id)));
        Route::post('/customer-requests/{id}/reject', function (Request $r, string $id, PublicMenuService $service) {
            $service->reject($r->user(), $id);

            return response()->json(['status' => 'rejected']);
        });
    });
    Route::post('/local-fixtures/prescriptions', function (Request $r, PrescriptionService $service) {
        LocalFeatureGate::request($r);
        $r->validate(['file' => 'required|file|max:5120']);

        return response()->json($service->upload($r->user(), $r->file('file')));
    })->middleware('throttle:10,1');
    Route::get('/local-fixtures/prescriptions/{id}', function (Request $r, string $id, PrescriptionService $service) {
        LocalFeatureGate::request($r);

        return $service->download($r->user(), $id);
    });
    Route::get('/state', function (Request $r) {
        $user = $r->user();
        $discountData = ['discounts_enabled' => app()->environment(['local', 'testing']) && config('pos.local_discounts_enabled') === true && in_array($user->role, ['cashier', 'manager', 'pharmacist'], true)];
        $refundsEnabled = app()->environment(['local', 'testing']) && config('pos.local_refunds_enabled') === true && $user->role === 'manager';
        $refundable = $refundsEnabled ? DB::table('pos_orders')->where('branch_id', $user->branch_id)->where('status', 'completed')->latest()->limit(20)->get()->map(function ($order) {
            $snapshot = json_decode($order->snapshot, true);
            $lines = collect($snapshot['lines'])->map(function ($line) {
                $refunded = DB::table('pos_refund_lines')->where('order_item_id', $line['line_id'])->get()->reduce(fn ($sum, $r) => bcadd($sum, (string) $r->quantity, 4), '0.0000');

                return ['line_id' => $line['line_id'], 'name' => $line['name'], 'unit' => $line['unit'], 'remaining' => bcsub($line['quantity'], $refunded, 4)];
            });

            return ['id' => $order->id, 'receipt_no' => $order->receipt_no, 'lines' => $lines];
        }) : [];
        $refundData = ['refunds_enabled' => $refundsEnabled, 'refundable_sales' => $refundable, 'cash_movements_enabled' => app()->environment(['local', 'testing']) && config('pos.local_cash_movements_enabled') === true && $user->role === 'manager'];
        $branch = DB::table('pos_branches')->where('id', $user->branch_id)->firstOrFail();
        $refundData['tax_documents_enabled'] = app()->environment(['local', 'testing']) && config('pos.local_tax_documents_enabled') === true && in_array($user->role, ['cashier', 'manager', 'pharmacist'], true);
        $refundData['return_inspections_enabled'] = app()->environment(['local', 'testing']) && config('pos.local_return_inspections_enabled') === true && $user->role === 'manager' && $user->pharmacist_qualified && $branch->business_type === 'pharmacy' && $branch->active;
        $restaurantEnabled = app()->environment(['local', 'testing']) && config('pos.local_restaurant_enabled') === true && $branch->business_type === 'restaurant' && $branch->active;
        $tables = $restaurantEnabled ? DB::table('pos_restaurant_tables as t')->leftJoin('pos_table_sessions as s', fn ($join) => $join->on('s.table_id', '=', 't.id')->whereNotNull('s.active_slot'))->where('t.branch_id', $user->branch_id)->where('t.active', true)->orderBy('t.label')->get(['t.id', 't.label', 's.id as session_id']) : [];
        $kitchen = $restaurantEnabled ? DB::table('pos_kitchen_lines as k')->join('pos_kitchen_orders as o', 'o.id', '=', 'k.order_id')->where('o.branch_id', $user->branch_id)->whereNull('k.billed_sale_id')->whereNotIn('k.status', ['cancelled', 'wasted'])->orderBy('k.created_at')->orderBy('k.id')->limit(100)->get(['k.id', 'k.product_id', 'k.status', 'k.snapshot', 'o.id as order_id', 'o.catalog_version', 'o.channel', 'o.table_session_id'])->map(function ($line) {
            $product = json_decode($line->snapshot, true)['product'];

            return ['id' => $line->id, 'product_id' => $line->product_id, 'name' => $product['name'], 'unit' => $product['unit'], 'price' => $product['price'], 'status' => $line->status, 'order_id' => $line->order_id, 'catalog_version' => $line->catalog_version, 'channel' => $line->channel, 'table_session_id' => $line->table_session_id];
        }) : [];
        $restaurantData = ['restaurant_enabled' => (bool) $restaurantEnabled, 'restaurant_tables' => $tables, 'restaurant_lines' => $kitchen];
        $q = DB::table('pos_catalogs')->where('branch_id', $user->branch_id)->where('valid_until', '>', now());
        $catalog = ($branch->active_catalog_version ? $q->where('version', $branch->active_catalog_version) : $q->latest('created_at'))->first();
        $requests = in_array($user->role, ['cashier', 'manager'], true) ? DB::table('pos_customer_requests')->where('branch_id', $user->branch_id)->whereIn('status', ['pending', 'accepted'])->latest()->limit(50)->get()->map(fn ($row) => ['id' => $row->id, 'status' => $row->status, 'catalog_version' => $row->catalog_version, 'items' => json_decode($row->items, true)]) : [];

        return response()->json(['staff' => ['name' => $user->name, 'role' => $user->role], 'terminals' => DB::table('pos_terminals')->where('branch_id', $user->branch_id)->where('active', true)->get(['id', 'code']), 'catalog_version' => $catalog?->version, 'items' => $catalog ? json_decode($catalog->items, true) : [], 'shifts' => DB::table('pos_shifts')->where('staff_id', $user->id)->where('status', 'open')->get(['id', 'terminal_id']), 'pending_sync' => DB::table('pos_outbox')->where('branch_id', $user->branch_id)->where('result_status', '!=', 'accepted')->count(), 'tax_setup' => 'pending_provider', 'customer_requests' => $requests, 'public_menu_enabled' => app()->environment(['local', 'testing']) && config('pos.local_public_menu_enabled') === true] + $refundData + $discountData + $restaurantData);
    });
    Route::post('/shifts', function (Request $r, CheckoutService $s) {
        $d = $r->validate(['terminal_id' => 'required|uuid', 'opening_cash' => 'required|string|max:20']);

        return response()->json(['id' => $s->openShift($r->user(), $d['terminal_id'], $d['opening_cash'])]);
    });
    Route::post('/shifts/{id}/close', function (Request $r, string $id, CheckoutService $s) {
        $d = $r->validate(['counted_cash' => 'required|string|max:20']);

        return response()->json($s->closeShift($r->user(), $id, $d['counted_cash']));
    });
    Route::post('/checkout', fn (Request $r, CheckoutService $s) => response()->json($s->checkout($r->user(), $r->all())));
});
