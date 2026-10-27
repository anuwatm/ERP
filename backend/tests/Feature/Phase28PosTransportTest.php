<?php

namespace Tests\Feature;

use App\Services\Pos\PosTransport;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class Phase28PosTransportTest extends TestCase
{
    private string $instance;

    private array $files = [];

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        $this->instance = (string) Str::uuid();
        foreach (['ca_bundle', 'certificate', 'private_key'] as $name) {
            $this->files[$name] = tempnam(sys_get_temp_dir(), 'pos-tls-fixture-');
        }
        config(['pos.local_transport_enabled' => true, 'pos.local_connections' => [$this->instance => $this->files + ['origin' => 'https://127.0.0.1:8443']]]);
    }

    protected function tearDown(): void
    {
        foreach ($this->files as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        parent::tearDown();
    }

    public function test_fixed_local_operation_uses_instance_identity(): void
    {
        Http::fake(function ($request, $options) {
            $this->assertSame($this->files['ca_bundle'], $options['verify']);
            $this->assertSame($this->files['certificate'], $options['cert']);
            $this->assertSame($this->files['private_key'], $options['ssl_key']);
            $this->assertFalse($options['allow_redirects']);
            $this->assertFalse($options['decode_content']);
            $this->assertSame('', $options['proxy']);

            return Http::response(['events' => [], 'next_cursor' => 0]);
        });
        $result = app(PosTransport::class)->request($this->instance, 'events', ['cursor' => 0, 'limit' => 100]);
        $this->assertSame(0, $result['next_cursor']);
        Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://127.0.0.1:8443/connector/v1/events') && $request->hasHeader('X-POS-Instance', $this->instance));
        Http::assertSentCount(1);
    }

    public function test_remote_host_redirect_and_oversize_response_are_blocked(): void
    {
        config(['pos.local_connections.'.$this->instance.'.origin' => 'https://example.invalid']);
        try {
            app(PosTransport::class)->request($this->instance, 'events');
            $this->fail('Remote host must fail.');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
        Http::assertNothingSent();
        config(['pos.local_connections.'.$this->instance.'.origin' => 'https://127.0.0.1:8443']);
        Http::fake(['*' => Http::response('', 302, ['Location' => 'https://example.invalid'])]);
        try {
            app(PosTransport::class)->request($this->instance, 'events');
            $this->fail('Redirect must fail.');
        } catch (HttpException $e) {
            $this->assertSame(502, $e->getStatusCode());
        }
        Http::assertSentCount(1);
        Http::fake(['*' => Http::response(str_repeat('x', 2097153), 200)]);
        $this->expectException(HttpException::class);
        app(PosTransport::class)->request($this->instance, 'events');
    }

    public function test_production_never_sends_even_with_local_feature_flag(): void
    {
        $this->app['env'] = 'production';
        try {
            app(PosTransport::class)->request($this->instance, 'events');
            $this->fail('Production must fail.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('local development', $e->getMessage());
        }
        Http::assertNothingSent();
    }

    public function test_payload_cannot_select_a_path_or_supply_userinfo_origin(): void
    {
        try {
            app(PosTransport::class)->request($this->instance, 'result', [], '../events');
            $this->fail('Path traversal must fail.');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
        config(['pos.local_connections.'.$this->instance.'.origin' => 'https://user:secret@127.0.0.1:8443']);
        try {
            app(PosTransport::class)->request($this->instance, 'events');
            $this->fail('Userinfo must fail.');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
        Http::assertNothingSent();
    }
}
