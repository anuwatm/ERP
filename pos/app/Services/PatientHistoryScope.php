<?php

namespace Pos\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Pos\Models\Staff;

/** Branch isolation foundation only. Clinical permissions/data policy remain a separate gate. */
final class PatientHistoryScope
{
    /** @return array{staff: Staff, branch: object} */
    public function lock(Staff $sessionStaff): array
    {
        abort_unless(app()->environment(['local', 'testing'])
            && config('pos.local_pharmacy_enabled') === true
            && config('pos.local_patient_history_enabled') === true
            && Str::isUuid(config('pos.instance_id')), 503, 'Patient history capability disabled.');

        if (DB::transactionLevel() === 0) {
            throw new \LogicException('Patient history scope requires a transaction.');
        }

        $staff = Staff::whereKey($sessionStaff->id)->lockForUpdate()->first();
        abort_unless($staff && $staff->active && $staff->branch_id === $sessionStaff->branch_id && $staff->role === $sessionStaff->role, 403, 'Current staff scope required.');
        $branch = DB::table('pos_branches')->where('id', $staff->branch_id)->where('active', true)->where('business_type', 'pharmacy')->lockForUpdate()->first();
        abort_unless($branch, 404, 'Pharmacy capability unavailable.');

        return ['staff' => $staff, 'branch' => $branch];
    }
}
