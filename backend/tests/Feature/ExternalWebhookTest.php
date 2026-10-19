<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\IntegrationApiKey;
use App\Models\IntegrationWebhook;
use App\Models\IntegrationWebhookDelivery;
use App\Models\IntegrationWebhookEvent;
use App\Models\Invoice;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use App\Services\ExternalWebhookService;
use App\Services\WebhookDestinationResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ExternalWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(now()->setDate(2026, 10, 19)->setTime(5, 0));
        config(['external_api.webhooks_enabled' => true]);
        Http::preventStrayRequests();
        $this->mock(WebhookDestinationResolver::class, fn ($mock) => $mock->shouldReceive('resolve')->andReturn([
            'host' => 'receiver.example.test', 'ip' => '8.8.8.8', 'url' => 'https://receiver.example.test/hook',
        ]));
    }

    private function fixture(): array
    {
        $user = User::factory()->create();
        $role = Role::create(['org_id' => $user->org_id, 'code' => 'admin', 'name' => 'Admin']);
        foreach (['api_keys.view', 'api_keys.manage', 'tasks.view'] as $code) {
            $permission = Permission::firstOrCreate(['code' => $code], ['module' => 'fixture', 'action' => 'view']);
            $role->permissions()->attach($permission);
        }
        $user->roles()->attach($role);
        $key = IntegrationApiKey::create(['org_id' => $user->org_id, 'name' => 'Fixture', 'created_by' => $user->id,
            'prefix' => 'erp_'.bin2hex(random_bytes(12)), 'secret_hash' => hash('sha256', 'unused fixture key'),
            'scopes' => ['tasks:read'], 'expires_at' => now()->addDays(30)]);
        $endpoint = IntegrationWebhook::create(['org_id' => $user->org_id, 'integration_api_key_id' => $key->id,
            'name' => 'Fixture receiver', 'url' => 'https://receiver.example.test/hook', 'signing_secret' => str_repeat('a', 64),
            'events' => ['task.status.changed'], 'status' => 'active', 'verified_at' => now(), 'created_by' => $user->id]);

        return [$user, $key, $endpoint];
    }

    public function test_outbox_and_business_save_rollback_together_and_signing_secret_is_encrypted(): void
    {
        [$user, $key, $endpoint] = $this->fixture();
        try {
            DB::transaction(function () use ($user) {
                Task::create(['org_id' => $user->org_id, 'title' => 'Rolled back']);
                $this->assertSame(1, IntegrationWebhookEvent::count());
                throw new \RuntimeException('intentional rollback');
            });
        } catch (\RuntimeException $error) {
            $this->assertSame('intentional rollback', $error->getMessage());
        }
        $this->assertSame(0, Task::count());
        $this->assertSame(0, IntegrationWebhookEvent::count());
        $this->assertSame(0, IntegrationWebhookDelivery::count());
        $this->assertNotSame($endpoint->signing_secret, DB::table('integration_webhooks')->value('signing_secret'));
        $this->assertArrayNotHasKey('signing_secret', $endpoint->toArray());
        Http::assertNothingSent();
    }

    public function test_delivery_signs_exact_bytes_retries_same_event_and_honors_retry_after(): void
    {
        [$user, $key, $endpoint] = $this->fixture();
        $task = Task::create(['org_id' => $user->org_id, 'title' => 'PRIVATE_TASK_NAME']);
        $bodies = [];
        Http::fake(function ($request, $options) use (&$bodies, $endpoint) {
            $this->assertFalse($options['allow_redirects']);
            $this->assertTrue($options['verify']);
            $this->assertSame(['receiver.example.test:443:8.8.8.8'], $options['curl'][CURLOPT_RESOLVE]);
            $this->assertSame('', $options['curl'][CURLOPT_PROXY]);
            $bodies[] = $request->body();
            $signature = $request->header('X-ERP-Signature')[0];
            $this->assertSame('t='.now()->timestamp.',v1='.hash_hmac('sha256', now()->timestamp.'.'.$request->body(), $endpoint->signing_secret), $signature);
            $this->assertStringNotContainsString('PRIVATE_TASK_NAME', $request->body());

            return count($bodies) === 1 ? Http::response('PRIVATE_RESPONSE', 429, ['Retry-After' => '120']) : Http::response('', 204);
        });
        $service = app(ExternalWebhookService::class);
        $this->assertSame(1, $service->dispatch());
        $delivery = IntegrationWebhookDelivery::firstOrFail();
        $this->assertSame('retry', $delivery->status);
        $this->assertSame(120, (int) now()->diffInSeconds($delivery->available_at));
        $this->assertSame(0, $service->dispatch());
        $this->travel(121)->seconds();
        $service->dispatch();
        $this->assertSame('delivered', $delivery->fresh()->status);
        $this->assertSame(2, $delivery->fresh()->attempts);
        $this->assertSame($bodies[0], $bodies[1]);
        $this->assertStringNotContainsString('PRIVATE_RESPONSE', $delivery->fresh()->toJson());
    }

    public function test_revoke_or_lost_row_visibility_stops_delivery(): void
    {
        [$user, $key] = $this->fixture();
        Task::create(['org_id' => $user->org_id, 'title' => 'Queued']);
        $key->update(['revoked_at' => now()]);
        Http::fake();
        app(ExternalWebhookService::class)->dispatch();
        $this->assertSame('skipped_no_access', IntegrationWebhookDelivery::firstOrFail()->status);
        Http::assertNothingSent();
        $key->update(['revoked_at' => null]);
        $user->roles()->firstOrFail()->update(['code' => 'staff']);
        Task::create(['org_id' => $user->org_id, 'title' => 'No assigned row visibility']);
        app(ExternalWebhookService::class)->dispatch();
        $this->assertSame(2, IntegrationWebhookDelivery::where('status', 'skipped_no_access')->count());
        Http::assertNothingSent();
    }

    public function test_customer_webhook_never_notifies_about_another_party_document(): void
    {
        [$user, $key, $endpoint] = $this->fixture();
        $permission = Permission::firstOrCreate(['code' => 'invoices.view'], ['module' => 'fixture', 'action' => 'view']);
        $user->roles()->firstOrFail()->permissions()->syncWithoutDetaching([$permission->id]);
        $a = Customer::create(['org_id' => $user->org_id, 'customer_code' => 'A', 'company_name' => 'Customer A', 'status' => 'active']);
        $b = Customer::create(['org_id' => $user->org_id, 'customer_code' => 'B', 'company_name' => 'Customer B', 'status' => 'active']);
        $key->update(['scopes' => ['invoices:read'], 'access_policy' => ['principal' => 'customer', 'party_id' => $a->id]]);
        $endpoint->update(['events' => ['invoice.payment.status.changed']]);
        $mine = Invoice::create(['org_id' => $user->org_id, 'customer_id' => $a->id, 'invoice_no' => 'A', 'issue_date' => '2026-10-01', 'status' => 'sent']);
        Invoice::create(['org_id' => $user->org_id, 'customer_id' => $b->id, 'invoice_no' => 'B', 'issue_date' => '2026-10-01', 'status' => 'sent']);
        Http::fake(fn () => Http::response('', 204));
        app(ExternalWebhookService::class)->dispatch();
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => json_decode($request->body(), true)['resource_id'] === $mine->id);
        $this->assertSame(1, IntegrationWebhookDelivery::where('status', 'skipped_no_access')->count());
    }

    public function test_connection_failures_dead_letter_after_five_attempts_without_recording_exception_text(): void
    {
        [$user] = $this->fixture();
        Task::create(['org_id' => $user->org_id, 'title' => 'Queued']);
        Http::fake(fn () => throw new ConnectionException('PRIVATE_TOKEN_URL'));
        $service = app(ExternalWebhookService::class);
        for ($i = 0; $i < 5; $i++) {
            $service->dispatch();
            $this->travel(1000)->seconds();
        }
        $row = IntegrationWebhookDelivery::firstOrFail();
        $this->assertSame('dead_letter', $row->status);
        $this->assertSame(5, $row->attempts);
        $this->assertSame('transport_error', $row->last_error);
        $this->assertStringNotContainsString('PRIVATE_TOKEN_URL', $row->toJson());
        $this->assertSame(0, $service->dispatch());
    }

    public function test_admin_reveals_secret_once_and_verifies_signed_challenge_then_rotation_pauses(): void
    {
        [$user, $key] = $this->fixture();
        $this->actingAs($user)->postJson(route('settings.api-webhooks.store'), [])->assertStatus(423);
        $response = $this->withSession(['auth.password_confirmed_at' => time()])->postJson(route('settings.api-webhooks.store'), [
            'name' => 'New receiver', 'url' => 'https://receiver.example.test/hook', 'integration_api_key_id' => $key->id, 'events' => ['task.status.changed'],
        ])->assertCreated()->assertHeader('Cache-Control', 'no-store, private');
        $secret = $response->json('secret');
        $id = $response->json('endpoint.id');
        $this->assertStringNotContainsString($secret, json_encode(session()->all()));
        $this->get(route('settings.api-webhooks.index'))->assertOk()->assertDontSee($secret);
        Http::fake(function ($request) use ($secret) {
            $body = json_decode($request->body(), true);

            return Http::response(['challenge' => $body['challenge'], 'signature' => hash_hmac('sha256', 'verify:'.$body['challenge'], $secret)]);
        });
        $this->postJson(route('settings.api-webhooks.verify', $id))->assertOk()->assertJsonPath('endpoint.status', 'active');
        $this->postJson(route('settings.api-webhooks.rotate', $id))->assertOk()->assertJsonPath('endpoint.status', 'paused')->assertJsonPath('endpoint.verified_at', null);
    }

    public function test_destination_resolver_rejects_private_reserved_mapped_ips_and_unsafe_urls(): void
    {
        $resolver = new WebhookDestinationResolver;
        foreach (['127.0.0.1', '10.0.0.1', '169.254.169.254', '100.64.0.1', '192.168.1.1', '198.18.0.1', '203.0.113.1', '224.0.0.1', '::1', '::ffff:127.0.0.1', 'fe80::1', 'fc00::1', '2002:7f00:1::', '2001::1234'] as $ip) {
            $this->assertFalse($resolver->isPublicIp($ip), $ip);
        }
        $this->assertTrue($resolver->isPublicIp('8.8.8.8'));
        $this->assertTrue($resolver->isPublicIp('2606:4700:4700::1111'));
        foreach (['http://8.8.8.8', 'https://u:p@8.8.8.8', 'https://8.8.8.8:444', 'https://8.8.8.8?key=x', 'https://8.8.8.8#fragment', 'https://127.0.0.1'] as $url) {
            try {
                $resolver->resolve($url);
                $this->fail('Unsafe URL accepted.');
            } catch (\RuntimeException $error) {
                $this->assertSame('unsafe_destination', $error->getMessage());
            }
        }
    }
}
