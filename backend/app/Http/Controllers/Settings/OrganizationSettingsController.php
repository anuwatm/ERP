<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\Setting;
use App\Services\NumberSequenceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationSettingsController extends Controller
{
    public function edit(NumberSequenceService $numbers): Response
    {
        $organization = auth()->user()->organization;
        $data = $organization->only(['id', 'name', 'legal_name', 'tax_id', 'email', 'phone', 'address', 'currency', 'timezone', 'status']);
        $data['logo_url'] = Organization::formatLogoUrl($organization->logo_url);
        $formats = $this->numberingFormats($organization->id);
        $twoFactorPolicy = $this->twoFactorPolicy($organization->id);

        return Inertia::render('Settings/Organization', [
            'organization' => $data,
            'numberingFormats' => $formats,
            'numberingPreviews' => collect(array_keys($formats))->mapWithKeys(function ($docType) use ($numbers, $organization) {
                try {
                    return [$docType => $numbers->preview($organization->id, $docType)];
                } catch (\Throwable) {
                    return [$docType => 'Requires branch'];
                }
            })->all(),
            'twoFactorPolicy' => $twoFactorPolicy,
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

    public function updateNumbering(Request $request): RedirectResponse
    {
        $organization = $request->user()->organization;
        $validated = $request->validate([
            'formats' => ['required', 'array'],
            'formats.*.enabled' => ['required', 'boolean'],
            'formats.*.format' => ['required', 'string', 'max:30', 'regex:/\{SEQ:\d+\}/'],
            'formats.*.reset' => ['required', 'in:none,yearly,monthly,daily'],
            'formats.*.scope' => ['required', 'in:organization,branch'],
        ]);

        Setting::updateOrCreate(
            ['org_id' => $organization->id, 'key' => 'document_numbering.formats'],
            ['value_json' => $validated['formats'], 'updated_by' => $request->user()->id]
        );

        AuditLog::create([
            'org_id' => $organization->id,
            'actor_user_id' => $request->user()->id,
            'action' => 'organization.numbering_update',
            'entity_type' => 'organization',
            'entity_id' => $organization->id,
            'before_json' => null,
            'after_json' => $validated['formats'],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('success', 'Document numbering updated.');
    }

    public function updateTwoFactor(Request $request): RedirectResponse
    {
        $organization = $request->user()->organization;
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
            'required_for_privileged_roles' => ['required', 'boolean'],
            'allow_trusted_devices' => ['required', 'boolean'],
            'trusted_device_days' => ['required', 'integer', 'min:1', 'max:90'],
        ]);
        $before = $this->twoFactorPolicy($organization->id);

        Setting::updateOrCreate(
            ['org_id' => $organization->id, 'key' => 'security.two_factor'],
            ['value_json' => $validated, 'updated_by' => $request->user()->id]
        );

        AuditLog::create([
            'org_id' => $organization->id,
            'actor_user_id' => $request->user()->id,
            'action' => 'organization.two_factor_policy_update',
            'entity_type' => 'organization',
            'entity_id' => $organization->id,
            'before_json' => $before,
            'after_json' => $validated,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('success', 'Two-factor security policy updated.');
    }

    private function numberingFormats(string $orgId): array
    {
        $defaults = [
            'invoice' => ['enabled' => false, 'format' => '{SEQ:6}', 'reset' => 'none', 'scope' => 'organization'],
            'expense' => ['enabled' => false, 'format' => '{SEQ:6}', 'reset' => 'none', 'scope' => 'organization'],
            'supplier' => ['enabled' => false, 'format' => '{SEQ:6}', 'reset' => 'none', 'scope' => 'organization'],
            'purchase_order' => ['enabled' => false, 'format' => '{SEQ:6}', 'reset' => 'none', 'scope' => 'organization'],
            'project' => ['enabled' => false, 'format' => '{SEQ:6}', 'reset' => 'none', 'scope' => 'organization'],
            'customer' => ['enabled' => false, 'format' => '{SEQ:6}', 'reset' => 'none', 'scope' => 'organization'],
        ];
        $stored = Setting::where('org_id', $orgId)->where('key', 'document_numbering.formats')->value('value_json') ?? [];

        return array_replace_recursive($defaults, $stored);
    }

    private function twoFactorPolicy(string $orgId): array
    {
        $defaults = ['enabled' => false, 'required_for_privileged_roles' => true, 'allow_trusted_devices' => true, 'trusted_device_days' => 30];
        $stored = Setting::where('org_id', $orgId)->where('key', 'security.two_factor')->value('value_json') ?? [];

        return array_replace($defaults, $stored);
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
