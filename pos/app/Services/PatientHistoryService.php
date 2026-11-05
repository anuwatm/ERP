<?php

namespace Pos\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Pos\Models\Staff;
use Pos\Support\CanonicalJson;

final class PatientHistoryService
{
    private function scope(Staff $staff, bool $clinical): array
    {
        $scope = app(PatientHistoryScope::class)->lock($staff);
        $actor = $scope['staff'];
        abort_unless(in_array($actor->role, ['cashier', 'manager', 'pharmacist'], true), 403);
        if ($clinical) {
            abort_unless($actor->role === 'pharmacist' || ($actor->role === 'manager' && $actor->pharmacist_qualified), 403, 'Clinical authority required.');
        }
        abort_unless(is_string(config('app.key')) && config('app.key') !== '', 503, 'Patient encryption unavailable.');

        return $scope;
    }

    private function audit(Staff $staff, string $action, string $id, array $metadata = []): void
    {
        DB::table('pos_audit')->insert(['actor_id' => $staff->id, 'branch_id' => $staff->branch_id, 'action' => $action, 'source_id' => $id, 'metadata' => CanonicalJson::encode($metadata), 'created_at' => now()]);
    }

    private function hash(array $data): string
    {
        return hash_hmac('sha256', CanonicalJson::encode($data), (string) config('app.key'));
    }

    private function lookup(string $branch, string $field, string $value): string
    {
        $normalized = $field === 'phone' ? preg_replace('/\D+/', '', $value) : mb_strtolower(preg_replace('/\s+/u', ' ', trim($value)));
        abort_unless($normalized !== '', 422, 'Search value is required.');

        return $this->hash(['branch_id' => $branch, 'field' => $field, 'value' => $normalized]);
    }

    public function search(Staff $staff, array $input): array
    {
        return DB::transaction(function () use ($staff, $input) {
            $this->scope($staff, true);
            $d = validator(['command' => $input], ['command' => 'required|array:field,term', 'command.field' => 'required|in:name,phone', 'command.term' => 'required|string|max:150'])->validate()['command'];
            $hash = $this->lookup($staff->branch_id, $d['field'], $d['term']);
            $rows = DB::table('pos_patients')->where('branch_id', $staff->branch_id)->where($d['field'].'_lookup', $hash)->orderBy('id')->limit(100)->get();
            $items = $rows->map(function ($row) {
                $profile = json_decode(Crypt::decryptString($row->payload), true, flags: JSON_THROW_ON_ERROR);

                return ['id' => $row->id, 'code' => $row->code, 'name_masked' => mb_substr($profile['name'], 0, 1).'***'];
            })->all();
            $this->audit($staff, 'patient.search', $staff->branch_id, ['count' => count($items)]);

            return ['items' => $items, 'next' => null, 'clinical_access' => true];
        });
    }

    private function row(Staff $staff, string $id): object
    {
        abort_unless(Str::isUuid($id), 404);

        return DB::table('pos_patients')->where('branch_id', $staff->branch_id)->where('id', $id)->lockForUpdate()->firstOrFail();
    }

    public function options(Staff $staff, ?string $after = null): array
    {
        return DB::transaction(function () use ($staff, $after) {
            $scope = $this->scope($staff, false);
            if ($after !== null) {
                abort_unless(Str::isUuid($after), 422);
            }
            $rows = DB::table('pos_patients')->where('branch_id', $staff->branch_id)->when($after, fn ($q) => $q->where('id', '>', $after))->orderBy('id')->limit(100)->get();
            $items = $rows->map(function ($row) {
                $p = json_decode(Crypt::decryptString($row->payload), true, flags: JSON_THROW_ON_ERROR);

                return ['id' => $row->id, 'code' => $row->code, 'name_masked' => mb_substr($p['name'], 0, 1).'***'];
            })->all();
            $this->audit($staff, 'patient.options.read', $staff->branch_id, ['count' => count($items)]);

            return ['items' => $items, 'next' => $rows->count() === 100 ? $rows->last()->id : null, 'clinical_access' => $scope['staff']->role === 'pharmacist' || ($scope['staff']->role === 'manager' && $scope['staff']->pharmacist_qualified)];
        });
    }

    public function save(Staff $staff, array $input, ?string $id = null): array
    {
        return DB::transaction(function () use ($staff, $input, $id) {
            $scope = $this->scope($staff, true);
            $today = now($scope['branch']->timezone)->toDateString();
            $d = validator(['command' => $input], ['command' => 'required|array:request_key,expected_version,synthetic_confirmed,profile', 'command.request_key' => 'required|uuid', 'command.expected_version' => 'required|integer|min:0', 'command.synthetic_confirmed' => 'required|accepted', 'command.profile' => 'required|array:name,birth_date,phone,allergies', 'command.profile.name' => 'required|string|max:150', 'command.profile.birth_date' => 'nullable|date_format:Y-m-d|before_or_equal:'.$today, 'command.profile.phone' => ['nullable', 'string', 'regex:/^\+?[0-9 ()-]{6,30}$/D'], 'command.profile.allergies' => 'present|nullable|string|max:4000'])->validate()['command'];
            $d['expected_version'] = (int) $d['expected_version'];
            $d['synthetic_confirmed'] = true;
            $operation = $id === null ? 'profile.create' : 'profile.update';
            $hash = $this->hash(['operation' => $operation, 'patient_id' => $id, 'command' => $d]);
            $old = DB::table('pos_patient_revisions')->where('branch_id', $staff->branch_id)->where('request_key', $d['request_key'])->first();
            if ($old) {
                abort_unless($old->actor_id === $staff->id && $old->operation === $operation && hash_equals($old->request_hash, $hash), 409, 'Patient command identity conflict.');

                return ['id' => $old->patient_id, 'version' => (int) $old->version];
            }
            $patient = $id === null ? null : $this->row($staff, $id);
            abort_unless((int) ($patient->version ?? 0) === $d['expected_version'], 409, 'Patient record changed; reload before editing.');
            $patientId = $patient->id ?? (string) Str::orderedUuid();
            $version = 1 + (int) ($patient->version ?? 0);
            $profile = ['name' => trim($d['profile']['name']), 'birth_date' => $d['profile']['birth_date'] ?? null, 'phone' => $d['profile']['phone'] ?? null, 'allergies' => $d['profile']['allergies'] ?? '', 'synthetic' => true];
            abort_unless($profile['name'] !== '', 422, 'Patient name is required.');
            $cipher = Crypt::encryptString(CanonicalJson::encode($profile));
            $lookups = ['name_lookup' => $this->lookup($staff->branch_id, 'name', $profile['name']), 'phone_lookup' => empty($profile['phone']) ? null : $this->lookup($staff->branch_id, 'phone', $profile['phone'])];
            if ($patient) {
                DB::table('pos_patients')->where('id', $patientId)->update(['version' => $version, 'payload' => $cipher, 'updated_by' => $staff->id, 'updated_at' => now()] + $lookups);
            } else {
                DB::table('pos_patients')->insert(['id' => $patientId, 'branch_id' => $staff->branch_id, 'code' => 'PT-'.strtoupper(str_replace('-', '', $patientId)), 'version' => $version, 'payload' => $cipher, 'created_by' => $staff->id, 'updated_by' => $staff->id, 'created_at' => now(), 'updated_at' => now()] + $lookups);
            }
            DB::table('pos_patient_revisions')->insert(['id' => (string) Str::orderedUuid(), 'branch_id' => $staff->branch_id, 'patient_id' => $patientId, 'version' => $version, 'operation' => $operation, 'request_key' => $d['request_key'], 'request_hash' => $hash, 'payload' => $cipher, 'actor_id' => $staff->id, 'created_at' => now(), 'updated_at' => now()]);
            $this->audit($staff, 'patient.profile.saved', $patientId, ['version' => $version]);

            return ['id' => $patientId, 'version' => $version];
        });
    }

    public function detail(Staff $staff, string $id): array
    {
        return DB::transaction(function () use ($staff, $id) {
            $this->scope($staff, true);
            $patient = $this->row($staff, $id);
            $this->audit($staff, 'patient.detail.read', $patient->id, ['version' => (int) $patient->version]);

            return ['id' => $patient->id, 'code' => $patient->code, 'version' => (int) $patient->version, 'profile' => json_decode(Crypt::decryptString($patient->payload), true, flags: JSON_THROW_ON_ERROR)];
        });
    }

    public function timeline(Staff $staff, string $id, string $kind, ?string $after = null): array
    {
        return DB::transaction(function () use ($staff, $id, $kind, $after) {
            $this->scope($staff, true);
            $patient = $this->row($staff, $id);
            abort_unless(in_array($kind, ['revisions', 'visits', 'dispensing'], true), 404);
            if ($after !== null) {
                abort_unless(Str::isUuid($after), 422);
            }
            $rows = DB::table('pos_patient_'.$kind)->where('branch_id', $staff->branch_id)->where('patient_id', $id)->when($after, fn ($q) => $q->where('id', '>', $after))->orderBy('id')->limit(50)->get();
            $items = $rows->map(function ($row) use ($kind, $staff) {
                $item = ['id' => $row->id, 'actor_id' => $row->actor_id, 'created_at' => $row->created_at];
                if ($kind === 'dispensing') {
                    $reference = json_decode(Crypt::decryptString($row->payload), true, flags: JSON_THROW_ON_ERROR);
                    $order = DB::table('pos_orders')->where('id', $reference['order_id'])->where('branch_id', $staff->branch_id)->firstOrFail();
                    $sale = json_decode($order->snapshot, true, flags: JSON_THROW_ON_ERROR);
                    $item += ['order_id' => $order->id, 'receipt_no' => $order->receipt_no, 'total' => $order->total, 'refunded_total' => bcadd((string) DB::table('pos_refunds')->where('order_id', $order->id)->sum('total'), '0', 2), 'lines' => array_map(fn ($line) => ['name' => $line['name'], 'quantity' => $line['quantity'], 'unit' => $line['unit']], $sale['lines'])];
                } else {
                    $item['data'] = json_decode(Crypt::decryptString($row->payload), true, flags: JSON_THROW_ON_ERROR);
                    if ($kind === 'revisions') {
                        $item['version'] = (int) $row->version;
                    } else {
                        $item['supersedes_id'] = $row->supersedes_id;
                    }
                }

                return $item;
            })->all();
            $this->audit($staff, 'patient.'.$kind.'.read', $patient->id, ['count' => count($items)]);

            return ['items' => $items, 'next' => $rows->count() === 50 ? $rows->last()->id : null];
        });
    }

    public function visit(Staff $staff, string $id, array $input): array
    {
        return DB::transaction(function () use ($staff, $id, $input) {
            $scope = $this->scope($staff, true);
            $today = now($scope['branch']->timezone)->toDateString();
            $patient = $this->row($staff, $id);
            $d = validator(['command' => $input], ['command' => 'required|array:request_key,synthetic_confirmed,occurred_on,note,supersedes_id,correction_reason', 'command.request_key' => 'required|uuid', 'command.synthetic_confirmed' => 'required|accepted', 'command.occurred_on' => 'required|date_format:Y-m-d|before_or_equal:'.$today, 'command.note' => 'required|string|max:5000', 'command.supersedes_id' => 'nullable|uuid', 'command.correction_reason' => 'required_with:command.supersedes_id|nullable|string|max:1000'])->validate()['command'];
            $d['synthetic_confirmed'] = true;
            $hash = $this->hash(['patient_id' => $id, 'command' => $d]);
            $old = DB::table('pos_patient_visits')->where('branch_id', $staff->branch_id)->where('request_key', $d['request_key'])->first();
            if ($old) {
                abort_unless($old->patient_id === $id && $old->actor_id === $staff->id && hash_equals($old->request_hash, $hash), 409, 'Visit command identity conflict.');

                return ['id' => $old->id];
            }
            if (! empty($d['supersedes_id'])) {
                DB::table('pos_patient_visits')->where('branch_id', $staff->branch_id)->where('patient_id', $id)->where('id', $d['supersedes_id'])->firstOrFail();
                abort_if(DB::table('pos_patient_visits')->where('supersedes_id', $d['supersedes_id'])->exists(), 409, 'Visit already corrected; append to latest correction.');
            }
            $visitId = (string) Str::orderedUuid();
            $payload = ['occurred_on' => $d['occurred_on'], 'note' => $d['note'], 'correction_reason' => $d['correction_reason'] ?? null, 'synthetic' => true];
            DB::table('pos_patient_visits')->insert(['id' => $visitId, 'branch_id' => $staff->branch_id, 'patient_id' => $id, 'request_key' => $d['request_key'], 'request_hash' => $hash, 'supersedes_id' => $d['supersedes_id'] ?? null, 'payload' => Crypt::encryptString(CanonicalJson::encode($payload)), 'actor_id' => $staff->id, 'created_at' => now(), 'updated_at' => now()]);
            $this->audit($staff, 'patient.visit.saved', $patient->id, ['visit_id' => $visitId]);

            return ['id' => $visitId];
        });
    }

    public function linkSale(Staff $staff, string $patientId, string $orderId): void
    {
        if (DB::transactionLevel() === 0) {
            throw new \LogicException('Patient sale link requires a transaction.');
        }
        $this->scope($staff, false);
        $patient = $this->row($staff, $patientId);
        $sale = DB::table('pos_orders')->where('id', $orderId)->where('branch_id', $staff->branch_id)->where('staff_id', $staff->id)->where('status', 'completed')->firstOrFail();
        $lookup = $this->hash(['branch_id' => $staff->branch_id, 'order_id' => $sale->id]);
        $old = DB::table('pos_patient_dispensing')->where('order_lookup', $lookup)->first();
        if ($old) {
            abort_unless($old->patient_id === $patient->id, 409, 'Patient sale link is immutable.');

            return;
        }
        $linkId = (string) Str::orderedUuid();
        DB::table('pos_patient_dispensing')->insert(['id' => $linkId, 'branch_id' => $staff->branch_id, 'patient_id' => $patient->id, 'order_lookup' => $lookup, 'payload' => Crypt::encryptString(CanonicalJson::encode(['order_id' => $sale->id])), 'actor_id' => $staff->id, 'created_at' => now(), 'updated_at' => now()]);
        $this->audit($staff, 'patient.sale.linked', $patient->id, ['link_id' => $linkId]);
    }
}
