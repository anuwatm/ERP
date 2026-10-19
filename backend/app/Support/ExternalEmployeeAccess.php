<?php

namespace App\Support;

use App\Models\Branch;
use App\Models\Department;
use App\Models\Division;
use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

final class ExternalEmployeeAccess
{
    public static function query(Request $request): Builder
    {
        $key = $request->attributes->get('integration_key');
        $org = $key->org_id;
        $query = Employee::where('org_id', $org);
        $policy = $key->access_policy ?? [];
        if (! ($policy['hr_all_branches'] ?? false)) {
            $query->whereIn('branch_id', $policy['hr_branch_ids'] ?? []);
        }
        foreach (['branch_id' => Branch::class, 'division_id' => Division::class, 'department_id' => Department::class, 'position_id' => Position::class, 'user_id' => User::class] as $field => $parent) {
            $query->where(fn ($q) => $q->whereNull($field)->orWhereIn($field, $parent::where('org_id', $org)->select('id')));
        }

        return $query;
    }
}
