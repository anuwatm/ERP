<?php

namespace App\Services\Pos;

use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\User;
use App\Support\PosCanonicalJson;
use Company\Pos\MemberEnvelope;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PosMemberRequestImportService
{
    public function import(object $instance, array $event): array
    {
        $this->guard();
        abort_unless(DB::transactionLevel() > 0, 500);
        $event = app(PosEventVerifier::class)->verify($instance, $event, 'member.requested');
        $p = validator($event['payload'], ['request_id' => 'required|uuid', 'branch_id' => 'required|uuid', 'staff_id' => 'required|uuid', 'publication_id' => 'required|uuid', 'envelope' => 'required|array:key_id,wrapped_key,iv,tag,ciphertext', 'envelope.*' => 'required|string|max:8192', 'occurred_at' => 'required|date|before_or_equal:'.now()->addMinutes(5)->toIso8601String(), 'business_timezone' => 'required|in:Asia/Bangkok'])->validate();
        abort_unless(count($p) === count($event['payload']) && $p['request_id'] === $event['aggregate_id'] && $p['branch_id'] === $instance->branch_id && $p['occurred_at'] === $event['occurred_at'], 403, 'Member request scope mismatch.');
        abort_unless(DB::table('pos_staff_authorities')->where('pos_instance_id', $instance->id)->where('source_staff_id', $p['staff_id'])->where('active', true)->whereIn('role', ['cashier', 'manager', 'pharmacist'])->exists(), 403, 'Member enrollment staff unavailable.');
        $old = DB::table('pos_import_member_requests')->where('pos_instance_id', $instance->id)->where('source_request_id', $p['request_id'])->first();
        if ($old) {
            abort_unless(hash_equals($old->payload_hash, $event['payload_hash']), 409, 'Member request source conflict.');

            return ['status' => 'accepted', 'reason_code' => null, 'member_status' => $old->status];
        }
        $publication = DB::table('pos_membership_publications')->where('id', $p['publication_id'])->where('org_id', $instance->org_id)->where('pos_instance_id', $instance->id)->firstOrFail();
        $pub = json_decode($publication->payload, true);
        abort_unless(hash_equals($publication->payload_hash, hash('sha256', PosCanonicalJson::encode($pub))), 422, 'Membership publication integrity mismatch.');
        $occurred = Carbon::parse($p['occurred_at']);
        abort_unless($occurred->gte(Carbon::parse($pub['created_at'])) && $occurred->lte(Carbon::parse($pub['valid_until'])), 422, 'Enrollment publication expired at request time.');
        abort_unless(! empty($pub['enrollment_key']) && hash_equals($pub['enrollment_key']['id'], $p['envelope']['key_id']), 422, 'Unpublished enrollment key.');
        $path = config('pos.member_enrollment_private_key_path');
        abort_unless(is_string($path) && is_file($path), 503, 'Enrollment private key unavailable.');
        $contact = (new MemberEnvelope)->open(file_get_contents($path), $p['envelope'], $instance->id.'|'.$instance->branch_id.'|'.$p['request_id']);
        abort_unless(count($contact) === 3 && ! array_diff(array_keys($contact), ['name', 'phone', 'consent_confirmed']), 422, 'Unexpected member contact fields.');
        $contact = validator($contact, ['name' => ['required', 'string', 'max:80', 'regex:/^[^\p{C}]+$/u'], 'phone' => ['required', 'string', 'regex:/^[0-9]{8,15}$/D'], 'consent_confirmed' => 'required|accepted'])->validate();
        DB::table('pos_import_member_requests')->insert(['id' => (string) Str::uuid(), 'org_id' => $instance->org_id, 'pos_instance_id' => $instance->id, 'source_request_id' => $p['request_id'], 'source_staff_id' => $p['staff_id'], 'payload_hash' => $event['payload_hash'], 'source_snapshot' => PosCanonicalJson::encode($p), 'contact' => Crypt::encryptString(PosCanonicalJson::encode($contact)), 'created_at' => now(), 'updated_at' => now()]);

        return ['status' => 'accepted', 'reason_code' => null, 'member_status' => 'pending_review'];
    }

    public function decide(User $actor, string $id, string $decision, ?string $existingMember): array
    {
        $this->guard();
        abort_unless(in_array($decision, ['approve', 'reject'], true), 422);

        return DB::transaction(function () use ($actor, $id, $decision, $existingMember) {
            Organization::whereKey($actor->org_id)->lockForUpdate()->firstOrFail();
            $current = User::findOrFail($actor->id);
            abort_unless($current->org_id === $actor->org_id && $current->status === 'active' && $current->hasPermissionCode('pos.manage'), 403);
            $row = DB::table('pos_import_member_requests')->where('id', $id)->where('org_id', $actor->org_id)->lockForUpdate()->firstOrFail();
            if ($row->status !== 'pending_review') {
                abort_unless(($row->status === 'approved') === ($decision === 'approve') && (! $existingMember || $existingMember === $row->member_id), 409, 'Member decision conflict.');

                return ['status' => $row->status, 'member_id' => $row->member_id];
            }
            $contact = json_decode(Crypt::decryptString($row->contact), true);
            $master = app(PosMembershipMasterService::class);
            $memberId = null;
            if ($decision === 'approve') {
                if ($existingMember) {
                    abort_unless(collect($master->candidates($actor, $contact['phone']))->contains('member_id', $existingMember), 409, 'Explicit matching member required.');
                    $memberId = $existingMember;
                } else {
                    $memberId = $master->register($actor, ['request_key' => $row->id, 'name' => $contact['name'], 'phone' => $contact['phone'], 'tier' => 'standard', 'consent_confirmed' => true])['member_id'];
                }
            }
            $status = $decision === 'approve' ? 'approved' : 'rejected';
            DB::table('pos_import_member_requests')->where('id', $row->id)->update(['status' => $status, 'member_id' => $memberId, 'reviewed_by' => $actor->id, 'updated_at' => now()]);
            AuditLog::create(['org_id' => $actor->org_id, 'actor_user_id' => $actor->id, 'action' => 'pos.member.request.'.$status, 'entity_type' => 'pos_membership', 'entity_id' => $row->id, 'after_json' => ['member_id' => $memberId], 'request_id' => (string) Str::uuid()]);

            return ['status' => $status, 'member_id' => $memberId];
        });
    }

    private function guard(): void
    {
        abort_unless(app()->environment(['local', 'testing']) && config('pos.local_membership_enabled') === true, 503, 'Local membership disabled.');
    }
}
