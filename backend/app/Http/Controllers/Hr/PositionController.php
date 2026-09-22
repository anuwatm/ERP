<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Position;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PositionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $orgId = $request->user()->org_id;
        $positions = Position::where('org_id', $orgId)
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'department_id', 'status']);

        return response()->json($positions);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $orgId = $request->user()->org_id;

        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:150',
                Rule::unique('positions', 'name')->where('org_id', $orgId)->whereNull('deleted_at'),
            ],
            'code' => ['nullable', 'string', 'max:50'],
            'department_id' => [
                'nullable',
                Rule::exists('departments', 'id')->where('org_id', $orgId)->where('status', 'active'),
            ],
            'description' => ['nullable', 'string'],
        ]);

        $position = Position::create($data + [
            'org_id' => $orgId,
            'status' => 'active',
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        AuditLog::create([
            'org_id' => $orgId,
            'user_id' => $request->user()->id,
            'action' => 'hr.position.create',
            'entity_type' => Position::class,
            'entity_id' => $position->id,
            'metadata' => [
                'name' => $position->name,
                'code' => $position->code,
                'department_id' => $position->department_id,
            ],
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Position created.',
                'position' => $position,
            ], 201);
        }

        return back()->with('success', 'Position created.')->with('created_position', $position);
    }
}
