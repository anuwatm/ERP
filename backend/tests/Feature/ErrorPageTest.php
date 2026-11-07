<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ErrorPageTest extends TestCase
{
    public function test_erp_error_layout_preserves_status_and_hides_exception_details(): void
    {
        config(['app.debug' => false]);
        Route::get('/_test/ui-error/{status}', function (string $status): never {
            abort((int) $status, 'Synthetic private exception detail', $status === '429' ? ['Retry-After' => '60'] : []);
        });

        foreach ([403 => 'Access denied', 404 => 'Page not found', 419 => 'Session expired', 429 => 'Too many requests', 500 => 'Something went wrong', 503 => 'Service unavailable'] as $status => $heading) {
            $response = $this->get('/_test/ui-error/'.$status)
                ->assertStatus($status)
                ->assertSee($heading)
                ->assertSee('Return to home')
                ->assertDontSee('Synthetic private exception detail');
            if ($status === 429) {
                $response->assertHeader('Retry-After', '60');
            }
        }

        $this->getJson('/_test/ui-error/404')
            ->assertNotFound()
            ->assertHeader('Content-Type', 'application/json')
            ->assertDontSee('Return to home');
    }
}
