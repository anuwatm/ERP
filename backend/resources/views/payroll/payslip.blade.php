<!doctype html><html><head><meta charset="utf-8"><style>body{font-family:DejaVu Sans,sans-serif;font-size:12px;color:#111}table{width:100%;border-collapse:collapse;margin-top:18px}td,th{border:1px solid #777;padding:7px;text-align:left}.right{text-align:right}.muted{color:#555}</style></head><body>
<h2>{{ $organization?->legal_name ?: $organization?->name ?: 'Organization' }}</h2><h3>Payroll Payslip</h3>
<p>
    <strong>Employee:</strong> {{ $item->employee ? trim($item->employee->first_name . ' ' . ($item->employee->last_name ?? '')) : ($item->user?->name ?? 'Employee') }}
    @if($item->employee?->employee_code) ({{ $item->employee->employee_code }}) @endif<br>
    <strong>Run:</strong> {{ $item->run->run_no }}<br>
    <strong>Period:</strong> {{ $item->run->period_start->format('Y-m-d') }} to {{ $item->run->period_end->format('Y-m-d') }}
</p>
<table>
    <tr><th>Earnings</th><th class="right">Amount (THB)</th><th>Deductions</th><th class="right">Amount (THB)</th></tr>
    <tr><td>Salary / Wages</td><td class="right">{{ number_format($item->salary_amount,2) }}</td><td>Other deduction</td><td class="right">{{ number_format($item->other_deduction_amount,2) }}</td></tr>
    <tr><td>Allowance</td><td class="right">{{ number_format($item->allowance_amount,2) }}</td><td>Social security</td><td class="right">{{ number_format($item->employee_social_security_amount,2) }}</td></tr>
    <tr><td>Overtime (OT)</td><td class="right">{{ number_format($item->ot_amount ?? 0,2) }}</td><td>Withholding tax</td><td class="right">{{ number_format($item->withholding_tax_amount,2) }}</td></tr>
    <tr><td></td><td></td><td>Late / Absent deduction</td><td class="right">{{ number_format($item->late_deduction_amount ?? 0,2) }}</td></tr>
    <tr><th>Net pay</th><th class="right">{{ number_format($item->net_pay_amount,2) }}</th><td colspan="2" class="muted">Employer social security: {{ number_format($item->employer_social_security_amount,2) }}</td></tr>
</table>
</body></html>
