<?php

namespace App\Http\Middleware;

use App\Models\Contact;
use App\Models\Customer;
use App\Models\Deal;
use App\Support\SalesAccess;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/** Opt-in retry protection for explicitly enabled create routes, after auth/permission middleware. */
class EnsureFormSubmission
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('X-Form-Submission');
        if ($key === null) {
            return $next($request);
        }
        abort_unless(Str::isUuid($key), 422, 'Invalid form submission key.');
        $fingerprint = hash('sha256', json_encode([$request->user()->org_id, $request->path(), $request->except('_token')], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($request, $next, $key, $fingerprint) {
            // Serialize this actor's opt-in creates so the domain write and receipt commit atomically.
            $request->user()->newQuery()->whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $receipt = DB::table('form_submissions')->where('user_id', $request->user()->id)->where('submission_key', $key)->first();
            if ($receipt) {
                if (! hash_equals($receipt->fingerprint, $fingerprint)) {
                    throw ValidationException::withMessages(['submission' => 'This submission was already saved with different data. Reload and check the saved record before starting a new draft.']);
                }
                $this->assertReplayScope($request);

                return back()->with(json_decode($receipt->result, true, 512, JSON_THROW_ON_ERROR));
            }

            // Only success produced by this request can authorize a receipt.
            // A previous POST's flash may still exist when validation redirects.
            $request->session()->forget('success');
            $response = $next($request);
            if ($response->isRedirection() && $request->session()->has('success')) {
                $result = ['success' => $request->session()->get('success')];
                if ($request->route()->getName() === 'contacts.store') {
                    $result['created_reference'] = $request->session()->get('created_reference');
                }
                DB::table('form_submissions')->insert([
                    'user_id' => $request->user()->id,
                    'submission_key' => $key,
                    'fingerprint' => $fingerprint,
                    'result' => json_encode($result, JSON_THROW_ON_ERROR),
                    'created_at' => now(),
                ]);
            }

            return $response;
        }, 3);
    }

    private function assertReplayScope(Request $request): void
    {
        $name = $request->route()->getName();
        if ($name === 'contacts.store') {
            SalesAccess::assertCustomerVisible($request->route('customer'), $request->user());
        } elseif ($name === 'deals.store') {
            SalesAccess::assertCustomerVisible(Customer::findOrFail($request->input('customer_id')), $request->user());
        } elseif ($name === 'activities.store') {
            $id = $request->input('entity_id');
            match ($request->input('entity_type')) {
                'customer' => SalesAccess::assertCustomerVisible(Customer::findOrFail($id), $request->user()),
                'deal' => SalesAccess::assertDealVisible(Deal::findOrFail($id), $request->user()),
                'contact' => SalesAccess::assertCustomerVisible(Contact::findOrFail($id)->customer, $request->user()),
                default => abort(422),
            };
        }
    }
}
