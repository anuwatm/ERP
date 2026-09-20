<?php

namespace App\Http\Controllers;

use App\Models\LeaveRequest;
use App\Models\PayrollRun;
use App\Support\DashboardDate;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HrDashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $canHr = $user->hasPermissionCode('hr.manage') && $user->hasPermissionCode('hr.self.view');
        $canPayroll = $user->hasPermissionCode('payroll.view');
        abort_unless($canHr || $canPayroll, 403);

        $pendingQuery = LeaveRequest::where('org_id', $user->org_id)->where('status', 'submitted');
        $pendingCount = $canHr ? (clone $pendingQuery)->count() : null;
        $pending = $canHr ? (clone $pendingQuery)->with('user:id,name')->latest('submitted_at')->limit(10)
            ->get(['id', 'org_id', 'user_id', 'starts_on', 'ends_on', 'status', 'submitted_at']) : collect();
        $onLeave = $canHr ? LeaveRequest::where('org_id', $user->org_id)->where('status', 'approved')
            ->whereDate('starts_on', '<=', DashboardDate::now()->startOfDay())->whereDate('ends_on', '>=', DashboardDate::now()->startOfDay())
            ->with('user:id,name')->orderBy('id')->limit(10)->get(['id', 'org_id', 'user_id', 'starts_on', 'ends_on', 'status']) : collect();
        $latestRun = $canPayroll ? PayrollRun::where('org_id', $user->org_id)
            ->latest('period_end')->first(['id', 'run_no', 'period_start', 'period_end', 'status', 'currency']) : null;

        return Inertia::render('Hr/Dashboard', [
            'can' => ['hr' => $canHr, 'payroll' => $canPayroll],
            'pendingLeaveCount' => $pendingCount,
            'pendingLeave' => $canHr ? $pending->map(fn ($leave) => [
                'id' => $leave->id, 'employee' => $leave->user?->name,
                'starts_on' => $leave->starts_on?->toDateString(), 'ends_on' => $leave->ends_on?->toDateString(),
            ])->values() : null,
            'onLeaveToday' => $canHr ? $onLeave->map(fn ($leave) => [
                'id' => $leave->id, 'employee' => $leave->user?->name,
                'starts_on' => $leave->starts_on?->toDateString(), 'ends_on' => $leave->ends_on?->toDateString(),
            ])->values() : null,
            'latestPayrollRun' => $canPayroll && $latestRun ? [
                'id' => $latestRun->id, 'run_no' => $latestRun->run_no,
                'period_end' => $latestRun->period_end?->toDateString(), 'status' => $latestRun->status,
            ] : null,
        ]);
    }
}
