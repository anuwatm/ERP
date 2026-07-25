<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationSettingsController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('Settings/Organization', [
            'organization' => auth()->user()->organization->only(['id', 'name', 'legal_name', 'tax_id', 'email', 'phone', 'address', 'currency', 'timezone', 'status']),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $organization = $request->user()->organization;
        $before = $organization->only(['name', 'legal_name', 'tax_id', 'email', 'phone', 'address']);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'tax_id' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:2000'],
        ]);

        $organization->update($validated);

        AuditLog::create([
            'org_id' => $organization->id,
            'actor_user_id' => $request->user()->id,
            'action' => 'organization.update',
            'entity_type' => 'organization',
            'entity_id' => $organization->id,
            'before_json' => $before,
            'after_json' => $organization->only(array_keys($before)),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('success', 'Organization updated.');
    }
}
