<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Contact;
use App\Models\Customer;
use App\Support\SalesAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ContactController extends Controller
{
    public function store(Request $request, Customer $customer): RedirectResponse
    {
        SalesAccess::assertCustomerVisible($customer, $request->user());
        $validated = $this->validateContact($request);
        $validated['org_id'] = $customer->org_id;
        $validated['customer_id'] = $customer->id;
        $validated['created_by'] = $request->user()->id;

        $contact = DB::transaction(function () use ($validated, $customer): Contact {
            if ($validated['is_primary'] ?? false) {
                Contact::where('org_id', $customer->org_id)->where('customer_id', $customer->id)->update(['is_primary' => false]);
            }

            return Contact::create($validated);
        });

        $this->audit($request, 'contact.create', $contact, null, $contact->only($this->trackedFields()));

        return back()->with('success', 'Contact created.');
    }

    public function update(Request $request, Contact $contact): RedirectResponse
    {
        abort_unless($contact->org_id === $request->user()->org_id, 404);
        $contact->load('customer');
        SalesAccess::assertCustomerVisible($contact->customer, $request->user());
        $before = $contact->only($this->trackedFields());
        $validated = $this->validateContact($request);
        $validated['updated_by'] = $request->user()->id;

        DB::transaction(function () use ($validated, $contact): void {
            if ($validated['is_primary'] ?? false) {
                Contact::where('org_id', $contact->org_id)->where('customer_id', $contact->customer_id)->whereKeyNot($contact->id)->update(['is_primary' => false]);
            }

            $contact->update($validated);
        });

        $this->audit($request, 'contact.update', $contact, $before, $contact->fresh()->only($this->trackedFields()));

        return back()->with('success', 'Contact updated.');
    }

    public function destroy(Request $request, Contact $contact): RedirectResponse
    {
        abort_unless($contact->org_id === $request->user()->org_id, 404);
        $contact->load('customer');
        SalesAccess::assertCustomerVisible($contact->customer, $request->user());
        abort_if($contact->deals()->exists(), 422, 'Cannot delete contact with deals.');
        $before = $contact->only($this->trackedFields());
        $contact->delete();
        $this->audit($request, 'contact.delete', $contact, $before, null);

        return back()->with('success', 'Contact deleted.');
    }

    private function validateContact(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'line_id' => ['nullable', 'string', 'max:100'],
            'is_primary' => ['boolean'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    private function trackedFields(): array
    {
        return ['customer_id', 'name', 'position', 'phone', 'email', 'line_id', 'is_primary', 'note'];
    }

    private function audit(Request $request, string $action, Contact $contact, ?array $before, ?array $after): void
    {
        AuditLog::create([
            'org_id' => $contact->org_id,
            'actor_user_id' => $request->user()->id,
            'action' => $action,
            'entity_type' => 'contact',
            'entity_id' => $contact->id,
            'before_json' => $before,
            'after_json' => $after,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
