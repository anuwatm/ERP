<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        $orgId = auth()->user()->org_id;
        $filters = $request->validate([
            'audit_id' => ['nullable', 'uuid'],
            'action' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'window' => ['nullable', 'in:24h'],
            'sensitive' => ['nullable', 'in:1'],
        ]);

        return Inertia::render('Admin/AuditLogs', [
            'auditLogs' => AuditLog::where('org_id', $orgId)
                ->when($filters['audit_id'] ?? null, fn ($query, $auditId) => $query->whereKey($auditId))
                ->when($filters['action'] ?? null, fn ($query, $action) => $query->where('action', $action))
                ->when($filters['from'] ?? null, fn ($query, $from) => $query->whereDate('created_at', '>=', $from))
                ->when($filters['to'] ?? null, fn ($query, $to) => $query->whereDate('created_at', '<=', $to))
                ->when(($filters['window'] ?? null) === '24h', fn ($query) => $query->where('created_at', '>=', now()->subDay()))
                ->when(($filters['sensitive'] ?? null) === '1', fn ($query) => $query->whereIn('action', ['user.role_change', 'user.disable', 'user.hierarchy_change']))
                ->with('actorUser:id,name,email')
                ->latest()
                ->limit(100)
                ->get(['id', 'actor_user_id', 'action', 'entity_type', 'entity_id', 'before_json', 'after_json', 'created_at'])
                ->map(fn (AuditLog $log) => [
                    'id' => $log->id,
                    'action' => $log->action,
                    'entity_type' => $log->entity_type,
                    'entity_id' => $log->entity_id,
                    'before_json' => $log->before_json,
                    'after_json' => $log->after_json,
                    'created_at' => $log->created_at?->toISOString(),
                    'actor' => $log->actorUser ? [
                        'id' => $log->actorUser->id,
                        'name' => $log->actorUser->name,
                        'email' => $log->actorUser->email,
                    ] : null,
                ]),
            'filters' => $filters,
        ]);
    }
}
