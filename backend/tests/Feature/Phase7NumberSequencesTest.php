<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\NumberSequence;
use App\Models\Setting;
use App\Models\User;
use App\Services\NumberSequenceService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class Phase7NumberSequencesTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_format_remains_six_digit_sequence(): void
    {
        $user = User::factory()->create();
        $numbers = app(NumberSequenceService::class);

        $this->assertSame('000001', $numbers->next($user->org_id, 'invoice'));
        $this->assertSame('000002', $numbers->preview($user->org_id, 'invoice'));
    }

    public function test_custom_invoice_format_and_monthly_reset(): void
    {
        $user = User::factory()->create();
        Setting::create([
            'org_id' => $user->org_id,
            'key' => 'document_numbering.formats',
            'value_json' => [
                'invoice' => [
                    'format' => 'INV-{YYYY}{MM}-{SEQ:5}',
                    'reset' => 'monthly',
                    'scope' => 'organization',
                    'enabled' => true,
                ],
            ],
        ]);

        $numbers = app(NumberSequenceService::class);

        $this->assertSame('INV-202608-00001', $numbers->next($user->org_id, 'invoice', null, CarbonImmutable::parse('2026-08-21')));
        $this->assertSame('INV-202608-00002', $numbers->preview($user->org_id, 'invoice', null, CarbonImmutable::parse('2026-08-22')));
        $this->assertSame('INV-202609-00001', $numbers->next($user->org_id, 'invoice', null, CarbonImmutable::parse('2026-09-01')));
    }

    public function test_branch_scope_separates_sequences(): void
    {
        $user = User::factory()->create();
        $firstBranch = Branch::create(['org_id' => $user->org_id, 'code' => 'BKK', 'name' => 'Bangkok']);
        $secondBranch = Branch::create(['org_id' => $user->org_id, 'code' => 'CNX', 'name' => 'Chiang Mai']);
        Setting::create([
            'org_id' => $user->org_id,
            'key' => 'document_numbering.formats',
            'value_json' => [
                'po' => [
                    'format' => 'PO-{BRANCH}-{YY}-{SEQ:3}',
                    'reset' => 'yearly',
                    'scope' => 'branch',
                    'enabled' => true,
                ],
            ],
        ]);

        $numbers = app(NumberSequenceService::class);

        $this->assertSame('PO-BKK-26-001', $numbers->next($user->org_id, 'po', $firstBranch->id, CarbonImmutable::parse('2026-08-21')));
        $this->assertSame('PO-BKK-26-002', $numbers->next($user->org_id, 'po', $firstBranch->id, CarbonImmutable::parse('2026-08-21')));
        $this->assertSame('PO-CNX-26-001', $numbers->next($user->org_id, 'po', $secondBranch->id, CarbonImmutable::parse('2026-08-21')));

        $this->assertSame(2, NumberSequence::where('org_id', $user->org_id)->where('doc_type', 'po')->count());
    }

    public function test_invalid_format_is_rejected(): void
    {
        $user = User::factory()->create();
        Setting::create([
            'org_id' => $user->org_id,
            'key' => 'document_numbering.formats',
            'value_json' => [
                'invoice' => [
                    'format' => 'INV-{YYYY}',
                    'reset' => 'monthly',
                    'scope' => 'organization',
                    'enabled' => true,
                ],
            ],
        ]);

        $this->expectException(InvalidArgumentException::class);

        app(NumberSequenceService::class)->next($user->org_id, 'invoice', null, CarbonImmutable::parse('2026-08-21'));
    }

    public function test_repeated_custom_sequence_calls_do_not_duplicate_numbers(): void
    {
        $user = User::factory()->create();
        Setting::create([
            'org_id' => $user->org_id,
            'key' => 'document_numbering.formats',
            'value_json' => [
                'invoice' => [
                    'format' => 'INV-{YYYY}{MM}-{SEQ:5}',
                    'reset' => 'monthly',
                    'scope' => 'organization',
                    'enabled' => true,
                ],
            ],
        ]);

        $numbers = app(NumberSequenceService::class);
        $generated = collect(range(1, 10))
            ->map(fn () => $numbers->next($user->org_id, 'invoice', null, CarbonImmutable::parse('2026-08-21')))
            ->all();

        $this->assertCount(10, array_unique($generated));
        $this->assertSame('INV-202608-00010', end($generated));
    }
}
