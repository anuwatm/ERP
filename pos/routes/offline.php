<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Pos\Services\ManagerApproval;
use Pos\Services\OfflineIngressService;
use Pos\Services\OfflinePermitService;

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
});
