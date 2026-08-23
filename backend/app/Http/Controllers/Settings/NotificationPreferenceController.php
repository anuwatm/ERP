<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\NotificationPreference;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class NotificationPreferenceController extends Controller
{
    private const TYPES = [
        'purchase_order.pending_approval' => 'PO pending approval',
        'invoice.due_soon' => 'Invoice due soon',
        'invoice.overdue' => 'Invoice overdue',
        'task.assigned' => 'Task assigned',
        'project.member_assigned' => 'Project member assigned',
        'user.invite' => 'User invitation',
    ];

    public function edit(Request $request): Response
    {
        $user = $request->user();
        $stored = NotificationPreference::where('user_id', $user->id)->get()->keyBy('type');

        return Inertia::render('Settings/NotificationPreferences', [
            'preferences' => collect(self::TYPES)->map(fn (string $label, string $type) => [
                'type' => $type,
                'label' => $label,
                'email_enabled' => $stored[$type]->email_enabled ?? true,
                'in_app_enabled' => $stored[$type]->in_app_enabled ?? true,
            ])->values(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $types = array_keys(self::TYPES);
        $validated = $request->validate([
            'preferences' => ['required', 'array'],
            'preferences.*.type' => ['required', Rule::in($types)],
            'preferences.*.email_enabled' => ['required', 'boolean'],
            'preferences.*.in_app_enabled' => ['required', 'boolean'],
        ]);

        foreach ($validated['preferences'] as $preference) {
            NotificationPreference::updateOrCreate(
                ['user_id' => $user->id, 'type' => $preference['type']],
                [
                    'org_id' => $user->org_id,
                    'email_enabled' => $preference['email_enabled'],
                    'in_app_enabled' => $preference['in_app_enabled'],
                ]
            );
        }

        return back()->with('success', 'Notification preferences updated.');
    }
}
