<!doctype html>
<html lang="th">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>PromptPay {{ $invoice->invoice_no }}</title></head>
<body style="font-family:sans-serif;text-align:center;margin:32px auto;max-width:480px">
<h1>{{ $invoice->invoice_no }}</h1>
<p>THB {{ number_format($transaction->amount_minor / 100, 2) }}</p>
<img src="{{ $image }}" width="300" height="300" alt="PromptPay QR">
<p>{{ $transaction->reference }}</p>
<p>Expires: {{ $transaction->expires_at->format('Y-m-d H:i T') }}</p>
</body></html>
