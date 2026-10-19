<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\ExternalApiResponse;
use App\Support\ExternalApprovalAccess;
use App\Support\ExternalReadPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ExternalApprovalController extends Controller
{
    private const FIELDS = ['id', 'workflow_instance_id', 'step_no', 'status', 'acted_at', 'updated_at'];

    public function index(Request $request): JsonResponse
    {
        $filters = ExternalReadPagination::filters($request, ['updated_since' => ['prohibited'], 'status' => ['sometimes', Rule::in(['queued', 'pending', 'approved', 'rejected', 'revision_requested'])],
            'workflow_instance_id' => ['sometimes', 'uuid']]);
        $query = ExternalApprovalAccess::query($request)->with('instance:id,subject_type,subject_id,status');
        foreach (['status', 'workflow_instance_id'] as $field) {
            if (isset($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }

        return ExternalReadPagination::page($request, $query, self::FIELDS, $filters, fn ($row) => $this->serialize($row), false);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        ExternalReadPagination::filters($request, ['limit' => ['prohibited'], 'cursor' => ['prohibited'], 'updated_since' => ['prohibited']]);
        $row = ExternalApprovalAccess::query($request)->with('instance:id,subject_type,subject_id,status')->whereKey($id)->firstOrFail(self::FIELDS);

        return response()->json(['data' => $this->serialize($row), 'meta' => ['api_version' => 'v1', 'request_id' => ExternalApiResponse::requestId($request)]]);
    }

    private function serialize($row): array
    {
        return $row->only(self::FIELDS) + ['subject' => $row->instance?->only(['subject_type', 'subject_id', 'status'])];
    }
}
