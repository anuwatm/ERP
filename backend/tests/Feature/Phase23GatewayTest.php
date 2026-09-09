<?php

namespace Tests\Feature;

use App\Http\Controllers\Finance\InvoiceController;
use App\Models\AccountingPeriod;
use App\Models\BankAccount;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Payment;
use App\Models\PaymentGatewayConfig;
use App\Models\Permission;
use App\Models\PortalSession;
use App\Models\PortalUser;
use App\Models\Role;
use App\Models\User;
use App\Services\BahtText;
use App\Services\FinancialJournalService;
use App\Services\GatewaySettlementService;
use App\Services\PromptPayQrService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class Phase23GatewayTest extends TestCase
{
    use RefreshDatabase;

    private function scenario(): array
    {
        $this->travelTo(now()->startOfSecond());
        $owner = User::factory()->create();
        $bank = BankAccount::create(['org_id' => $owner->org_id, 'bank_name' => 'Test bank', 'account_name' => 'Merchant', 'account_number' => '1234567890', 'account_number_hash' => hash('sha256', '1234567890'), 'account_type' => 'savings', 'currency' => 'THB', 'status' => 'active']);
        $config = PaymentGatewayConfig::create(['org_id' => $owner->org_id, 'enabled' => true, 'provider' => 'settlement_hmac', 'qr_type' => 'biller', 'recipient_id' => '010555123456701', 'webhook_secret' => str_repeat('s', 40), 'bank_account_id' => $bank->id]);
        $customer = Customer::create(['org_id' => $owner->org_id, 'owner_id' => $owner->id, 'customer_code' => 'GATE01', 'company_name' => 'Gateway customer', 'customer_type' => 'company', 'status' => 'active']);
        $invoice = Invoice::create(['org_id' => $owner->org_id, 'customer_id' => $customer->id, 'invoice_no' => 'INV-GW', 'status' => 'sent', 'tax_mode' => 'no_tax', 'issue_date' => today(), 'currency' => 'THB', 'base_currency' => 'THB', 'exchange_rate' => 1, 'subtotal' => 100, 'total' => 100, 'balance_due' => 100, 'base_subtotal' => 100, 'base_total' => 100, 'base_balance_due' => 100]);
        app(FinancialJournalService::class)->postInvoice($invoice, $owner->id);
        $tx = app(GatewaySettlementService::class)->intent($invoice);

        return [$config, $invoice, $tx];
    }

    private function sendEvent($config, array $body, ?string $signature = null, ?int $timestamp = null)
    {
        $raw = json_encode($body, JSON_THROW_ON_ERROR);
        $timestamp ??= now()->timestamp;

        return $this->call('POST', '/api/gateway/'.$config->id.'/settlement', [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_SETTLEMENT_TIMESTAMP' => (string) $timestamp,
            'HTTP_X_SETTLEMENT_SIGNATURE' => $signature ?? hash_hmac('sha256', $timestamp.'.'.$raw, $config->webhook_secret),
        ], $raw);
    }

    private function body($tx, string $event = 'evt-1'): array
    {
        return ['event_id' => $event, 'reference' => $tx->reference, 'status' => 'settled', 'amount_minor' => 10000, 'currency' => 'THB', 'settled_at' => now()->toIso8601String()];
    }

    public function test_qr_uses_bill_references_amount_crc_and_real_svg(): void
    {
        $qr = app(PromptPayQrService::class);
        $payload = $qr->payload('biller', '010555123456701', 12345, 'INV123', 'CUSTOMER1');
        $this->assertStringContainsString('A000000677010112', $payload);
        $this->assertStringContainsString('0206INV1230309CUSTOMER1', $payload);
        $this->assertStringContainsString('5406123.45', $payload);
        $this->assertMatchesRegularExpression('/6304[A-F0-9]{4}$/', $payload);
        $this->assertStringStartsWith('data:image/svg+xml;base64,', $qr->image($payload));
        $this->assertSame(12345, PromptPayQrService::minor('123.45'));
    }

    public function test_settlement_posts_once_and_duplicate_cannot_regress_paid_invoice(): void
    {
        [$config, $invoice, $tx] = $this->scenario();
        $this->sendEvent($config, $this->body($tx))->assertOk()->assertJson(['status' => 'processed']);
        $this->sendEvent($config, $this->body($tx))->assertOk()->assertJson(['duplicate' => true]);
        $this->sendEvent($config, [...$this->body($tx, 'evt-2'), 'status' => 'pending'])->assertOk()->assertJson(['status' => 'ignored']);
        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertDatabaseCount('payments', 1);
        $this->assertSame(1, JournalEntry::where('source_type', 'payment')->count());
        $this->assertSame('100.00', Payment::first()->amount);
    }

    public function test_invalid_signature_stale_timestamp_and_conflicting_replay_are_rejected(): void
    {
        [$config, $invoice, $tx] = $this->scenario();
        $this->sendEvent($config, $this->body($tx), 'invalid')->assertUnauthorized();
        $this->sendEvent($config, $this->body($tx), null, now()->timestamp - 301)->assertUnauthorized();
        $pending = [...$this->body($tx), 'status' => 'pending'];
        $this->sendEvent($config, $pending)->assertOk();
        $this->sendEvent($config, $this->body($tx))->assertConflict();
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_amount_mismatch_and_other_tenant_never_post(): void
    {
        [$config, $invoice, $tx] = $this->scenario();
        $this->sendEvent($config, [...$this->body($tx), 'amount_minor' => 10001])->assertOk()->assertJson(['status' => 'review']);
        $other = User::factory()->create();
        $config->update(['org_id' => $other->org_id]);
        $this->sendEvent($config, $this->body($tx, 'evt-2'))->assertOk()->assertJson(['status' => 'review']);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_closed_period_rolls_back_payment_and_allows_retry_after_reopening(): void
    {
        [$config, $invoice, $tx] = $this->scenario();
        $period = AccountingPeriod::create(['org_id' => $invoice->org_id, 'name' => 'Closed', 'start_date' => today()->startOfMonth(), 'end_date' => today()->endOfMonth(), 'status' => 'closed']);
        $this->sendEvent($config, $this->body($tx))->assertUnprocessable();
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('webhook_events', 0);
        $period->update(['status' => 'open']);
        $this->sendEvent($config, $this->body($tx))->assertOk()->assertJson(['status' => 'processed']);
    }

    public function test_expired_qr_and_overpayment_are_reviewed_without_posting(): void
    {
        [$config, $invoice, $tx] = $this->scenario();
        $invoice->update(['balance_due' => 50]);
        $this->sendEvent($config, $this->body($tx))->assertOk()->assertJson(['status' => 'review']);
        $invoice->update(['balance_due' => 100]);
        $this->travel(31)->minutes();
        $this->sendEvent($config, $this->body($tx, 'late'))->assertOk()->assertJson(['status' => 'review']);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_secrets_are_encrypted_and_qr_requires_party_or_staff_access(): void
    {
        [$config, $invoice, $tx] = $this->scenario();
        $this->assertNotSame($config->webhook_secret, $config->getRawOriginal('webhook_secret'));
        $this->assertArrayNotHasKey('webhook_secret', $config->toArray());
        $this->assertArrayNotHasKey('recipient_id', $config->toArray());
        $this->get(route('gateway.qr', $invoice))->assertNotFound();
        $other = User::factory()->create();
        $this->actingAs($other)->get(route('gateway.qr', $invoice))->assertNotFound();
        $config->update(['enabled' => false]);
        $this->assertNull(app(GatewaySettlementService::class)->intent($invoice));
        $this->sendEvent($config, $this->body($tx))->assertNotFound();
    }

    public function test_customer_qr_and_invoice_pdf_share_the_same_payment_reference(): void
    {
        [$config, $invoice, $tx] = $this->scenario();
        $user = PortalUser::create(['org_id' => $invoice->org_id, 'party_type' => 'customer', 'party_id' => $invoice->customer_id, 'email' => 'payer@example.test', 'is_active' => true]);
        $raw = str_repeat('p', 80);
        PortalSession::create(['portal_user_id' => $user->id, 'token_hash' => hash('sha256', $raw), 'expires_at' => now()->addHour()]);
        $this->withCookie('erp_portal_session', $raw)->get(route('gateway.qr', $invoice))->assertOk()->assertSee($tx->reference)->assertSee('data:image/svg+xml;base64,', false);
        $request = Request::create('/invoices/'.$invoice->id.'/print');
        $owner = User::where('org_id', $invoice->org_id)->firstOrFail();
        $request->setUserResolver(fn () => $owner);
        $controller = app(InvoiceController::class);
        $view = $controller->print($request, $invoice, app(BahtText::class));
        $this->assertStringContainsString($tx->reference, $view->render());
        $pdf = $controller->pdf($request, $invoice, app(BahtText::class));
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
        $this->assertDatabaseCount('gateway_transactions', 1);
    }

    public function test_settings_require_permission_preserve_secrets_and_block_receiver_changes(): void
    {
        $this->withoutVite();
        [$config, $invoice, $tx] = $this->scenario();
        $owner = User::where('org_id', $invoice->org_id)->firstOrFail();
        $this->actingAsOrgUser($owner)->get(route('gateway.settings'))->assertForbidden();
        $role = Role::create(['org_id' => $owner->org_id, 'code' => 'gateway_admin', 'name' => 'Gateway Admin']);
        foreach (['settings.organization.view', 'settings.organization.update'] as $code) {
            $permission = Permission::firstOrCreate(['code' => $code], ['module' => 'settings', 'action' => str($code)->afterLast('.')->toString(), 'description' => $code]);
            $role->permissions()->attach($permission);
        }
        $owner->roles()->attach($role);
        $owner->unsetRelation('roles');
        $this->actingAsOrgUser($owner)->get(route('gateway.settings'))->assertOk()->assertDontSee($config->webhook_secret);
        $data = ['enabled' => false, 'qr_type' => 'biller', 'bank_account_id' => $config->bank_account_id, 'recipient_id' => '', 'webhook_secret' => ''];
        $this->withSession(['auth.password_confirmed_at' => now()->timestamp])->put(route('gateway.configure'), $data)->assertRedirect();
        $this->assertFalse($config->fresh()->enabled);
        $this->assertSame(str_repeat('s', 40), $config->fresh()->webhook_secret);
        $this->put(route('gateway.configure'), [...$data, 'recipient_id' => '010555123456702'])->assertSessionHasErrors('recipient_id');
        $this->assertSame('010555123456701', $config->fresh()->recipient_id);
        foreach (['creating', 'creation_unknown'] as $status) {
            $tx->update(['status' => $status, 'expires_at' => now()->subHour()]);
            $this->put(route('gateway.configure'), [...$data, 'provider' => 'opn', 'provider_secret' => 'skey_test_fixture'])->assertSessionHasErrors('provider');
            $this->put(route('gateway.configure'), [...$data, 'recipient_id' => '010555123456702'])->assertSessionHasErrors('recipient_id');
        }
    }

    public function test_opn_retrieves_event_and_charge_but_waits_for_signed_settlement(): void
    {
        [$config, $invoice, $tx] = $this->scenario();
        $config->update(['provider' => 'opn', 'provider_secret' => 'skey_live_fixture', 'livemode' => true]);
        $event = ['id' => 'evnt_test_123', 'key' => 'charge.complete', 'livemode' => true, 'data' => ['id' => 'chrg_test_123']];
        $charge = ['id' => 'chrg_test_123', 'livemode' => true, 'status' => 'successful', 'paid' => true, 'paid_at' => now()->setTimezone('Asia/Bangkok')->toIso8601String(), 'amount' => 10000, 'currency' => 'thb', 'source' => ['type' => 'promptpay'], 'metadata' => ['erp_reference' => $tx->reference], 'refunded_amount' => 0];
        Http::preventStrayRequests();
        Http::fake([
            'https://api.omise.co/events/evnt_test_123' => Http::response($event),
            'https://api.omise.co/charges/chrg_test_123' => Http::response($charge),
        ]);
        $this->postJson(route('gateway.opn', $config->id), ['id' => 'evnt_test_123', 'status' => 'settled'])->assertOk()->assertJson(['status' => 'provider_confirmed']);
        $this->assertDatabaseCount('payments', 0);
        $this->sendEvent($config, $this->body($tx))->assertOk()->assertJson(['status' => 'review']);
        $this->travel(2)->days();
        $this->sendEvent($config, [...$this->body($tx, 'verified-settlement'), 'provider_charge_id' => 'chrg_test_123'])->assertOk()->assertJson(['status' => 'processed']);
        $this->assertSame('paid', $invoice->fresh()->status);
    }

    public function test_opn_verification_failure_and_missing_fx_snapshot_never_post(): void
    {
        [$config, $invoice, $tx] = $this->scenario();
        $config->update(['provider' => 'opn', 'provider_secret' => 'skey_test_fixture']);
        Http::fake(['*' => Http::response([], 503)]);
        $this->postJson(route('gateway.opn', $config->id), ['id' => 'evnt_test_123'])->assertStatus(503);
        $this->assertDatabaseCount('webhook_events', 0);
        $config->update(['provider' => 'settlement_hmac']);
        $invoice->update(['base_balance_due' => 0]);
        $this->sendEvent($config, $this->body($tx))->assertOk()->assertJson(['status' => 'review']);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_opn_test_mode_cannot_post_even_with_confirmed_charge_and_signed_settlement(): void
    {
        [$config, $invoice, $tx] = $this->scenario();
        $config->update(['provider' => 'opn', 'livemode' => false]);
        $tx->update(['provider_charge_id' => 'chrg_test_sandbox', 'provider_confirmed_at' => now(), 'provider_paid_at' => now()]);
        $this->sendEvent($config, [...$this->body($tx), 'provider_charge_id' => $tx->provider_charge_id])->assertOk()->assertJson(['status' => 'review']);
        $this->assertDatabaseCount('payments', 0);
        $this->assertSame(0, JournalEntry::where('source_type', 'payment')->count());
        $this->assertSame('sent', $invoice->fresh()->status);
    }

    public function test_opn_checkout_uses_provider_qr_and_does_not_create_duplicate_charges(): void
    {
        [$config, $invoice, $old] = $this->scenario();
        $old->delete();
        $config->update(['provider' => 'opn', 'provider_secret' => 'skey_test_fixture', 'livemode' => false]);
        $imageUrl = 'https://api.omise.co/charges/chrg_test_checkout/documents/docu_test_qr/downloads/ABC123';
        Http::preventStrayRequests();
        Http::fake([
            'https://api.omise.co/charges' => fn ($request) => Http::response(['id' => 'chrg_test_checkout', 'livemode' => false, 'amount' => 10000, 'currency' => 'THB', 'metadata' => $request['metadata'], 'source' => ['scannable_code' => ['image' => ['download_uri' => $imageUrl]]]]),
            $imageUrl => Http::response('<svg xmlns="http://www.w3.org/2000/svg"><rect width="10" height="10"/></svg>', 200, ['Content-Type' => 'image/svg+xml']),
        ]);
        $service = app(GatewaySettlementService::class);
        $tx = $service->intent($invoice);
        $this->assertNotNull($tx);
        $this->assertSame('chrg_test_checkout', $tx->provider_charge_id);
        $this->assertStringStartsWith('data:image/svg+xml;base64,', $tx->provider_qr_image);
        $this->assertSame($tx->id, $service->intent($invoice)->id);
        Http::assertSentCount(2);
    }

    public function test_ambiguous_provider_create_is_not_blindly_retried(): void
    {
        [$config, $invoice, $old] = $this->scenario();
        $old->delete();
        $config->update(['provider' => 'opn', 'provider_secret' => 'skey_test_fixture']);
        Http::fake(['*' => Http::response([], 503)]);
        $service = app(GatewaySettlementService::class);
        $this->assertNull($service->intent($invoice));
        $this->assertNull($service->intent($invoice));
        $this->assertDatabaseHas('gateway_transactions', ['invoice_id' => $invoice->id, 'status' => 'creation_unknown']);
        Http::assertSentCount(1);
    }
}
