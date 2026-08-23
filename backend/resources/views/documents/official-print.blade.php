<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <title>{{ $document['title'] }} {{ $document['number'] }}</title>
    <style>
        @page { size: A4; margin: 14mm; }
        * { box-sizing: border-box; }
        body { color: #111827; font-family: Tahoma, Arial, sans-serif; font-size: 12px; line-height: 1.45; margin: 0; }
        .toolbar { background: #111827; color: white; display: {{ ($pdf ?? false) ? 'none' : 'flex' }}; justify-content: space-between; left: 0; padding: 10px 18px; position: sticky; right: 0; top: 0; z-index: 2; }
        .toolbar button { background: white; border: 0; border-radius: 4px; color: #111827; cursor: pointer; font-weight: 700; padding: 6px 12px; }
        .sheet { margin: 18px auto; max-width: 794px; min-height: 1123px; padding: 28px; position: relative; }
        .watermark { color: rgba(185, 28, 28, .13); font-size: 92px; font-weight: 800; left: 50%; position: absolute; top: 45%; transform: translate(-50%, -50%) rotate(-24deg); }
        .header { display: grid; gap: 20px; grid-template-columns: 1fr 240px; }
        .brand { display: grid; gap: 12px; grid-template-columns: 72px 1fr; }
        .logo { align-items: center; border: 1px solid #d1d5db; display: flex; height: 64px; justify-content: center; width: 64px; }
        .logo img { max-height: 58px; max-width: 58px; object-fit: contain; }
        h1 { font-size: 26px; letter-spacing: 0; margin: 0 0 6px; }
        h2 { font-size: 18px; margin: 0 0 12px; text-align: right; }
        .muted { color: #6b7280; }
        .box { border: 1px solid #d1d5db; padding: 10px; }
        .meta { display: grid; grid-template-columns: 110px 1fr; row-gap: 4px; }
        .parties { display: grid; gap: 12px; grid-template-columns: 1fr 1fr; margin: 18px 0; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #d1d5db; padding: 7px; vertical-align: top; }
        th { background: #f3f4f6; text-align: left; }
        .right { text-align: right; }
        .center { text-align: center; }
        .totals { display: grid; gap: 16px; grid-template-columns: 1fr 280px; margin-top: 14px; }
        .totals table td { border-left: 0; border-right: 0; }
        .signatures { display: grid; gap: 48px; grid-template-columns: 1fr 1fr; margin-top: 64px; }
        .signature { border-top: 1px solid #111827; padding-top: 8px; text-align: center; }
        @media print {
            .toolbar { display: none; }
            .sheet { margin: 0; max-width: none; padding: 0; }
            body { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <div>{{ $document['title'] }} {{ $document['number'] }}</div>
        <button onclick="window.print()">Print / Save PDF</button>
    </div>
    <main class="sheet">
        @if ($document['void'])
            <div class="watermark">VOID</div>
        @endif

        <section class="header">
            <div class="brand">
                <div class="logo">
                    @if ($organization['logo_url'])
                        <img src="{{ $organization['logo_url'] }}" alt="{{ $organization['legal_name'] }}">
                    @else
                        <strong>LOGO</strong>
                    @endif
                </div>
                <div>
                    <h1>{{ $organization['legal_name'] }}</h1>
                    <div>{{ $organization['address'] ?: '-' }}</div>
                    <div>Tax ID: {{ $organization['tax_id'] ?: '-' }}</div>
                    <div>Branch: {{ $branch['is_head_office'] ? 'Head Office' : ($branch['code'] ? $branch['code'].' '.$branch['name'] : '-') }}</div>
                    @if ($branch['address'])
                        <div>Branch Address: {{ $branch['address'] }}</div>
                    @endif
                    <div>Phone: {{ $branch['phone'] ?: $organization['phone'] ?: '-' }} | Email: {{ $organization['email'] ?: '-' }}</div>
                </div>
            </div>
            <div>
                <h2>{{ $document['title'] }}</h2>
                <div class="meta">
                    <strong>No.</strong><span>{{ $document['number'] }}</span>
                    <strong>Issue Date</strong><span>{{ $document['issue_date'] }}</span>
                    <strong>Due / Expected</strong><span>{{ $document['due_date'] ?: '-' }}</span>
                    <strong>Status</strong><span>{{ strtoupper($document['status']) }}</span>
                    <strong>Copy</strong><span>{{ $document['copy_label'] }}</span>
                </div>
            </div>
        </section>

        <section class="parties">
            <div class="box">
                <strong>{{ $document['party_label'] }}</strong>
                <div>{{ $party['name'] }}</div>
                <div>Tax ID: {{ $party['tax_id'] ?: '-' }}</div>
                <div>{{ $party['address'] ?: '-' }}</div>
                <div>{{ $party['phone'] ?: '-' }} {{ $party['email'] ? '| '.$party['email'] : '' }}</div>
            </div>
            <div class="box">
                <strong>Tax / Payment</strong>
                <div>{{ $document['tax_wording'] }}</div>
                <div>Currency: {{ $document['currency'] }}</div>
            </div>
        </section>

        <table>
            <thead>
                <tr>
                    <th class="center" style="width: 40px;">#</th>
                    <th>Description</th>
                    <th class="right" style="width: 72px;">Qty</th>
                    <th style="width: 60px;">Unit</th>
                    <th class="right" style="width: 96px;">Unit Price</th>
                    <th class="right" style="width: 86px;">Discount</th>
                    <th class="right" style="width: 66px;">VAT</th>
                    <th class="right" style="width: 96px;">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($items as $item)
                    <tr>
                        <td class="center">{{ $loop->iteration }}</td>
                        <td>{{ $item['description'] }}</td>
                        <td class="right">{{ $item['quantity'] }}</td>
                        <td>{{ $item['unit'] ?: '-' }}</td>
                        <td class="right">{{ $item['unit_price'] }}</td>
                        <td class="right">{{ $item['discount_amount'] }}</td>
                        <td class="right">{{ $item['tax_rate'] }}%</td>
                        <td class="right">{{ $item['line_total'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <section class="totals">
            <div class="box">
                <strong>Amount in words</strong>
                <div>{{ $document['baht_text'] }}</div>
                @if ($document['notes'])
                    <p><strong>Notes:</strong> {{ $document['notes'] }}</p>
                @endif
            </div>
            <table>
                <tr><td>Subtotal</td><td class="right">{{ $document['subtotal'] }}</td></tr>
                <tr><td>Discount</td><td class="right">{{ $document['discount_amount'] }}</td></tr>
                <tr><td>VAT</td><td class="right">{{ $document['tax_amount'] }}</td></tr>
                <tr><td><strong>Total</strong></td><td class="right"><strong>{{ $document['total'] }}</strong></td></tr>
            </table>
        </section>

        <section class="signatures">
            <div class="signature">Authorized by</div>
            <div class="signature">Accepted by</div>
        </section>
    </main>
</body>
</html>
