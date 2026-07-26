<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\AuditLog;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\Deal;
use App\Support\SalesAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ActivityController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateActivity($request);
        $this->assertEntityVisible($validated['entity_type'], $validated['entity_id'], $request);
        $validated['org_id'] = $request->user()->org_id;
        $validated['owner_id'] = $validated['owner_id'] ?? $request->user()->id;
        $validated['created_by'] = $request->user()->id;

        $activity = Activity::create($validated);
        $this->audit($request, 'activity.create', $activity, null, $activity->only($this->trackedFields()));

        return back()->with('success', 'Activity created.');
    }

    public function update(Request $request, Activity $activity): RedirectResponse
    {
        abort_unless($activity->org_id === $request->user()->org_id, 404);
        $this->assertEntityVisible($activity->entity_type, $activity->entity_id, $request);
        $before = $activity->only($this->trackedFields());
        $validated = $this->validateActivity($request);
        $this->assertEntityVisible($validated['entity_type'], $validated['entity_id'], $request);
        $validated['updated_by'] = $request->user()->id;

        $activity->update($validated);
        $this->audit($request, 'activity.update', $activity, $before, $activity->fresh()->only($this->trackedFields()));

        return back()->with('success', 'Activity updated.');
    }

    public function complete(Request $request, Activity $activity): RedirectResponse
    {
        abort_unless($activity->org_id === $request->user()->org_id, 404);
        $this->assertEntityVisible($activity->entity_type, $activity->entity_id, $request);
        $before = $activity->only($this->trackedFields());
        $activity->update(['completed_at' => now(), 'updated_by' => $request->user()->id]);
        $this->audit($request, 'activity.complete', $activity, $before, $activity->fresh()->only($this->trackedFields()));

        return back()->with('success', 'Activity completed.');
    }

    private function validateActivity(Request $request): array
    {
        return $request->validate([
            'entity_type' => ['required', Rule::in(Activity::ENTITY_TYPES)],
            'entity_id' => ['required', 'uuid'],
            'activity_type' => ['required', Rule::in(Activity::ACTIVITY_TYPES)],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:4000'],
            'activity_at' => ['required', 'date'],
            'follow_up_at' => ['nullable', 'date'],
            'completed_at' => ['nullable', 'date'],
            'owner_id' => ['nullable', 'uuid', Rule::exists('users', 'id')->where('org_id', $request->user()->org_id)],
        ]);
    }

    private function assertEntityVisible(string $type, string $id, Request $request): void
    {
        if ($type === 'customer') {
            $customer = Customer::where('org_id', $request->user()->org_id)->findOrFail($id);
            SalesAccess::assertCustomerVisible($customer, $request->user());

            return;
        }

        if ($type === 'deal') {
            $deal = Deal::where('org_id', $request->user()->org_id)->findOrFail($id);
            SalesAccess::assertDealVisible($deal, $request->user());

            return;
        }

        if ($type === 'contact') {
            $contact = Contact::where('org_id', $request->user()->org_id)->with('customer')->findOrFail($id);
            SalesAccess::assertCustomerVisible($contact->customer, $request->user());

            return;
        }

        abort(422, 'Invalid activity entity.');
    }

    private function trackedFields(): array
    {
        return ['entity_type', 'entity_id', 'activity_type', 'subject', 'body', 'activity_at', 'follow_up_at', 'completed_at', 'owner_id'];
    }

    private function audit(Request $request, string $action, Activity $activity, ?array $before, ?array $after): void
    {
        AuditLog::create([
            'org_id' => $activity->org_id,
            'actor_user_id' => $request->user()->id,
            'action' => $action,
            'entity_type' => 'activity',
            'entity_id' => $activity->id,
            'before_json' => $before,
            'after_json' => $after,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
