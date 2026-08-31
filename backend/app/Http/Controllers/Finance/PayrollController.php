<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\BankAccount;
use App\Models\EmployeePayrollProfile;
use App\Models\PayrollItem;
use App\Models\PayrollRun;
use App\Models\PayrollTaxPolicy;
use App\Models\SocialSecurityPolicy;
use App\Models\User;
use App\Services\NumberSequenceService;
use App\Services\PayrollService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PayrollController extends Controller
{
    public function index(Request $request, PayrollService $payroll): Response
    {
        $user = $request->user();
        $policies = $payroll->policiesFor($user->org_id, now()->toDateString());

        return Inertia::render('Finance/Payroll', [
            'profiles' => EmployeePayrollProfile::where('org_id', $user->org_id)->with('user:id,name,email,person_id')->orderBy('user_id')->get(),
            'runs' => PayrollRun::where('org_id', $user->org_id)->with(['items.user:id,name', 'taxPolicy:id,name,effective_from', 'socialSecurityPolicy:id,name,effective_from', 'bankAccount:id,bank_name,account_name'])->latest('period_end')->get(),
            'users' => User::where('org_id', $user->org_id)->where('status', 'active')->orderBy('name')->get(['id', 'name', 'email', 'person_id']),
            'bankAccounts' => BankAccount::where('org_id', $user->org_id)->where('status', 'active')->orderBy('account_name')->get(['id', 'bank_name', 'account_name']),
            'taxPolicies' => PayrollTaxPolicy::where('org_id', $user->org_id)->latest('effective_from')->get(),
            'socialSecurityPolicies' => SocialSecurityPolicy::where('org_id', $user->org_id)->latest('effective_from')->get(),
            'currentPolicies' => ['tax_id' => $policies['tax']->id, 'social_security_id' => $policies['social']->id],
            'can' => ['manage' => $user->hasPermissionCode('payroll.manage'), 'approve' => $user->hasPermissionCode('payroll.approve'), 'pay' => $user->hasPermissionCode('payroll.pay'), 'export' => $user->hasPermissionCode('payroll.export')],
        ]);
    }

    public function storeProfile(Request $request): RedirectResponse
    {
        $orgId = $request->user()->org_id;
        $data = $request->validate([
            'user_id' => ['required', 'uuid', Rule::exists('users', 'id')->where('org_id', $orgId)],
            'monthly_salary' => ['required', 'numeric', 'min:0', 'max:999999999999.99'], 'fixed_allowance' => ['nullable', 'numeric', 'min:0'], 'fixed_deduction' => ['nullable', 'numeric', 'min:0'], 'annual_tax_allowance' => ['nullable', 'numeric', 'min:0'],
            'tax_id' => ['nullable', 'string', 'max:20'], 'social_security_enabled' => ['boolean'], 'payment_method' => ['required', Rule::in(['bank_transfer', 'cash'])], 'payment_reference' => ['nullable', 'string', 'max:100'], 'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);
        $profile = EmployeePayrollProfile::updateOrCreate(['org_id' => $orgId, 'user_id' => $data['user_id']], array_merge($data, ['org_id' => $orgId, 'fixed_allowance' => $data['fixed_allowance'] ?? 0, 'fixed_deduction' => $data['fixed_deduction'] ?? 0, 'annual_tax_allowance' => $data['annual_tax_allowance'] ?? 60000, 'social_security_enabled' => $request->boolean('social_security_enabled'), 'updated_by' => $request->user()->id]));
        $this->audit($request, 'payroll_profile.upsert', 'employee_payroll_profile', $profile->id, $profile->only(['user_id', 'monthly_salary', 'status']));

        return back()->with('success', 'Employee payroll profile saved.');
    }

    public function storeRun(Request $request, PayrollService $payroll, NumberSequenceService $numbers): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate(['period_start' => ['required', 'date'], 'period_end' => ['required', 'date', 'after_or_equal:period_start'], 'payment_date' => ['required', 'date'], 'bank_account_id' => ['nullable', 'uuid', Rule::exists('bank_accounts', 'id')->where('org_id', $user->org_id)->where('status', 'active')]]);
        $policies = $payroll->policiesFor($user->org_id, $data['period_end']);
        $run = PayrollRun::create(array_merge($data, ['org_id' => $user->org_id, 'run_no' => $numbers->next($user->org_id, 'payroll'), 'payroll_tax_policy_id' => $policies['tax']->id, 'social_security_policy_id' => $policies['social']->id, 'currency' => 'THB', 'status' => 'draft', 'created_by' => $user->id]));
        $this->audit($request, 'payroll_run.create', 'payroll_run', $run->id, $run->only(['run_no', 'period_start', 'period_end']));

        return back()->with('success', "Payroll {$run->run_no} created.");
    }

    public function calculate(Request $request, PayrollRun $payrollRun, PayrollService $payroll): RedirectResponse
    {
        $this->owned($request, $payrollRun);
        $payroll->calculate($payrollRun);
        $this->audit($request, 'payroll_run.calculate', 'payroll_run', $payrollRun->id, []);

        return back()->with('success', 'Payroll calculated from locked policy versions.');
    }

    public function approve(Request $request, PayrollRun $payrollRun, PayrollService $payroll): RedirectResponse
    {
        $this->owned($request, $payrollRun);
        $payroll->approve($payrollRun, $request->user()->id);
        $this->audit($request, 'payroll_run.approve', 'payroll_run', $payrollRun->id, []);

        return back()->with('success', 'Payroll approved and posted to GL.');
    }

    public function pay(Request $request, PayrollRun $payrollRun, PayrollService $payroll): RedirectResponse
    {
        $this->owned($request, $payrollRun);
        $payroll->markPaid($payrollRun, $request->user()->id);
        $this->audit($request, 'payroll_run.pay', 'payroll_run', $payrollRun->id, []);

        return back()->with('success', 'Payroll payment posted. Vendor payments were not used.');
    }

    public function storePolicies(Request $request): RedirectResponse
    {
        $orgId = $request->user()->org_id;
        $data = $request->validate(['effective_from' => ['required', 'date'], 'employment_expense_rate' => ['required', 'numeric', 'min:0', 'max:100'], 'employment_expense_cap' => ['required', 'numeric', 'min:0'], 'brackets_json' => ['required', 'json'], 'employee_rate' => ['required', 'numeric', 'min:0', 'max:100'], 'employer_rate' => ['required', 'numeric', 'min:0', 'max:100'], 'wage_ceiling' => ['required', 'numeric', 'min:0'], 'source_url' => ['nullable', 'url', 'max:1000']]);
        $brackets = json_decode($data['brackets_json'], true, flags: JSON_THROW_ON_ERROR);
        abort_unless(is_array($brackets) && count($brackets) > 0, 422, 'Tax brackets must be a non-empty JSON array.');
        DB::transaction(function () use ($request, $orgId, $data, $brackets): void {
            PayrollTaxPolicy::create(['org_id' => $orgId, 'name' => 'Configured tax policy '.$data['effective_from'], 'effective_from' => $data['effective_from'], 'employment_expense_rate' => $data['employment_expense_rate'], 'employment_expense_cap' => $data['employment_expense_cap'], 'brackets_json' => $brackets, 'source_url' => $data['source_url'] ?? null, 'updated_by' => $request->user()->id]);
            SocialSecurityPolicy::create(['org_id' => $orgId, 'name' => 'Configured social security policy '.$data['effective_from'], 'effective_from' => $data['effective_from'], 'employee_rate' => $data['employee_rate'], 'employer_rate' => $data['employer_rate'], 'wage_ceiling' => $data['wage_ceiling'], 'source_url' => $data['source_url'] ?? null, 'updated_by' => $request->user()->id]);
        });

        return back()->with('success', 'New effective-dated payroll policy version saved.');
    }

    public function export(Request $request, PayrollRun $payrollRun, string $type): StreamedResponse
    {
        $this->owned($request, $payrollRun);
        abort_unless(in_array($type, ['pnd1', 'social-security'], true), 404);
        $payrollRun->load('items.user', 'items.profile');

        return response()->streamDownload(function () use ($payrollRun, $type): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, $type === 'pnd1' ? ['period_end', 'employee_name', 'tax_id', 'gross_income', 'withholding_tax'] : ['period_end', 'employee_name', 'employee_contribution', 'employer_contribution']);
            foreach ($payrollRun->items as $item) {
                fputcsv($out, $type === 'pnd1' ? [$payrollRun->period_end->toDateString(), $item->user->name, $item->profile->tax_id ?: $item->user->person_id, $item->salary_amount + $item->allowance_amount, $item->withholding_tax_amount] : [$payrollRun->period_end->toDateString(), $item->user->name, $item->employee_social_security_amount, $item->employer_social_security_amount]);
            }
            fclose($out);
        }, "{$type}-{$payrollRun->run_no}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function printPayslip(Request $request, PayrollItem $payrollItem): View
    {
        abort_unless($payrollItem->org_id === $request->user()->org_id, 404);
        abort_unless($payrollItem->user_id === $request->user()->id || $request->user()->hasPermissionCode('payroll.view'), 403);
        $payrollItem->load(['run', 'user', 'profile']);

        return view('payroll.payslip', ['item' => $payrollItem, 'organization' => $request->user()->organization]);
    }

    public function payslipPdf(Request $request, PayrollItem $payrollItem)
    {
        $view = $this->printPayslip($request, $payrollItem);

        return Pdf::loadHTML($view->render())->setPaper('a4')->download("payslip-{$payrollItem->run->run_no}.pdf");
    }

    private function owned(Request $request, PayrollRun $run): void
    {
        abort_unless($run->org_id === $request->user()->org_id, 404);
    }

    private function audit(Request $request, string $action, string $type, string $id, array $after): void
    {
        AuditLog::create(['org_id' => $request->user()->org_id, 'actor_user_id' => $request->user()->id, 'action' => $action, 'entity_type' => $type, 'entity_id' => $id, 'after_json' => $after, 'request_id' => (string) Str::uuid()]);
    }
}
