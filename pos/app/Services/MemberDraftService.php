<?php

namespace Pos\Services;

use Company\Pos\MemberEnvelope;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Pos\Models\Staff;
use Pos\Support\CanonicalJson;

class MemberDraftService
{
    public function submit(Staff $staff, array $input): array
    {
        $branch = app(MembershipService::class)->actor($staff);
        $d = validator($input, ['request_key' => 'required|uuid', 'member_name' => ['required', 'string', 'max:80', 'regex:/^[^\p{C}]+$/u'], 'member_phone' => ['required', 'string', 'regex:/^\+?[0-9 ()-]{8,30}$/D'], 'consent_confirmed' => 'required|accepted'])->validate();
        $contact = ['name' => trim($d['member_name']), 'phone' => preg_replace('/[^0-9]/', '', $d['member_phone']), 'consent_confirmed' => true];
        abort_unless(preg_match('/^[0-9]{8,15}$/D', $contact['phone']), 422, 'Invalid member phone.');
        $hash = hash_hmac('sha256', CanonicalJson::encode($contact), (string) config('app.key'));

        return DB::transaction(function () use ($staff, $branch, $d, $contact, $hash) {
            DB::table('pos_branches')->where('id', $branch->id)->lockForUpdate()->firstOrFail();
            app(MembershipService::class)->actor($staff);
            $old = DB::table('pos_member_drafts')->where('branch_id', $branch->id)->where('request_key', $d['request_key'])->first();
            if ($old) {
                abort_unless($old->staff_id === $staff->id && hash_equals($old->request_hash, $hash), 409, 'Member draft identity conflict.');

                return ['request_id' => $old->id, 'status' => $old->status];
            }
            $publication = DB::table('pos_membership_publications')->where('branch_id', $branch->id)->orderByDesc('version')->firstOrFail();
            abort_unless(Carbon::parse($publication->valid_until)->gt(now()), 409, 'Current enrollment publication required.');
            $pub = json_decode($publication->payload, true, flags: JSON_THROW_ON_ERROR);
            abort_unless(hash_equals($publication->payload_hash, hash('sha256', CanonicalJson::encode($pub))) && ! empty($pub['enrollment_key']), 503, 'Enrollment encryption key is not provisioned.');
            $id = (string) Str::uuid();
            $scope = config('pos.instance_id').'|'.$branch->id.'|'.$id;
            $sealed = (new MemberEnvelope)->seal($pub['enrollment_key']['public_key'], $contact, $scope);
            abort_unless(hash_equals($pub['enrollment_key']['id'], $sealed['key_id']), 409, 'Enrollment key fingerprint conflict.');
            DB::table('pos_member_drafts')->insert(['id' => $id, 'branch_id' => $branch->id, 'staff_id' => $staff->id, 'request_key' => $d['request_key'], 'request_hash' => $hash, 'contact' => Crypt::encryptString(CanonicalJson::encode($contact)), 'created_at' => now(), 'updated_at' => now()]);
            app(OutboxService::class)->append('member.requested', $id, ['request_id' => $id, 'branch_id' => $branch->id, 'staff_id' => $staff->id, 'publication_id' => $publication->id, 'envelope' => $sealed, 'occurred_at' => now()->utc()->toIso8601String(), 'business_timezone' => $branch->timezone]);

            return ['request_id' => $id, 'status' => 'pending_review'];
        });
    }
}
