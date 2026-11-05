<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Pos\Support\CanonicalJson;

$database = require __DIR__.'/patient-environment.php';
require dirname(__DIR__, 2).'/bootstrap/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->useEnvironmentPath(dirname($database));
$app->make(Kernel::class)->bootstrap();
if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== $database) {
    throw new RuntimeException('Isolated SQLite required');
}
$expected = ['pos_patients' => 1, 'pos_patient_revisions' => 2, 'pos_patient_visits' => 2, 'pos_patient_dispensing' => 1, 'pos_orders' => 1, 'pos_payments' => 1, 'pos_outbox' => 1, 'pos_offline_inbox' => 0];
foreach ($expected as $table => $count) {
    if (DB::table($table)->count() !== $count) {
        throw new RuntimeException('Patient acceptance counter mismatch: '.$table);
    }
}
$patient = DB::table('pos_patients')->first();
$profile = json_decode(Crypt::decryptString($patient->payload), true, flags: JSON_THROW_ON_ERROR);
if ($profile['name'] !== 'SYNTHETIC-PATIENT-B-SECRET' || (int) $patient->version !== 2) {
    throw new RuntimeException('Unexpected current patient revision');
}
$protected = json_encode([DB::table('pos_patients')->get(), DB::table('pos_patient_revisions')->get(), DB::table('pos_patient_visits')->get(), DB::table('pos_audit')->get()]);
foreach (['SYNTHETIC-PATIENT-A-SECRET', 'SYNTHETIC-PATIENT-B-SECRET', '+66812345678', 'SYNTHETIC-ALLERGY-SECRET', 'SYNTHETIC-VISIT-ORIGINAL-SECRET', 'SYNTHETIC-VISIT-CORRECTION-SECRET'] as $secret) {
    if (str_contains($protected, $secret)) {
        throw new RuntimeException('Plaintext clinical data found outside encryption boundary');
    }
}
$event = DB::table('pos_outbox')->value('envelope');
$saleId = json_decode($event, true)['aggregate_id'];
if (str_contains(json_encode(DB::table('pos_patient_dispensing')->get()), $saleId) || str_contains(json_encode(DB::table('pos_audit')->where('action', 'like', 'patient.%')->get()), $saleId)) {
    throw new RuntimeException('Plaintext clinical bill reference escaped encryption');
}
foreach ([$patient->id, $profile['name'], $profile['phone'], $profile['allergies'], 'patient_id'] as $secret) {
    if (str_contains($event, $secret)) {
        throw new RuntimeException('Patient data escaped into financial event');
    }
}
$envelope = json_decode($event, true, flags: JSON_THROW_ON_ERROR);
$signature = base64_decode($envelope['signature']);
unset($envelope['signature']);
$key = openssl_pkey_get_private(file_get_contents(dirname($database).'/synthetic-private.pem'));
$public = openssl_pkey_get_details($key)['key'];
if (openssl_verify(CanonicalJson::encode($envelope), $signature, $public, OPENSSL_ALGO_SHA256) !== 1) {
    throw new RuntimeException('Financial event signature failed');
}
$report = ['result' => 'PASS', 'counts' => $expected, 'current_version' => 2, 'clinical_fields_encrypted' => true, 'audit_contains_no_plaintext_health_data' => true, 'financial_event_contains_no_patient_identity' => true, 'financial_event_signature_valid' => true, 'clinical_bill_reference_encrypted' => true, 'offline_inbox_empty' => true, 'scope' => 'Synthetic only; no configuredDB or real health data'];
file_put_contents(dirname($database).'/server-report.json', json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
