<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Pos\Http\PatientHistoryAccess;
use Pos\Services\PatientHistoryScope;
use Pos\Services\PatientHistoryService;

Route::middleware(['pos.staff', PatientHistoryAccess::class, 'throttle:60,1'])->prefix('patients')->group(function () {
    $access = function (Request $r, bool $json = true): void {
        abort_unless(in_array($r->ip(), ['127.0.0.1', '::1'], true), 403);
        if ($json) {
            abort_unless($r->expectsJson(), 406, 'JSON requests required.');
        }
    };
    Route::get('/', function (Request $r, PatientHistoryScope $scope) use ($access) {
        $access($r, false);
        DB::transaction(fn () => $scope->lock($r->user()));

        return response()->view('patients')->header('Cache-Control', 'private, no-store')->header('X-Content-Type-Options', 'nosniff');
    });
    Route::get('/options', function (Request $r, PatientHistoryService $service) use ($access) {
        $access($r);
        $d = $r->validate(['after' => 'sometimes|uuid']);

        return response()->json($service->options($r->user(), $d['after'] ?? null))->header('Cache-Control', 'private, no-store');
    });
    Route::post('/', function (Request $r, PatientHistoryService $service) use ($access) {
        $access($r);

        return response()->json($service->save($r->user(), $r->all()))->header('Cache-Control', 'private, no-store');
    });
    Route::post('/search', function (Request $r, PatientHistoryService $service) use ($access) {
        $access($r);

        return response()->json($service->search($r->user(), $r->all()))->header('Cache-Control', 'private, no-store');
    });
    Route::get('/{id}', function (Request $r, string $id, PatientHistoryService $service) use ($access) {
        $access($r);

        return response()->json($service->detail($r->user(), $id))->header('Cache-Control', 'private, no-store');
    });
    Route::post('/{id}', function (Request $r, string $id, PatientHistoryService $service) use ($access) {
        $access($r);

        return response()->json($service->save($r->user(), $r->all(), $id))->header('Cache-Control', 'private, no-store');
    });
    Route::get('/{id}/{kind}', function (Request $r, string $id, string $kind, PatientHistoryService $service) use ($access) {
        $access($r);
        $d = $r->validate(['after' => 'sometimes|uuid']);

        return response()->json($service->timeline($r->user(), $id, $kind, $d['after'] ?? null))->header('Cache-Control', 'private, no-store');
    });
    Route::post('/{id}/visits', function (Request $r, string $id, PatientHistoryService $service) use ($access) {
        $access($r);

        return response()->json($service->visit($r->user(), $id, $r->all()))->header('Cache-Control', 'private, no-store');
    });
});
