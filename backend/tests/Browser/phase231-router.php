<?php

use App\Http\Controllers\Finance\InvoiceController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

$fixtureDatabase = require __DIR__.'/phase231-environment.php';
$public = realpath(dirname(__DIR__, 2).'/public');
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = realpath($public.'/'.ltrim(rawurldecode($path), '/'));
if ($path !== '/' && $file && str_starts_with($file, $public.DIRECTORY_SEPARATOR) && is_file($file) && pathinfo($file, PATHINFO_EXTENSION) !== 'php') {
    return false;
}
define('LARAVEL_START', microtime(true));
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->useEnvironmentPath(dirname($fixtureDatabase));
$app->booted(function () {
    Route::middleware(['web', 'auth'])->group(function () {
        Route::get('/phase231/standard', fn () => Inertia::render('Testing/DialogStandard'));
        Route::post('/phase231/standard/preview', function (Request $request) {
            $data = $request->validate(['tax_mode' => 'required|in:exclusive,inclusive,no_tax', 'discount_amount' => 'required|numeric|min:0', 'items' => 'required|array|min:1', 'items.*.quantity' => 'required|numeric|min:0', 'items.*.unit_price' => 'required|numeric|min:0', 'items.*.discount_amount' => 'required|numeric|min:0', 'items.*.tax_rate' => 'required|numeric|min:0']);
            $method = new ReflectionMethod(InvoiceController::class, 'taxSummaryFromLines');

            return response()->json($method->invoke(app(InvoiceController::class), $data['tax_mode'], $data['discount_amount'], $data['items']));
        });
        Route::post('/phase231/standard/validate', function (Request $request) {
            $request->validate(['title' => 'required|string|max:255', 'items' => 'required|array|min:1', 'items.*.description' => 'required|string', 'items.*.quantity' => 'required|numeric|min:0.01', 'attachment' => 'nullable|file|max:5120']);

            return back()->with('success', 'Fixture validation passed; no business record created.');
        });
    });
});
$app->handleRequest(Request::capture());
