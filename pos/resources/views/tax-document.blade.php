<!doctype html>
<html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ $document['document_no'] }}</title>
<style>body{font:16px system-ui,sans-serif;max-width:900px;margin:24px auto;padding:16px;color:#172b41}table{border-collapse:collapse;width:100%}th,td{text-align:left;padding:10px;border-bottom:1px solid #ccc}p{overflow-wrap:anywhere}.notice{background:#fff2cd;padding:16px}.actions{margin:20px 0}@media print{.actions{display:none}.notice{border:1px solid #333}body{margin:0}}@media(max-width:600px){th,td{padding:6px;font-size:13px}}</style></head><body>
<p class="notice">เอกสารทดสอบ local — ยังไม่ผ่านการตรวจรับภาษี/เครื่องพิมพ์ · e-Tax: {{ $etax }} · สำเนาสำหรับพิมพ์ซ้ำ</p>
<h1>{{ $document['kind'] === 'ABB' ? 'ใบกำกับภาษีอย่างย่อ' : 'ใบกำกับภาษี' }}</h1>
<p>{{ $document['issuer']['seller_name'] }} · เลขผู้เสียภาษี {{ $document['issuer']['seller_tax_id'] }} · สาขา {{ $document['issuer']['seller_branch_no'] }}</p>
<p>{{ $document['issuer']['seller_address'] }}</p>
<p>เลขที่ {{ $document['document_no'] }} · วันที่ {{ \Illuminate\Support\Carbon::parse($document['occurred_at'])->setTimezone('Asia/Bangkok')->format('d/m/Y H:i') }} · สถานะ {{ $status }}</p>
<p>อ้างอิงการขาย {{ $document['receipt_no'] }}</p>
@if($document['replaces_document_no'])<p>ออกแทนใบกำกับภาษีอย่างย่อ {{ $document['replaces_document_no'] }} โดยอ้างอิงยอดขายเดิม</p>@endif
@if($document['buyer'])<p>ผู้ซื้อ {{ $document['buyer']['name'] }} · เลขผู้เสียภาษี {{ $document['buyer']['tax_id'] }} · สาขา {{ $document['buyer']['branch_no'] }}</p><p>{{ $document['buyer']['address'] }}</p>@endif
<table><thead><tr><th>รายการ</th><th>จำนวน</th><th>หน่วย</th><th>{{ $document['kind'] === 'ABB' ? 'รวมภาษี' : 'ก่อนภาษี' }}</th></tr></thead><tbody>
@foreach($document['lines'] as $line)<tr><td>{{ $line['name'] }}</td><td>{{ $line['quantity'] }}</td><td>{{ $line['unit'] }}</td><td>{{ $document['kind'] === 'ABB' ? $line['total'] : $line['subtotal'] }}</td></tr>@endforeach
</tbody></table>
@if($document['kind'] === 'FULL')<p>มูลค่าก่อนภาษี {{ $document['subtotal'] }} บาท</p><p>ภาษีมูลค่าเพิ่ม {{ $document['tax_amount'] }} บาท</p>@else<p>ราคาสินค้าได้รวมภาษีมูลค่าเพิ่มไว้แล้ว</p>@endif
<h2>ยอดรวม {{ $document['total'] }} บาท</h2>
<div class="actions"><button onclick="window.print()">พิมพ์เอกสารทดสอบ</button></div>
</body></html>
