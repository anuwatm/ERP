<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\ExchangeRate;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\OrganizationCurrency;
use App\Services\FxRevaluationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CurrencyController extends Controller
{
    public function index(Request $request): Response
    {
        $orgId = $request->user()->org_id;

        return Inertia::render('Finance/Currencies', [
            'baseCurrency' => strtoupper((string) ($request->user()->organization?->currency ?: 'THB')),
            'currencies' => OrganizationCurrency::where('org_id', $orgId)->orderBy('code')->get(),
            'rates' => ExchangeRate::where('org_id', $orgId)->latest('rate_date')->latest()->take(100)->get(),
            'exposure' => [
                'accounts_receivable' => Invoice::where('org_id', $orgId)->where('currency', '!=', $request->user()->organization?->currency ?: 'THB')->where('balance_due', '>', 0)->sum('base_balance_due'),
                'accounts_payable' => Expense::where('org_id', $orgId)->where('currency', '!=', $request->user()->organization?->currency ?: 'THB')->where('balance_due', '>', 0)->sum('base_balance_due'),
                'fcd' => BankAccount::where('org_id', $orgId)->where('currency', '!=', $request->user()->organization?->currency ?: 'THB')->sum('base_balance'),
            ],
        ]);
    }

    public function storeCurrency(Request $request): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'size:3'], 'name' => ['required', 'string', 'max:100'], 'decimal_places' => ['required', 'integer', 'between:0,6'], 'status' => ['required', Rule::in(['active', 'inactive'])]]);
        OrganizationCurrency::updateOrCreate(['org_id' => $request->user()->org_id, 'code' => strtoupper($data['code'])], $data);

        return back()->with('success', 'Currency saved.');
    }

    public function storeRate(Request $request): RedirectResponse
    {
        $orgId = $request->user()->org_id;
        $data = $request->validate(['quote_currency' => ['required', 'string', 'size:3'], 'rate_date' => ['required', 'date'], 'rate' => ['required', 'numeric', 'gt:0', 'max:999999999999.999999'], 'source' => ['nullable', 'string', 'max:50']]);
        $base = strtoupper((string) ($request->user()->organization?->currency ?: 'THB'));
        abort_if($base === strtoupper($data['quote_currency']), 422, 'Quote currency must differ from base currency.');
        ExchangeRate::updateOrCreate(['org_id' => $orgId, 'base_currency' => $base, 'quote_currency' => strtoupper($data['quote_currency']), 'rate_date' => $data['rate_date']], array_merge($data, ['base_currency' => $base, 'quote_currency' => strtoupper($data['quote_currency']), 'created_by' => $request->user()->id]));

        return back()->with('success', 'Exchange rate saved.');
    }

    public function revalue(Request $request, FxRevaluationService $fx): RedirectResponse
    {
        $data = $request->validate(['month' => ['required', 'date_format:Y-m']]);
        $count = $fx->revalueReceivables($request->user()->org_id, $data['month'], $request->user()->id)
            + $fx->revaluePayables($request->user()->org_id, $data['month'], $request->user()->id)
            + $fx->revalueFcd($request->user()->org_id, $data['month'], $request->user()->id);

        return back()->with('success', "Posted {$count} FX revaluation(s).");
    }

    public function reverse(Request $request, FxRevaluationService $fx): RedirectResponse
    {
        $data = $request->validate(['month' => ['required', 'date_format:Y-m']]);
        $count = $fx->reverseMonth($request->user()->org_id, $data['month'], $request->user()->id);

        return back()->with('success', "Reversed {$count} FX revaluation(s).");
    }
}
