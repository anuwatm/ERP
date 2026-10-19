<?php

namespace Tests\Feature;

use App\Models\IntegrationApiKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExternalReadContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_openapi_covers_every_read_route_and_every_route_requires_integration_authentication(): void
    {
        Cache::flush();
        config(['external_api.invalid_requests_per_minute' => 1000]);
        $spec = json_decode(file_get_contents(base_path('../docs/openapi/external-read-v1.json')), true, 512, JSON_THROW_ON_ERROR);
        $paths = [];
        foreach (app('router')->getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/v1/')) {
                continue;
            }
            $path = '/'.$route->uri();
            $paths[] = $path;
            $this->assertSame([], array_values(array_diff($route->methods(), ['GET', 'HEAD'])), $path);
            $this->assertArrayHasKey($path, $spec['paths']);
            $this->assertArrayHasKey('get', $spec['paths'][$path]);
            $this->assertNotEmpty(array_filter($route->gatherMiddleware(), fn ($middleware) => is_string($middleware) && str_starts_with($middleware, 'App\\Http\\Middleware\\AuthenticateIntegrationKey:')), $path);
            $this->getJson(str_replace('{id}', (string) Str::uuid(), $path))->assertUnauthorized()->assertJsonPath('code', 'unauthorized')->assertHeader('Cache-Control', 'no-store, private');
        }
        sort($paths);
        $contractPaths = array_keys($spec['paths']);
        sort($contractPaths);
        $this->assertSame($paths, $contractPaths);
        foreach ($spec['paths'] as $path => $operations) {
            $this->assertSame(['get'], array_keys($operations), $path);
        }
    }

    public function test_contract_has_no_sensitive_personal_financial_or_write_surfaces_and_all_references_resolve(): void
    {
        $spec = json_decode(file_get_contents(base_path('../docs/openapi/external-read-v1.json')), true, 512, JSON_THROW_ON_ERROR);
        $forbidden = ['password', 'secret', 'secret_hash', 'national_id', 'tax_id', 'date_of_birth', 'bank_account_number',
            'bank_account_id', 'bank_name', 'salary', 'gross_pay', 'net_pay', 'email', 'phone', 'reason', 'correction_reason',
            'clock_in_ip_hash', 'clock_in_latitude', 'storage_key', 'subject_snapshot', 'definition_snapshot'];
        $walk = function ($node) use (&$walk, $spec, $forbidden) {
            if (! is_array($node)) {
                return;
            }
            foreach (array_keys($node['properties'] ?? []) as $field) {
                $this->assertNotContains($field, $forbidden);
            }
            if (isset($node['$ref'])) {
                $this->assertStringStartsWith('#/', $node['$ref']);
                $target = $spec;
                foreach (explode('/', substr($node['$ref'], 2)) as $part) {
                    $part = str_replace(['~1', '~0'], ['/', '~'], $part);
                    $this->assertArrayHasKey($part, $target);
                    $target = $target[$part];
                }
            }
            foreach ($node as $child) {
                $walk($child);
            }
        };
        $walk($spec);
        foreach (IntegrationApiKey::SCOPES as $scope) {
            $this->assertTrue(str_ends_with($scope, ':read') || $scope === 'documents:download');
            $this->assertDoesNotMatchRegularExpression('/payroll|salary|bank|identity|:write|:create|:approve|:delete/', $scope);
        }
    }
}
