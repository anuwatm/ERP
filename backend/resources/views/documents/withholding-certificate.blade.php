<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <title>Withholding Tax Certificate {{ $expense['expense_no'] }}</title>
    <style>
        @page { size: A4; margin: 16mm; }
        body { color: #111827; font-family: Tahoma, Arial, sans-serif; font-size: 12px; line-height: 1.5; }
        h1 { font-size: 22px; margin: 0; text-align: center; }
        h2 { font-size: 16px; margin: 4px 0 18px; text-align: center; }
        .box { border: 1px solid #111827; margin-top: 12px; padding: 10px; }
        .grid { display: grid; gap: 12px; grid-template-columns: 1fr 1fr; }
        .meta { display: grid; grid-template-columns: 140px 1fr; row-gap: 5px; }
        table { border-collapse: collapse; margin-top: 14px; width: 100%; }
        th, td { border: 1px solid #111827; padding: 8px; vertical-align: top; }
        th { background: #f3f4f6; }
        .right { text-align: right; }
        .signatures { display: grid; gap: 48px; grid-template-columns: 1fr 1fr; margin-top: 72px; }
        .signature { border-top: 1px solid #111827; padding-top: 8px; text-align: center; }
    </style>
</head>
<body>
    <h1>หนังสือรับรองการหักภาษี ณ ที่จ่าย</h1>
    <h2>Withholding Tax Certificate / 50-Tawi</h2>

    <div class="grid">
        <div class="box">
            <strong>ผู้มีหน้าที่หักภาษี ณ ที่จ่าย / Payer</strong>
            <div>{{ $organization['legal_name'] }}</div>
            <div>Tax ID: {{ $organization['tax_id'] ?: '-' }}</div>
            <div>{{ $organization['address'] ?: '-' }}</div>
            <div>Phone: {{ $organization['phone'] ?: '-' }}</div>
        </div>
        <div class="box">
            <strong>ผู้ถูกหักภาษี ณ ที่จ่าย / Payee</strong>
            <div>{{ $supplier['name'] }}</div>
            <div>Tax ID: {{ $supplier['tax_id'] ?: '-' }}</div>
            <div>{{ $supplier['address'] ?: '-' }}</div>
        </div>
    </div>

    <div class="box meta">
        <strong>Document No.</strong><span>{{ $expense['expense_no'] }}</span>
        <strong>Date</strong><span>{{ $expense['date'] }}</span>
        <strong>Form</strong><span>{{ strtoupper($expense['form']) }}</span>
    </div>

    <table>
        <thead>
            <tr>
                <th>Description</th>
                <th class="right">Base Amount</th>
                <th class="right">Rate</th>
                <th class="right">Tax Withheld</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ $expense['title'] }}</td>
                <td class="right">{{ $expense['base_amount'] }}</td>
                <td class="right">{{ $expense['rate'] }}%</td>
                <td class="right">{{ $expense['withholding_amount'] }}</td>
            </tr>
        </tbody>
    </table>

    <div class="box">
        <strong>Tax withheld in words</strong>
        <div>{{ $expense['baht_text'] }}</div>
    </div>

    <div class="signatures">
        <div class="signature">Payer / Authorized Signature</div>
        <div class="signature">Payee / Receiver</div>
    </div>
</body>
</html>
