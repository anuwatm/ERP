<?php

namespace App\Services\Pos;

use App\Models\User;
use App\Support\PosCanonicalJson;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PosTaxDocumentImportService
{
    public function import(object $instance, array $event): array
    {
        abort_unless(DB::transactionLevel() > 0 && app()->environment(['local', 'testing']) && config('pos.local_tax_documents_enabled') === true, 403);
        $event = app(PosEventVerifier::class)->verify($instance, $event, 'tax.document_issued');
        validator($event['payload'], ['issuer' => 'required|array:branch_id,version,seller_name,seller_tax_id,seller_address,seller_branch_no', 'buyer' => 'present|nullable|array:name,tax_id,branch_no,address'])->validate();
        $p = validator($event['payload'], ['document_id' => 'required|uuid', 'document_no' => 'required|string|max:180', 'kind' => 'required|in:ABB,FULL', 'sale_id' => 'required|uuid', 'receipt_no' => 'required|string|max:100', 'branch_id' => 'required|uuid', 'terminal_id' => 'required|uuid', 'staff_id' => 'required|uuid', 'replaces_document_id' => 'present|nullable|uuid', 'replaces_document_no' => 'present|nullable|string|max:180', 'issuer' => 'required|array', 'buyer' => 'present|nullable|array', 'currency' => 'required|in:THB', 'subtotal' => 'required|string', 'tax_amount' => 'required|string', 'total' => 'required|string', 'lines' => 'required|array|min:1|max:100', 'occurred_at' => 'required|date', 'business_timezone' => 'required|in:Asia/Bangkok'])->validate();
        abort_unless($p['document_id'] === $event['aggregate_id'] && $p['branch_id'] === $instance->branch_id && in_array($p['terminal_id'], json_decode($instance->terminal_ids, true), true) && $p['occurred_at'] === $event['occurred_at'], 403, 'Tax document source scope invalid.');
        User::where('org_id', $instance->org_id)->where('status', 'active')->findOrFail($instance->actor_id);
        $old = DB::table('pos_import_tax_documents')->where('pos_instance_id', $instance->id)->where('source_document_id', $p['document_id'])->first();
        if ($old) {
            abort_unless(hash_equals($old->payload_hash, $event['payload_hash']), 409, 'Tax document identity conflict.');

            return ['status' => 'accepted', 'reason_code' => null];
        }
        $sale = DB::table('pos_import_sales')->where('pos_instance_id', $instance->id)->where('source_sale_id', $p['sale_id'])->first();
        if (! $sale) {
            throw new PosDependencyPending('sale', [$p['sale_id']]);
        }
        $origin = json_decode($sale->source_snapshot, true, flags: JSON_THROW_ON_ERROR);
        $at = Carbon::parse($p['occurred_at']);
        abort_unless($at->gte(Carbon::parse($origin['occurred_at'])) && ! $at->gt(now()->addMinutes(5)) && $p['receipt_no'] === $origin['receipt_no'] && $p['currency'] === $origin['currency'], 422, 'Tax document source time or receipt invalid.');
        foreach (['subtotal', 'tax_amount', 'total'] as $field) {
            abort_unless(preg_match('/^\d{1,14}\.\d{2}$/D', $p[$field]) && bccomp($p[$field], $origin[$field], 2) === 0, 422, 'Tax document totals differ from source sale.');
        }
        abort_unless(PosCanonicalJson::encode($p['lines']) === PosCanonicalJson::encode($origin['lines']), 422, 'Tax lines differ from original sale evidence.');
        $issuer = validator($p['issuer'], ['version' => 'required|string|max:80', 'branch_id' => 'required|uuid', 'seller_name' => 'required|string|max:255', 'seller_tax_id' => ['required', 'string', 'regex:/^\d{13}$/D'], 'seller_address' => 'required|string|max:1000', 'seller_branch_no' => ['required', 'string', 'regex:/^\d{5}$/D']])->validate();
        $published = DB::table('pos_tax_profile_publications')->where('pos_instance_id', $instance->id)->where('version', $issuer['version'])->first();
        abort_unless($published && Carbon::parse($published->created_at)->lte($at) && hash_equals($published->payload_hash, hash('sha256', PosCanonicalJson::encode($issuer))) && $issuer['branch_id'] === $instance->branch_id, 422, 'Tax issuer not provisioned by ERP.');
        $terminalCodes = json_decode($instance->terminal_codes ?? '{}', true);
        abort_unless($instance->branch_code && isset($terminalCodes[$p['terminal_id']]), 422, 'Trusted tax numbering identity must be provisioned.');
        $prefix = $p['kind'].'-'.$instance->branch_code.'-'.$terminalCodes[$p['terminal_id']].'-'.$at->copy()->setTimezone('Asia/Bangkok')->format('Ymd').'-';
        abort_unless(preg_match('/^'.preg_quote($prefix, '/').'\d{8}$/D', $p['document_no']), 422, 'Tax numbering policy invalid.');
        if ($p['kind'] === 'FULL') {
            validator($p['buyer'] ?? [], ['name' => 'required|string|max:255', 'tax_id' => ['required', 'string', 'regex:/^\d{13}$/D'], 'branch_no' => ['required', 'string', 'regex:/^\d{5}$/D'], 'address' => 'required|string|max:1000'])->validate();
        } else {
            abort_unless($p['buyer'] === null && $p['replaces_document_id'] === null, 422, 'ABB cannot replace another original or include buyer data.');
        }
        $active = DB::table('pos_import_tax_documents')->where('sale_id', $sale->id)->whereNotNull('current_slot')->first();
        if ($p['replaces_document_id']) {
            $parent = DB::table('pos_import_tax_documents')->where('pos_instance_id', $instance->id)->where('source_document_id', $p['replaces_document_id'])->first();
            if (! $parent) {
                throw new PosDependencyPending('tax_document', [$p['replaces_document_id']]);
            }
            abort_unless($active && $active->id === $parent->id && $parent->sale_id === $sale->id && $parent->kind === 'ABB' && $p['kind'] === 'FULL' && $p['replaces_document_no'] === $parent->document_no, 409, 'Tax replacement source conflict.');
            DB::table('pos_import_tax_documents')->where('id', $parent->id)->update(['current_slot' => null, 'updated_at' => now()]);
        } else {
            abort_unless(! $active && $p['replaces_document_no'] === null, 409, 'Original tax document already exists.');
        }
        DB::table('pos_import_tax_documents')->insert(['id' => (string) Str::uuid(), 'org_id' => $instance->org_id, 'pos_instance_id' => $instance->id, 'source_document_id' => $p['document_id'], 'sale_id' => $sale->id, 'document_no' => $p['document_no'], 'kind' => $p['kind'], 'replaces_source_id' => $p['replaces_document_id'], 'current_slot' => 1, 'payload_hash' => $event['payload_hash'], 'source_snapshot' => PosCanonicalJson::encode($p), 'created_at' => now(), 'updated_at' => now()]);

        return ['status' => 'accepted', 'reason_code' => null];
    }
}
