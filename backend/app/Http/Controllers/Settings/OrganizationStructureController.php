<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Division;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationStructureController extends Controller
{
    public function index(): Response
    {
        $orgId = auth()->user()->org_id;

        return Inertia::render('Settings/OrganizationStructure', [
            'branches' => Branch::where('org_id', $orgId)->orderBy('code')->get(['id', 'code', 'name', 'is_head_office', 'status']),
            'divisions' => Division::where('org_id', $orgId)->orderBy('code')->get(['id', 'branch_id', 'code', 'name', 'status']),
            'departments' => Department::where('org_id', $orgId)->orderBy('code')->get(['id', 'branch_id', 'division_id', 'code', 'name', 'status']),
        ]);
    }
}
