<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Division;
use App\Models\Role;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        $orgId = auth()->user()->org_id;
        $inactiveUsers = User::where('org_id', $orgId)->where('status', 'inactive')->count();
        $pendingInvites = User::where('org_id', $orgId)->where('status', 'invited')->count();
        $expiredInvites = User::where('org_id', $orgId)
            ->where('status', 'invited')
            ->where('invite_expires_at', '<', now())
            ->count();
        $sensitiveAuditEvents = AuditLog::where('org_id', $orgId)
            ->whereIn('action', ['user.role_change', 'user.disable', 'user.hierarchy_change'])
            ->where('created_at', '>=', now()->subDay())
            ->count();

        return Inertia::render('Dashboard', [
            'summary' => [
                'branches' => Branch::where('org_id', $orgId)->count(),
                'divisions' => Division::where('org_id', $orgId)->count(),
                'departments' => Department::where('org_id', $orgId)->count(),
                'users' => User::where('org_id', $orgId)->count(),
                'active_users' => User::where('org_id', $orgId)->where('status', 'active')->count(),
                'invited_users' => $pendingInvites,
                'roles' => Role::where('org_id', $orgId)->count(),
                'recent_audits' => AuditLog::where('org_id', $orgId)->count(),
            ],
            'securityAlerts' => [
                'inactive_users' => $inactiveUsers,
                'pending_invites' => $pendingInvites,
                'expired_invites' => $expiredInvites,
                'sensitive_audit_events_24h' => $sensitiveAuditEvents,
                'total' => $inactiveUsers + $expiredInvites + $sensitiveAuditEvents,
            ],
            'recentAudits' => AuditLog::where('org_id', $orgId)->latest()->limit(8)->get(['action', 'entity_type', 'created_at']),
        ]);
    }
}
