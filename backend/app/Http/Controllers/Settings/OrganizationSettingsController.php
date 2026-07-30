<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationSettingsController extends Controller
{
    public function edit(): Response
    {
        $organization = auth()->user()->organization;
        $data = $organization->only(['id', 'name', 'legal_name', 'tax_id', 'email', 'phone', 'address', 'currency', 'timezone', 'status']);
        $data['logo_url'] = Organization::formatLogoUrl($organization->logo_url);

        return Inertia::render('Settings/Organization', [
            'organization' => $data,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $organization = $request->user()->organization;
        $trackedFields = ['name', 'legal_name', 'tax_id', 'email', 'phone', 'address', 'logo_url'];
        $before = $organization->only($trackedFields);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'tax_id' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:2000'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        unset($validated['logo']);

        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('org-logos/'.$organization->id, 'public');
            $validated['logo_url'] = Storage::disk('public')->url($path);
            $this->deleteOldLogo($before['logo_url'] ?? null);
        }

        $organization->update($validated);

        AuditLog::create([
            'org_id' => $organization->id,
            'actor_user_id' => $request->user()->id,
            'action' => 'organization.update',
            'entity_type' => 'organization',
            'entity_id' => $organization->id,
            'before_json' => $before,
            'after_json' => $organization->only($trackedFields),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('success', 'Organization updated.');
    }

    private function deleteOldLogo(?string $logoUrl): void
    {
        if (! $logoUrl || ! str_contains($logoUrl, '/storage/org-logos/')) {
            return;
        }

        $path = substr($logoUrl, strpos($logoUrl, '/storage/') + strlen('/storage/'));
        Storage::disk('public')->delete($path);
    }
}
