<?php

namespace Pos\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Pos\Models\Staff;
use Pos\Support\CanonicalJson;

class TaxDocumentService
{
    public function provision(array $input): void
    {
        $this->guard();
        $p = validator($input, ['branch_id' => 'required|uuid', 'version' => 'required|string|max:80', 'seller_name' => 'required|string|max:255', 'seller_tax_id' => ['required', 'string', 'regex:/^\d{13}$/D'], 'seller_address' => 'required|string|max:1000', 'seller_branch_no' => ['required', 'string', 'regex:/^\d{5}$/D']])->validate();
        abort_unless(in_array($p['branch_id'], config('pos.connector_branch_ids'), true), 403);
        DB::transaction(function () use ($p) {
            DB::table('pos_branches')->where('id', $p['branch_id'])->lockForUpdate()->firstOrFail();
            $old = DB::table('pos_tax_profiles')->where('branch_id', $p['branch_id'])->first();
            $hash = hash('sha256', CanonicalJson::encode($p));
            if ($old && $old->version === $p['version']) {
                abort_unless(hash_equals($old->payload_hash, $hash), 409, 'Tax profile version conflict.');

                return;
            }
            DB::table('pos_tax_profiles')->updateOrInsert(['branch_id' => $p['branch_id']], ['version' => $p['version'], 'snapshot' => CanonicalJson::encode($p), 'payload_hash' => $hash, 'created_at' => $old?->created_at ?? now(), 'updated_at' => now()]);
        });
    }

    public function issue(Staff $staff, array $input): array
    {
        $this->guard();
        validator($input, ['buyer' => 'sometimes|array:name,tax_id,branch_no,address'])->validate();
        $d = validator($input, ['sale_id' => 'required|uuid', 'terminal_id' => 'required|uuid', 'kind' => 'required|in:ABB,FULL', 'request_key' => 'required|string|max:100', 'buyer' => 'required_if:kind,FULL|array', 'buyer.name' => 'required_if:kind,FULL|string|max:255', 'buyer.tax_id' => ['required_if:kind,FULL', 'string', 'regex:/^\d{13}$/D'], 'buyer.branch_no' => ['required_if:kind,FULL', 'string', 'regex:/^\d{5}$/D'], 'buyer.address' => 'required_if:kind,FULL|string|max:1000'])->validate();
        if ($d['kind'] === 'ABB') {
            abort_if(isset($d['buyer']), 422, 'ABB does not collect buyer details.');
            $d['buyer'] = null;
        }
        $hash = hash('sha256', CanonicalJson::encode($d));

        return DB::transaction(function () use ($staff, $d, $hash) {
            $actor = Staff::whereKey($staff->id)->lockForUpdate()->firstOrFail();
            abort_unless($actor->active && $actor->branch_id === $staff->branch_id && in_array($actor->role, ['cashier', 'manager', 'pharmacist'], true), 403);
            $terminal = DB::table('pos_terminals')->where('id', $d['terminal_id'])->where('branch_id', $actor->branch_id)->where('active', true)->lockForUpdate()->firstOrFail();
            $branch = DB::table('pos_branches')->where('id', $actor->branch_id)->where('active', true)->lockForUpdate()->firstOrFail();
            abort_unless($branch->currency === 'THB', 422, 'Local tax issuance supports THB only.');
            $order = DB::table('pos_orders')->where('id', $d['sale_id'])->where('branch_id', $actor->branch_id)->where('status', 'completed')->lockForUpdate()->firstOrFail();
            $old = DB::table('pos_tax_documents')->where('branch_id', $actor->branch_id)->where('request_key', $d['request_key'])->first();
            if ($old) {
                abort_unless($old->staff_id === $actor->id && hash_equals($old->request_hash, $hash), 409, 'Tax issuance identity conflict.');

                return json_decode($old->snapshot, true);
            }
            $active = DB::table('pos_tax_documents')->where('order_id', $order->id)->whereNotNull('current_slot')->lockForUpdate()->first();
            abort_if($active && ($active->kind === 'FULL' || $d['kind'] !== 'FULL'), 409, 'An original tax document already exists; reprint it instead.');
            $profile = DB::table('pos_tax_profiles')->where('branch_id', $branch->id)->firstOrFail();
            $sale = json_decode($order->snapshot, true, flags: JSON_THROW_ON_ERROR);
            $day = now('Asia/Bangkok')->toDateString();
            DB::table('pos_tax_sequences')->insertOrIgnore(['terminal_id' => $terminal->id, 'business_date' => $day, 'kind' => $d['kind'], 'last_number' => 0]);
            $seq = DB::table('pos_tax_sequences')->where('terminal_id', $terminal->id)->where('business_date', $day)->where('kind', $d['kind'])->lockForUpdate()->firstOrFail();
            abort_if($seq->last_number >= 99999999, 422, 'Tax number range exhausted.');
            DB::table('pos_tax_sequences')->where('id', $seq->id)->update(['last_number' => $seq->last_number + 1]);
            $id = (string) Str::uuid();
            $number = $d['kind'].'-'.$branch->code.'-'.$terminal->code.'-'.str_replace('-', '', $day).'-'.str_pad((string) ($seq->last_number + 1), 8, '0', STR_PAD_LEFT);
            $snapshot = ['document_id' => $id, 'document_no' => $number, 'kind' => $d['kind'], 'sale_id' => $order->id, 'receipt_no' => $order->receipt_no, 'branch_id' => $branch->id, 'terminal_id' => $terminal->id, 'staff_id' => $actor->id, 'replaces_document_id' => $active?->id, 'replaces_document_no' => $active?->document_no, 'issuer' => json_decode($profile->snapshot, true), 'buyer' => $d['buyer'], 'currency' => $sale['currency'], 'subtotal' => $sale['subtotal'], 'tax_amount' => $sale['tax_amount'], 'total' => $sale['total'], 'lines' => $sale['lines'], 'occurred_at' => now()->utc()->toIso8601String(), 'business_timezone' => 'Asia/Bangkok'];
            if ($active) {
                DB::table('pos_tax_documents')->where('id', $active->id)->update(['current_slot' => null, 'status' => 'superseded', 'updated_at' => now()]);
            }
            DB::table('pos_tax_documents')->insert(['id' => $id, 'branch_id' => $branch->id, 'order_id' => $order->id, 'terminal_id' => $terminal->id, 'staff_id' => $actor->id, 'kind' => $d['kind'], 'document_no' => $number, 'status' => 'issued', 'current_slot' => 1, 'replaces_id' => $active?->id, 'request_key' => $d['request_key'], 'request_hash' => $hash, 'snapshot' => CanonicalJson::encode($snapshot), 'created_at' => now(), 'updated_at' => now()]);
            DB::table('pos_etax_queue')->insert(['document_id' => $id, 'status' => 'awaiting_provider', 'attempts' => 0, 'reason_code' => 'provider_not_configured', 'created_at' => now(), 'updated_at' => now()]);
            if ($active) {
                DB::table('pos_etax_queue')->where('document_id', $active->id)->update(['status' => 'superseded', 'reason_code' => 'replaced_before_submission', 'updated_at' => now()]);
            }
            app(OutboxService::class)->append('tax.document_issued', $id, $snapshot);
            DB::table('pos_audit')->insert(['actor_id' => $actor->id, 'branch_id' => $branch->id, 'action' => 'tax.document_issued', 'source_id' => $id, 'metadata' => CanonicalJson::encode(['sale_id' => $order->id, 'kind' => $d['kind'], 'replaces_id' => $active?->id]), 'created_at' => now()]);

            return $snapshot;
        });
    }

    private function guard(): void
    {
        abort_unless(app()->environment(['local', 'testing']) && config('pos.local_tax_documents_enabled') === true, 503, 'Local tax documents disabled.');
    }
}
