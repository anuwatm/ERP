<?php

namespace App\Support;

use App\Models\Expense;
use App\Models\LeaveRequest;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\WorkflowApproval;
use App\Models\WorkflowDelegation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

final class ExternalApprovalAccess
{
    public static function query(Request $request): Builder
    {
        $issuer = ExternalBusinessAccess::issuer($request, 'approvals:read');
        $org = $issuer->org_id;
        $delegations = WorkflowDelegation::where('org_id', $org)->where('delegate_user_id', $issuer->id)->where('is_active', true)
            ->where('starts_at', '<=', now())->where('ends_at', '>=', now())->get(['delegator_user_id', 'subject_type']);
        $query = WorkflowApproval::whereHas('instance', function ($instances) use ($org) {
            $instances->where('org_id', $org)->where(function ($parents) use ($org) {
                foreach (['leave_request' => LeaveRequest::class, 'purchase_request' => PurchaseRequest::class, 'purchase_order' => PurchaseOrder::class, 'expense' => Expense::class] as $type => $model) {
                    $parents->orWhere(fn ($q) => $q->where('subject_type', $type)->whereIn('subject_id', $model::where('org_id', $org)->select('id')));
                }
            });
        });
        $query->where(function ($access) use ($issuer, $delegations) {
            $access->where('assigned_user_id', $issuer->id)->orWhere('acted_by_user_id', $issuer->id);
            foreach ($delegations as $delegation) {
                $access->orWhere(fn ($delegated) => $delegated->where('assigned_user_id', $delegation->delegator_user_id)->where('status', 'pending')
                    ->whereHas('instance', fn ($instance) => $instance->where('status', 'pending')->when($delegation->subject_type, fn ($subject, $type) => $subject->where('subject_type', $type))));
            }
        });

        return $query;
    }
}
