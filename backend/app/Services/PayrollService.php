<?php

namespace App\Services;

use App\Models\EmployeePayrollProfile;
use App\Models\PayrollItem;
use App\Models\PayrollRun;
use App\Models\PayrollTaxPolicy;
use App\Models\SocialSecurityPolicy;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PayrollService
{
    public function __construct(private readonly FinancialJournalService $journals) {}

    /** @return array{tax: PayrollTaxPolicy, social: SocialSecurityPolicy} */
    public function policiesFor(string $orgId, string $date): array
    {
        $this->ensurePolicies($orgId);
        $tax = PayrollTaxPolicy::where('org_id', $orgId)->whereDate('effective_from', '<=', $date)->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date))->latest('effective_from')->firstOrFail();
        $social = SocialSecurityPolicy::where('org_id', $orgId)->whereDate('effective_from', '<=', $date)->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date))->latest('effective_from')->firstOrFail();

        return compact('tax', 'social');
    }

    public function calculate(PayrollRun $run): PayrollRun
    {
        return DB::transaction(function () use ($run): PayrollRun {
            $run = PayrollRun::with(['taxPolicy', 'socialSecurityPolicy'])->whereKey($run->id)->lockForUpdate()->firstOrFail();
            if (! in_array($run->status, ['draft', 'calculated'], true)) {
                throw ValidationException::withMessages(['payroll' => 'Only draft or calculated payroll runs can be calculated.']);
            }
            $profiles = EmployeePayrollProfile::where('org_id', $run->org_id)->where('status', 'active')->with('user:id,name,person_id')->get();
            if ($profiles->isEmpty()) {
                throw ValidationException::withMessages(['payroll' => 'Create at least one active employee payroll profile first.']);
            }

            $run->items()->delete();
            $totals = ['gross' => 0.0, 'employee_ss' => 0.0, 'employer_ss' => 0.0, 'tax' => 0.0, 'net' => 0.0];
            foreach ($profiles as $profile) {
                $item = $this->itemPayload($run, $profile);
                PayrollItem::create($item);
                $totals['gross'] += $item['salary_amount'] + $item['allowance_amount'];
                $totals['employee_ss'] += $item['employee_social_security_amount'];
                $totals['employer_ss'] += $item['employer_social_security_amount'];
                $totals['tax'] += $item['withholding_tax_amount'];
                $totals['net'] += $item['net_pay_amount'];
            }
            $run->update([
                'status' => 'calculated',
                'gross_amount' => round($totals['gross'], 2),
                'employee_social_security_amount' => round($totals['employee_ss'], 2),
                'employer_social_security_amount' => round($totals['employer_ss'], 2),
                'withholding_tax_amount' => round($totals['tax'], 2),
                'net_pay_amount' => round($totals['net'], 2),
            ]);

            return $run->fresh('items.user');
        });
    }

    public function approve(PayrollRun $run, string $actorId): PayrollRun
    {
        return DB::transaction(function () use ($run, $actorId): PayrollRun {
            $run = PayrollRun::with(['taxPolicy', 'socialSecurityPolicy'])->whereKey($run->id)->lockForUpdate()->firstOrFail();
            if ($run->status !== 'calculated') {
                throw ValidationException::withMessages(['payroll' => 'Calculate the payroll run before approval.']);
            }
            $this->journals->postPayrollApproval($run, $actorId);
            $run->update(['status' => 'approved', 'approved_by' => $actorId, 'approved_at' => now()]);

            return $run->fresh('items.user');
        });
    }

    public function markPaid(PayrollRun $run, string $actorId): PayrollRun
    {
        return DB::transaction(function () use ($run, $actorId): PayrollRun {
            $run = PayrollRun::whereKey($run->id)->lockForUpdate()->firstOrFail();
            if ($run->status !== 'approved') {
                throw ValidationException::withMessages(['payroll' => 'Only approved payroll runs can be marked paid.']);
            }
            $this->journals->postPayrollPayment($run, $actorId);
            $run->update(['status' => 'paid', 'paid_by' => $actorId, 'paid_at' => now()]);

            return $run->fresh('items.user');
        });
    }

    private function ensurePolicies(string $orgId): void
    {
        if (! PayrollTaxPolicy::where('org_id', $orgId)->exists()) {
            PayrollTaxPolicy::create([
                'org_id' => $orgId, 'name' => 'Thailand PIT 2026 default', 'effective_from' => '2026-01-01',
                'employment_expense_rate' => 50, 'employment_expense_cap' => 100000,
                'brackets_json' => [['up_to' => 150000, 'rate' => 0], ['up_to' => 300000, 'rate' => 5], ['up_to' => 500000, 'rate' => 10], ['up_to' => 750000, 'rate' => 15], ['up_to' => 1000000, 'rate' => 20], ['up_to' => 2000000, 'rate' => 25], ['up_to' => 5000000, 'rate' => 30], ['up_to' => null, 'rate' => 35]],
                'source_url' => 'https://www.rd.go.th/26218.html',
            ]);
        }
        if (! SocialSecurityPolicy::where('org_id', $orgId)->exists()) {
            SocialSecurityPolicy::create([
                'org_id' => $orgId, 'name' => 'Section 33 2026 default', 'effective_from' => '2026-01-01',
                'employee_rate' => 5, 'employer_rate' => 5, 'wage_ceiling' => 17500,
                'source_url' => 'https://www.hrm.chula.ac.th/th/home/social-security-fund',
            ]);
        }
    }

    private function itemPayload(PayrollRun $run, EmployeePayrollProfile $profile): array
    {
        $salary = round((float) $profile->monthly_salary, 2);
        $allowance = round((float) $profile->fixed_allowance, 2);
        $gross = $salary + $allowance;
        $socialPolicy = $run->socialSecurityPolicy;
        $employeeSs = $profile->social_security_enabled ? round(min($gross, (float) $socialPolicy->wage_ceiling) * (float) $socialPolicy->employee_rate / 100, 2) : 0;
        $employerSs = $profile->social_security_enabled ? round(min($gross, (float) $socialPolicy->wage_ceiling) * (float) $socialPolicy->employer_rate / 100, 2) : 0;
        $annualIncome = $gross * 12;
        $taxPolicy = $run->taxPolicy;
        $expense = min($annualIncome * (float) $taxPolicy->employment_expense_rate / 100, (float) $taxPolicy->employment_expense_cap);
        $annualTaxable = max(0, $annualIncome - $expense - (float) $profile->annual_tax_allowance - ($employeeSs * 12));
        $withholding = round($this->progressiveTax($annualTaxable, $taxPolicy->brackets_json) / 12, 2);
        $other = round((float) $profile->fixed_deduction, 2);
        $net = max(0, round($gross - $other - $employeeSs - $withholding, 2));

        return ['org_id' => $run->org_id, 'payroll_run_id' => $run->id, 'employee_payroll_profile_id' => $profile->id, 'user_id' => $profile->user_id, 'salary_amount' => $salary, 'allowance_amount' => $allowance, 'other_deduction_amount' => $other, 'employee_social_security_amount' => $employeeSs, 'employer_social_security_amount' => $employerSs, 'withholding_tax_amount' => $withholding, 'net_pay_amount' => $net, 'calculation_snapshot' => ['annual_income' => $annualIncome, 'employment_expense' => $expense, 'annual_taxable_income' => $annualTaxable, 'tax_policy_id' => $taxPolicy->id, 'social_security_policy_id' => $socialPolicy->id, 'calculated_at' => Carbon::now()->toIso8601String()]];
    }

    private function progressiveTax(float $income, array $brackets): float
    {
        $tax = 0.0;
        $lower = 0.0;
        foreach ($brackets as $bracket) {
            $upper = $bracket['up_to'] ?? null;
            $portion = $upper === null ? max(0, $income - $lower) : max(0, min($income, (float) $upper) - $lower);
            $tax += $portion * (float) $bracket['rate'] / 100;
            if ($upper === null || $income <= $upper) {
                break;
            }
            $lower = (float) $upper;
        }

        return round($tax, 2);
    }
}
