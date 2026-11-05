<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Pos\Services\ManagerApproval;
use Pos\Services\OfflineIngressService;
use Pos\Services\OfflinePermitService;
use Pos\Services\OfflineReconciliationService;

Route::get('/offline/app', function () {
    abort_unless(app()->environment(['local', 'testing']) && config('pos.local_browser_offline_enabled') === true, 503);

    return response()->view('offline')->header('Cache-Control', 'public, max-age=0');
});

Route::middleware(['pos.staff', 'throttle:30,1'])->prefix('offline')->group(function () {
    $access = function (Request $r): void {
        abort_unless(in_array($r->ip(), ['127.0.0.1', '::1'], true), 403);
        abort_unless(app()->environment(['local', 'testing']) && config('pos.local_browser_offline_enabled') === true, 503, 'Browser offline capability disabled.');
    };
    Route::get('/context', function (Request $r, OfflinePermitService $service) use ($access) {
        $access($r);

        return response()->json(['instance_id' => config('pos.instance_id'), 'branch_id' => $r->user()->branch_id, 'staff_id' => $r->user()->id, 'server_now' => now()->utc()->toIso8601String(), 'policy' => $service->current($r->user()), 'offline_sale_authorized' => false])->header('Cache-Control', 'private, no-store');
    });
    Route::post('/devices', function (Request $r, OfflineIngressService $service) use ($access) {
        $access($r);

        return response()->json($service->register($r->user(), $r->all()))->header('Cache-Control', 'private, no-store');
    });
    Route::post('/policies', function (Request $r, OfflinePermitService $service) use ($access) {
        $access($r);
        $d = $r->validate(['manager_password' => 'required|string|max:1000']);
        $command = $r->except('manager_password');
        ManagerApproval::verify($r->user(), $d['manager_password'], $command)->assertFor($r->user(), $command);

        return response()->json($service->configure($r->user(), $command))->header('Cache-Control', 'private, no-store');
    });
    Route::post('/permits', function (Request $r, OfflinePermitService $service) use ($access) {
        $access($r);

        return response()->json($service->issue($r->user(), $r->all()))->header('Cache-Control', 'private, no-store');
    });
    Route::post('/inbox', function (Request $r, OfflineIngressService $service) use ($access) {
        $access($r);

        return response()->json($service->receive($r->user(), $r->all()))->header('Cache-Control', 'private, no-store');
    });
    Route::get('/session', function (Request $r, OfflinePermitService $service) use ($access) {
        $access($r);

        return response()->json(['csrf_token' => csrf_token(), 'instance_id' => config('pos.instance_id'), 'branch_id' => $r->user()->branch_id, 'staff_id' => $r->user()->id, 'role' => $r->user()->role, 'server_now' => now()->utc()->toIso8601String(), 'policy' => $service->current($r->user()), 'terminals' => DB::table('pos_terminals')->where('branch_id', $r->user()->branch_id)->where('active', true)->get(['id', 'code'])])->header('Cache-Control', 'private, no-store');
    });
    Route::get('/review', function (Request $r, OfflinePermitService $service) use ($access) {
        $access($r);
        $service->current($r->user());
        abort_unless($r->user()->role === 'manager', 403);
        $cursor = $r->validate(['cursor_at' => 'sometimes|required_with:cursor_id|date_format:Y-m-d H:i:s', 'cursor_id' => 'sometimes|required_with:cursor_at|uuid']);
        $rows = DB::table('pos_offline_inbox')->where('branch_id', $r->user()->branch_id)->where('status', 'review')
            ->when(isset($cursor['cursor_id']), fn ($q) => $q->where(fn ($q) => $q->where('server_received_at', '>', $cursor['cursor_at'])->orWhere(fn ($q) => $q->where('server_received_at', $cursor['cursor_at'])->where('id', '>', $cursor['cursor_id']))))
            ->orderBy('server_received_at')->orderBy('id')->limit(100)->get();
        DB::table('pos_audit')->insert(['actor_id' => $r->user()->id, 'branch_id' => $r->user()->branch_id, 'action' => 'offline.review.read', 'source_id' => $r->user()->branch_id, 'metadata' => '{}', 'created_at' => now()]);

        return response()->json($rows->map(function ($row) {
            $e = json_decode(Crypt::decryptString($row->payload), true, flags: JSON_THROW_ON_ERROR);

            return ['id' => $row->id, 'payload_hash' => $row->payload_hash, 'kind' => $row->kind, 'reason_code' => $row->reason_code, 'server_received_at' => $row->server_received_at, 'evidence' => $e];
        }))->header('Cache-Control', 'private, no-store');
    });
    Route::post('/accept', function (Request $r, OfflineReconciliationService $service) use ($access) {
        $access($r);
        $d = $r->validate(['manager_password' => 'required|string|max:1000']);
        $command = $r->except('manager_password');
        ManagerApproval::verify($r->user(), $d['manager_password'], $command)->assertFor($r->user(), $command);

        return response()->json($service->accept($r->user(), $command))->header('Cache-Control', 'private, no-store');
    });
    Route::post('/close-permit', function (Request $r, OfflineReconciliationService $service) use ($access) {
        $access($r);
        $d = $r->validate(['manager_password' => 'required|string|max:1000']);
        $command = $r->except('manager_password');
        ManagerApproval::verify($r->user(), $d['manager_password'], $command)->assertFor($r->user(), $command);

        return response()->json($service->close($r->user(), $command))->header('Cache-Control', 'private, no-store');
    });
    Route::get('/permits', function (Request $r, OfflinePermitService $service) use ($access) {
        $access($r);
        $service->current($r->user());
        abort_unless($r->user()->role === 'manager', 403);

        return response()->json(DB::table('pos_offline_permits')->where('branch_id', $r->user()->branch_id)->where('status', 'active')->orderBy('issued_at')->limit(100)->get(['id', 'device_id', 'staff_id', 'terminal_id', 'expires_at'])->map(function ($permit) {
            $permit->accepted_transactions = DB::table('pos_offline_slots')->where('permit_id', $permit->id)->where('status', 'consumed')->pluck('client_transaction_id');

            return $permit;
        }))->header('Cache-Control', 'private, no-store');
    });
});
