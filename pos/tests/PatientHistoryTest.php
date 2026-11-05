<?php

namespace Pos\Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\RecordNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Pos\Models\Staff;
use Pos\Services\OfflineIngressService;
use Pos\Services\PatientHistoryService;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class PatientHistoryTest extends TestCase
{
    use RefreshDatabase;

    private Staff $staff;

    public function createApplication(): Application
    {
        $app = require dirname(__DIR__).'/bootstrap/app.php';
        $this->traitsUsedByTest = class_uses_recursive(static::class);
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $branch = (string) Str::uuid();
        DB::table('pos_branches')->insert(['id' => $branch, 'code' => 'PATIENTS', 'name' => 'Synthetic pharmacy', 'business_type' => 'pharmacy']);
        $this->staff = Staff::create(['id' => (string) Str::uuid(), 'branch_id' => $branch, 'name' => 'Synthetic clinician', 'email' => 'clinical@fixture.test', 'password' => 'synthetic-password', 'role' => 'pharmacist', 'active' => true]);
        config(['pos.instance_id' => (string) Str::uuid(), 'pos.local_pharmacy_enabled' => true, 'pos.local_patient_history_enabled' => true]);
    }

    private function command(): array
    {
        return ['request_key' => (string) Str::uuid(), 'expected_version' => 0, 'synthetic_confirmed' => true, 'profile' => ['name' => 'Synthetic Patient Secret', 'birth_date' => '2000-01-02', 'phone' => '+66812345678', 'allergies' => 'SYNTHETIC ALLERGY SECRET']];
    }

    private function deny(callable $action, int $status): void
    {
        try {
            $action();
            $this->fail('Unauthorized patient action succeeded.');
        } catch (HttpExceptionInterface $e) {
            $this->assertSame($status, $e->getStatusCode());
        } catch (ModelNotFoundException|RecordNotFoundException $e) {
            $this->assertSame(404, $status);
        } catch (ValidationException $e) {
            $this->assertSame($status, $e->status);
        }
    }

    public function test_profiles_are_encrypted_versioned_idempotent_and_not_written_to_audit_or_outbox(): void
    {
        $s = app(PatientHistoryService::class);
        $d = $this->command();
        $first = $s->save($this->staff, $d);
        $this->assertSame($first, $s->save($this->staff, $d));
        $changed = $d;
        $changed['profile']['phone'] = '+66899999999';
        $this->deny(fn () => $s->save($this->staff, $changed), 409);
        $stale = $this->command();
        $this->deny(fn () => $s->save($this->staff, $stale, $first['id']), 409);
        $changed['request_key'] = (string) Str::uuid();
        $changed['expected_version'] = 1;
        $second = $s->save($this->staff, $changed, $first['id']);
        $this->assertSame(2, $second['version']);
        $this->assertSame('+66899999999', $s->detail($this->staff, $first['id'])['profile']['phone']);
        $this->assertDatabaseCount('pos_patients', 1);
        $this->assertDatabaseCount('pos_patient_revisions', 2);
        $cipher = DB::table('pos_patients')->value('payload');
        $this->assertStringNotContainsString('Synthetic Patient Secret', $cipher);
        $this->assertSame('Synthetic Patient Secret', json_decode(Crypt::decryptString($cipher), true)['name']);
        $history = $s->timeline($this->staff, $first['id'], 'revisions');
        $this->assertSame([1, 2], array_column($history['items'], 'version'));
        $audit = json_encode(DB::table('pos_audit')->get());
        foreach (['Synthetic Patient Secret', '+66812345678', 'SYNTHETIC ALLERGY SECRET'] as $secret) {
            $this->assertStringNotContainsString($secret, $audit);
        }
        $this->assertDatabaseCount('pos_outbox', 0);
    }

    public function test_cashier_and_unqualified_manager_only_receive_masked_selection_and_qualified_manager_can_read(): void
    {
        $s = app(PatientHistoryService::class);
        $id = $s->save($this->staff, $this->command())['id'];
        $this->staff->update(['role' => 'cashier']);
        $options = $s->options($this->staff);
        $this->assertFalse($options['clinical_access']);
        $this->assertSame(['id', 'code', 'name_masked'], array_keys($options['items'][0]));
        $this->assertSame('S***', $options['items'][0]['name_masked']);
        $this->deny(fn () => $s->detail($this->staff, $id), 403);
        $this->deny(fn () => $s->save($this->staff, $this->command()), 403);
        $this->deny(fn () => $s->timeline($this->staff, $id, 'revisions'), 403);
        $this->staff->update(['role' => 'manager']);
        $this->deny(fn () => $s->detail($this->staff, $id), 403);
        DB::table('pos_staff')->where('id', $this->staff->id)->update(['pharmacist_qualified' => true]);
        $this->staff->refresh();
        $this->assertTrue($s->options($this->staff)['clinical_access']);
        $this->assertSame($id, $s->detail($this->staff, $id)['id']);
    }

    public function test_branch_business_and_feature_scope_cannot_be_bypassed(): void
    {
        $s = app(PatientHistoryService::class);
        $id = $s->save($this->staff, $this->command())['id'];
        $original = $this->staff->branch_id;
        $other = (string) Str::uuid();
        DB::table('pos_branches')->insert(['id' => $other, 'code' => 'OTHER', 'name' => 'Other', 'business_type' => 'pharmacy']);
        $this->staff->update(['branch_id' => $other]);
        $this->deny(fn () => $s->detail($this->staff, $id), 404);
        $this->assertSame([], $s->options($this->staff)['items']);
        foreach (['retail', 'restaurant'] as $type) {
            DB::table('pos_branches')->where('id', $other)->update(['business_type' => $type]);
            $this->deny(fn () => $s->options($this->staff), 404);
        }
        $this->staff->update(['branch_id' => $original]);
        config(['pos.local_patient_history_enabled' => false]);
        $this->deny(fn () => $s->detail($this->staff, $id), 503);
    }

    public function test_visits_correct_by_append_preserve_original_and_replay_once(): void
    {
        $s = app(PatientHistoryService::class);
        $id = $s->save($this->staff, $this->command())['id'];
        $d = ['request_key' => (string) Str::uuid(), 'synthetic_confirmed' => true, 'occurred_on' => '2026-01-01', 'note' => 'SYNTHETIC VISIT SECRET', 'supersedes_id' => null, 'correction_reason' => null];
        $first = $s->visit($this->staff, $id, $d);
        $this->assertSame($first, $s->visit($this->staff, $id, $d));
        $correction = $d;
        $correction['request_key'] = (string) Str::uuid();
        $correction['note'] = 'SYNTHETIC CORRECTION';
        $correction['supersedes_id'] = $first['id'];
        $correction['correction_reason'] = 'Synthetic correction evidence';
        $s->visit($this->staff, $id, $correction);
        $correction['request_key'] = (string) Str::uuid();
        $this->deny(fn () => $s->visit($this->staff, $id, $correction), 409);
        $this->assertDatabaseCount('pos_patient_visits', 2);
        $timeline = $s->timeline($this->staff, $id, 'visits');
        $this->assertSame('SYNTHETIC VISIT SECRET', $timeline['items'][0]['data']['note']);
        $this->assertStringNotContainsString('SYNTHETIC VISIT SECRET', json_encode(DB::table('pos_patient_visits')->get()));
        $this->assertStringNotContainsString('SYNTHETIC VISIT SECRET', json_encode(DB::table('pos_audit')->get()));
        $bad = $d;
        $bad['request_key'] = (string) Str::uuid();
        $bad['synthetic_confirmed'] = false;
        $this->deny(fn () => $s->visit($this->staff, $id, $bad), 422);
    }

    public function test_http_no_store_session_deadline_and_non_json_requests_never_flash_health_input(): void
    {
        $this->actingAs($this->staff)->withSession(['pos_last_seen' => time(), 'pos_login_at' => time()]);
        $response = $this->postJson('/patients', $this->command())->assertOk();
        $id = $response->json('id');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertGreaterThan(0, (int) $response->headers->get('X-POS-Session-Remaining'));
        $this->post('/patients', $this->command())->assertStatus(406)->assertSessionMissing('_old_input');
        $this->staff->update(['role' => 'cashier']);
        $this->actingAs($this->staff)->getJson('/patients/'.$id)->assertForbidden();
        $this->getJson('/patients/options')->assertOk()->assertJsonPath('items.0.name_masked', 'S***')->assertJsonPath('clinical_access', false);
        $this->withSession(['pos_last_seen' => time() - 901, 'pos_login_at' => time()])->getJson('/patients/options')->assertUnauthorized();
    }

    public function test_search_is_clinical_exact_match_with_branch_keyed_indexes_and_no_plaintext_audit(): void
    {
        $s = app(PatientHistoryService::class);
        $d = $this->command();
        $patient = $s->save($this->staff, $d);
        $match = $s->search($this->staff, ['field' => 'name', 'term' => '  synthetic   patient SECRET  ']);
        $this->assertSame($patient['id'], $match['items'][0]['id']);
        $this->assertSame('S***', $match['items'][0]['name_masked']);
        $this->assertSame($patient['id'], $s->search($this->staff, ['field' => 'phone', 'term' => '+66 81-234-5678'])['items'][0]['id']);
        $this->assertSame([], $s->search($this->staff, ['field' => 'name', 'term' => 'Synthetic'])['items']);
        $d['request_key'] = (string) Str::uuid();
        $d['expected_version'] = 1;
        $d['profile']['phone'] = '+66899999999';
        $s->save($this->staff, $d, $patient['id']);
        $this->assertSame([], $s->search($this->staff, ['field' => 'phone', 'term' => '+66812345678'])['items']);
        $this->assertSame($patient['id'], $s->search($this->staff, ['field' => 'phone', 'term' => '+66899999999'])['items'][0]['id']);
        $this->staff->update(['role' => 'cashier']);
        $this->deny(fn () => $s->search($this->staff, ['field' => 'name', 'term' => 'Synthetic Patient Secret']), 403);
        $raw = json_encode(DB::table('pos_patients')->get());
        $audit = json_encode(DB::table('pos_audit')->get());
        foreach (['Synthetic Patient Secret', '+66899999999', 'SYNTHETIC ALLERGY SECRET'] as $secret) {
            $this->assertStringNotContainsString($secret, $raw);
            $this->assertStringNotContainsString($secret, $audit);
        }
    }

    public function test_patient_references_cannot_enter_offline_inbox(): void
    {
        config(['pos.local_browser_offline_enabled' => true]);
        $terminal = (string) Str::uuid();
        $device = (string) Str::uuid();
        DB::table('pos_terminals')->insert(['id' => $terminal, 'branch_id' => $this->staff->branch_id, 'code' => 'P1']);
        $s = app(OfflineIngressService::class);
        $s->register($this->staff, ['device_id' => $device, 'terminal_id' => $terminal, 'label' => 'Synthetic patient boundary']);
        $e = ['device_id' => $device, 'client_transaction_id' => (string) Str::uuid(), 'kind' => 'sale.draft', 'client_created_at' => 'untrusted', 'data' => ['nested' => ['patient_id' => (string) Str::uuid()]]];
        $this->deny(fn () => $s->receive($this->staff, $e), 422);
        $this->assertDatabaseCount('pos_offline_inbox', 0);
    }

    public function test_unknown_allergies_are_preserved_and_national_id_is_rejected(): void
    {
        $s = app(PatientHistoryService::class);
        $d = $this->command();
        $d['profile']['allergies'] = null;
        $this->travelTo(Carbon::parse('2026-11-04 18:00:00', 'UTC'));
        $d['profile']['birth_date'] = '2026-11-05';
        $id = $s->save($this->staff, $d)['id'];
        $profile = $s->detail($this->staff, $id)['profile'];
        $this->assertSame('', $profile['allergies']);
        $this->assertArrayNotHasKey('allergy_cleared', $profile);
        $bad = $this->command();
        $bad['profile']['national_id'] = '0000000000000';
        $this->deny(fn () => $s->save($this->staff, $bad), 422);
        $this->assertDatabaseCount('pos_patients', 1);
    }
}
