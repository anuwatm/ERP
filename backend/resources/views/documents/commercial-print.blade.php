<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <title>{{ $title }} {{ $number }}</title>
    <style>
        @page { size: A4; margin: 16mm; }
        body { color: #111827; font-family: "DejaVu Sans", Tahoma, Arial, sans-serif; font-size: 12px; line-height: 1.5; margin: 0; }
        .toolbar { background: #111827; color: white; display: {{ ($pdf ?? false) ? 'none' : 'flex' }}; justify-content: space-between; padding: 10px 18px; }
        .toolbar button { background: white; border: 0; border-radius: 4px; color: #111827; cursor: pointer; font-weight: 700; padding: 6px 12px; }
        .sheet { margin: 18px auto; max-width: 794px; min-height: 1123px; padding: 28px; }
        h1 { font-size: 24px; margin: 0 0 4px; }
        h2 { font-size: 20px; margin: 0; text-align: right; }
        .header { display: grid; grid-template-columns: 1fr 240px; gap: 20px; }
        .box { border: 1px solid #d1d5db; margin-top: 14px; padding: 10px; }
        .meta { display: grid; grid-template-columns: 90px 1fr; row-gap: 4px; }
        table { border-collapse: collapse; margin-top: 14px; width: 100%; }
        th, td { border: 1px solid #d1d5db; padding: 8px; vertical-align: top; }
        th { background: #f3f4f6; text-align: left; }
        .right { text-align: right; }
        .signatures { display: grid; gap: 48px; grid-template-columns: 1fr 1fr; margin-top: 72px; }
        .signature { border-top: 1px solid #111827; padding-top: 8px; text-align: center; }
        @media print { .toolbar { display: none; } .sheet { margin: 0; padding: 0; } }
    </style>
</head>
<body>
    <div class="toolbar">
        <div>{{ $title }} {{ $number }}</div>
        <button onclick="window.print()">Print / Save PDF</button>
    </div>
    <main class="sheet">
        <section class="header">
            <div>
                <h1>{{ $organization['name'] }}</h1>
                <div>Tax ID: {{ $organization['tax_id'] ?: '-' }}</div>
                <div>{{ $organization['address'] ?: '-' }}</div>
            </div>
            <div>
                <h2>{{ $title }}</h2>
                <div class="meta">
                    <strong>No.</strong><span>{{ $number }}</span>
                    <strong>Date</strong><span>{{ $date }}</span>
                    <strong>Status</strong><span>{{ strtoupper($status) }}</span>
                </div>
            </div>
        </section>
        <section class="box">
            <strong>Partner</strong>
            <div>{{ $partner }}</div>
        </section>
        <table>
            <thead>
                <tr>
                    <th>Description</th>
                    <th class="right" style="width: 90px;">Qty</th>
                    <th class="right" style="width: 120px;">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        <td>{{ $row['description'] }}</td>
                        <td class="right">{{ $row['quantity'] }}</td>
                        <td class="right">{{ is_null($row['amount']) ? '-' : number_format((float) $row['amount'], 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        @if (! is_null($total))
            <table>
                <tr><td><strong>Total</strong></td><td class="right"><strong>{{ number_format((float) $total, 2) }}</strong></td></tr>
            </table>
        @endif
        <section class="signatures">
            <div class="signature">Prepared by</div>
            <div class="signature">Approved / Received by</div>
        </section>
    </main>
</body>
</html>
