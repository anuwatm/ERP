<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function index(): Response
    {
        $orgId = auth()->user()->org_id;

        return Inertia::render('Admin/AuditLogs', [
            'auditLogs' => AuditLog::where('org_id', $orgId)
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
        ]);
    }
}
