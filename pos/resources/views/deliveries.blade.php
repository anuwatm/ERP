<!doctype html>
<html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>POS Delivery</title>
<style>body{font-family:system-ui,sans-serif;margin:20px;max-width:1000px;background:#f5f7fa;color:#182334}section,article{background:white;border:1px solid #cbd5e1;border-radius:8px;padding:16px;margin:16px 0;overflow-wrap:anywhere}label{display:block;margin:8px 0}input:not([type=checkbox]),select,textarea{display:block;box-sizing:border-box;max-width:100%;width:100%;padding:8px}button,a{margin:4px}button{padding:8px 12px}form{margin:12px 0}.note{background:#fff4ce;padding:12px}.product{display:flex;gap:8px;align-items:center}.product input[type=number]{width:90px}summary{cursor:pointer}a{color:#174ea6}[role=alert]{color:#9b2424}</style>@include('partials.workspace-theme')</head>
<body class="pos-workspace"><a href="/">กลับ POS</a><h1>คำสั่งซื้อ / จัดส่ง</h1>
<p class="note">Local — ยังไม่ได้ทดสอบกับระบบจริง<br>COD และค่าจัดส่งเก็บเป็นหลักฐานเงินรับฝากรอตรวจรับบัญชี/ภาษี ไม่ออก ABB/Payment/GL อัตโนมัติ; Pharmacy Delivery ปิดอยู่</p>
@if(session('delivery_notice'))<p role="status">{{ session('delivery_notice') }}</p>@endif
@if($errors->any())<div role="alert">ข้อมูลไม่ครบหรือรูปแบบไม่ถูกต้อง กรุณาตรวจแบบฟอร์มใหม่</div>@endif
<section><h2>สร้างงานจัดส่ง</h2><form data-delivery method="post" action="/deliveries">@csrf
<input type="hidden" name="request_key" value="{{ Illuminate\Support\Str::uuid() }}">
<label>วิธีรับเงิน<select name="settlement_mode"><option value="prepaid_cash">เงินสดก่อนส่ง — อ้างบิลที่ชำระแล้ว</option><option value="cod">COD — เก็บเงินปลายทาง</option></select></label>
<label>บิลเงินสดเดิม (เว้นว่างสำหรับ COD)<select name="sale_id"><option value="">เลือกบิล</option>@foreach($sales as $sale)<option value="{{ $sale->id }}">{{ $sale->receipt_no }}</option>@endforeach</select></label>
<label>ชื่อผู้รับ<input name="recipient" required maxlength="120" autocomplete="off"></label><label>โทรศัพท์<input name="phone" required maxlength="40" autocomplete="off"></label><label>ที่อยู่<textarea name="address" required maxlength="1000" autocomplete="off"></textarea></label>
<input type="hidden" name="catalog_version" value="{{ $catalog?->version }}">
<details><summary>เลือกสินค้า COD (แสดงไม่เกิน100รายการ)</summary><p>ราคาและจำนวนจะตรวจจาก POS Server; ยังไม่จอง Stock จนยืนยันพร้อมจัดส่ง</p>
@foreach($products as $i => $product)<div class="product"><label><input type="checkbox" name="items[{{ $i }}][product_id]" value="{{ $product['product_id'] }}"> {{ $product['name'] }} — {{ $product['price'] }} / {{ $product['unit'] }}</label><input aria-label="จำนวน {{ $product['name'] }}" type="number" name="items[{{ $i }}][quantity]" min="0.0001" step="0.0001" value="1"></div>@endforeach</details>
<button type="submit">บันทึกงานจัดส่ง</button><p role="alert" aria-live="polite"></p></form></section>
<h2>งานล่าสุด</h2>
@forelse($deliveries as $delivery)
@php
$s=$delivery->snapshot;
$map=['draft'=>['ready'=>'พร้อมจัดส่ง','cancel'=>'ยกเลิก'],'ready'=>['dispatch'=>'ส่งออก','cancel'=>'ยกเลิก','receive'=>'รับเข้าตรวจคืน'],'dispatched'=>['deliver'=>'ส่งสำเร็จ','fail'=>'ส่งไม่สำเร็จ'],'delivered'=>['collect'=>'รับเงินจากลูกค้า','remit'=>'รับเงินคืนร้าน','receive'=>'รับเข้าตรวจคืน'],'failed'=>['retry'=>'เตรียมส่งใหม่','receive'=>'รับเข้าตรวจคืน'],'return_review'=>['refund'=>'คืนเงิน COD/ค่าจัดส่ง','refund_prepaid'=>'คืนเงินบิลเดิม','inspect'=>'ตรวจรับคืน'],'returned'=>['refund'=>'คืนเงิน COD/ค่าจัดส่ง'],'cancelled'=>['receive'=>'รับเข้าตรวจคืน']];
$actions=$map[$delivery->status] ?? [];
$manager=auth()->user()->role==='manager';
$due=bcadd($delivery->settlement_mode==='cod'?($s['total']??'0'):'0',$s['fees_total']??'0',2);
@endphp
<article data-delivery-id="{{ $delivery->id }}"><h3>{{ $delivery->id }}</h3><p>{{ $delivery->settlement_mode }} · {{ $delivery->status }} · version {{ $delivery->version }}</p>
<p>{{ $delivery->contact['recipient'] }} · {{ $delivery->contact['phone'] }}<br>{{ $delivery->contact['address'] }}</p>
<p>ยอดสินค้า {{ $s['total']??'0.00' }} · ค่าจัดส่ง/บรรจุภัณฑ์ {{ $s['fees_total']??'0.00' }}<br>ลูกค้าจ่ายเพิ่ม {{ $due }} · รับแล้ว {{ $s['cash_collected']??'0.00' }} · ส่งร้าน {{ $s['cash_remitted']??'0.00' }} · คืนลูกค้า {{ $s['cash_refunded']??'0.00' }}</p>
<p>Stock: {{ $s['stock_status']??'อ้างบิลเดิม' }} · COD/ค่าจัดส่งรอ review บัญชี; signed events ตรวจสถานะรับเข้าใน ERP monitor</p>
<a href="/deliveries/{{ $delivery->id }}/slip">ใบส่งของ</a>
@if(in_array($delivery->status,['draft','failed']))
<details><summary>แก้ไขผู้รับ@if($delivery->status==='draft' && $delivery->settlement_mode==='cod') / รายการ COD @endif</summary>
<form data-delivery method="post" action="/deliveries/{{ $delivery->id }}/transition">@csrf<input type="hidden" name="request_key" value="{{ Illuminate\Support\Str::uuid() }}"><input type="hidden" name="version" value="{{ $delivery->version }}"><input type="hidden" name="action" value="edit">
<label>ผู้รับ<input name="recipient" required maxlength="120" value="{{ $delivery->contact['recipient'] }}"></label><label>โทรศัพท์<input name="phone" required maxlength="40" value="{{ $delivery->contact['phone'] }}"></label><label>ที่อยู่<textarea name="address" required maxlength="1000">{{ $delivery->contact['address'] }}</textarea></label>
@if($delivery->status==='draft' && $delivery->settlement_mode==='cod')<p>แก้สินค้าแล้วต้องตรวจราคา/ค่าธรรมเนียมใหม่</p><input type="hidden" name="catalog_version" value="{{ $catalog?->version }}">@foreach($products as $i=>$p) @php $old=collect($s['lines'])->firstWhere('product_id',$p['product_id']); @endphp<div class="product"><label><input type="checkbox" name="items[{{ $i }}][product_id]" value="{{ $p['product_id'] }}" @checked($old)> {{ $p['name'] }}</label><input type="number" aria-label="จำนวน {{ $p['name'] }}" name="items[{{ $i }}][quantity]" min="0.0001" step="0.0001" value="{{ $old['quantity']??1 }}"></div>@endforeach @endif
@if($delivery->status==='failed')<label>รหัสผ่านผู้จัดการ<input name="manager_password" type="password" required autocomplete="current-password"></label>@endif
<button type="submit">บันทึกการแก้ไข</button><p role="alert" aria-live="polite"></p></form></details>
@endif
@if($manager && $delivery->status==='draft')<details><summary>ค่าจัดส่ง / บรรจุภัณฑ์</summary><form data-delivery method="post" action="/deliveries/{{ $delivery->id }}/transition">@csrf<input type="hidden" name="request_key" value="{{ Illuminate\Support\Str::uuid() }}"><input type="hidden" name="version" value="{{ $delivery->version }}"><input type="hidden" name="action" value="fees">
@foreach(['delivery'=>'ค่าจัดส่ง','packaging'=>'ค่าบรรจุภัณฑ์'] as $kind=>$label)<label>{{ $label }} ก่อนภาษี<input name="{{ $kind }}_fee" type="number" min="0" step="0.01" required value="{{ $s['fees'][$kind]['original_net']??$s['fees'][$kind]['net']??'0.00' }}"></label><label>อัตราภาษี local (รอตรวจรับ)<select name="{{ $kind }}_tax_rate">@foreach($taxRates as $rate)<option value="{{ $rate }}" @selected(($s['fees'][$kind]['tax_rate']??'0.00')===$rate)>{{ $rate }}</option>@endforeach</select></label>@endforeach
<label>รหัสผ่านผู้จัดการ<input name="manager_password" type="password" required autocomplete="current-password"></label><button type="submit">บันทึกค่าธรรมเนียม</button><p role="alert" aria-live="polite"></p></form></details>@endif

@if($delivery->status==='draft' && $delivery->settlement_mode==='cod' && $membershipOptions['publication_id'])
<details><summary>สมาชิก / โปรโมชัน COD</summary><p>ตั้งค่าจัดส่ง/บรรจุภัณฑ์ก่อนเลือกโปรโมชัน การแก้สินค้าใหม่จะล้างตัวเลือกเดิม การยืนยันพร้อมจัดส่งจึงกันสิทธิ์จริง</p>
<form data-delivery method="post" action="/deliveries/{{ $delivery->id }}/transition">@csrf
<input type="hidden" name="request_key" value="{{ Illuminate\Support\Str::uuid() }}"><input type="hidden" name="version" value="{{ $delivery->version }}"><input type="hidden" name="action" value="membership"><input type="hidden" name="membership_publication_id" value="{{ $membershipOptions['publication_id'] }}">
<label>สมาชิก<select name="member_id"><option value="">ไม่ผูกสมาชิก</option>@foreach($membershipOptions['members'] as $member)<option value="{{ $member['member_id'] }}" @selected(($s['membership_selection']['member_id']??null)===$member['member_id'])>{{ $member['code'] }} · {{ $member['name_masked'] }} · {{ $member['phone_masked'] }}</option>@endforeach</select></label>
<label>โปรโมชัน<select name="promotion_id"><option value="">ไม่ใช้โปรโมชัน</option>@foreach($membershipOptions['policies'] as $policy)<option value="{{ $policy['id'] }}" @selected(($s['membership_selection']['promotion_id']??null)===$policy['id'])>{{ $policy['name'] }}</option>@endforeach</select></label>
<button>ตรวจยอดและบันทึกตัวเลือก</button><p role="alert" aria-live="polite"></p></form></details>
@endif
@if(!empty($s['membership']['quote']))<p>ส่วนลดโปรโมชัน {{ $s['membership']['quote']['discount_amount'] }} ก่อน VAT @if(!empty($s['membership_released'])) · คืนสิทธิ์แล้ว @endif</p>@endif
@foreach($actions as $action=>$label)
@continue(in_array($action,['retry','remit','refund','refund_prepaid','inspect']) && !$manager)
@continue($action==='collect' && (!empty($s['cash_collected']) || bccomp($due,'0',2)<=0))
@continue($action==='remit' && (empty($s['cash_collected']) || !empty($s['cash_remitted']) || !empty($s['cash_refunded'])))
@continue($action==='refund' && (empty($s['cash_collected']) || !empty($s['cash_refunded'])))
@continue($action==='refund_prepaid' && ($delivery->settlement_mode!=='prepaid_cash' || !empty($s['refund_id'])))
@continue($action==='receive' && $delivery->status==='cancelled' && $delivery->settlement_mode==='cod')
<form data-delivery method="post" action="/deliveries/{{ $delivery->id }}/transition">@csrf<input type="hidden" name="request_key" value="{{ Illuminate\Support\Str::uuid() }}"><input type="hidden" name="version" value="{{ $delivery->version }}"><input type="hidden" name="action" value="{{ $action }}">
@if($action==='dispatch')<label>ผู้รับผิดชอบส่ง<select name="courier_id" required><option value="">เลือกพนักงาน</option>@foreach($couriers as $courier)<option value="{{ $courier->id }}">{{ $courier->name }}</option>@endforeach</select></label>@endif
@if(($action==='ready' && $delivery->settlement_mode==='cod') || in_array($action,['remit','refund_prepaid']) || ($action==='refund' && !empty($s['cash_remitted'])))<label>Terminal ของกะที่รับ/จ่ายเงิน<select name="terminal_id" required><option value="">เลือก Terminal</option>@foreach($terminals as $terminal)<option value="{{ $terminal->id }}">{{ $terminal->code }}</option>@endforeach</select></label>@endif
@if(in_array($action,['fail','cancel']))<label>เหตุผล<select name="reason" required><option value="customer_cancel">ลูกค้ายกเลิก</option><option value="address_issue">ที่อยู่ไม่ถูกต้อง</option><option value="recipient_absent">ไม่พบผู้รับ</option><option value="transport_issue">ปัญหาขนส่ง</option><option value="other">อื่น ๆ</option></select></label>@endif
@if($action==='collect')<label>จำนวนเงินสุทธิที่รับจากลูกค้า<input name="amount" type="number" min="0.01" step="0.01" required value="{{ $due }}"></label>@endif
@if($action==='inspect')<label>ผลตรวจ<select name="decision">@if($branch->business_type!=='restaurant')<option value="restock">คืนขายหลังตรวจสภาพ</option>@endif<option value="destroy">ทำลาย / อาหารเสีย</option></select></label><label><input type="checkbox" name="condition_verified" value="1" required> ตรวจรับสินค้าจริง สภาพ/การเก็บ/lot/expiry ตามความเกี่ยวข้องแล้ว</label>@endif
@if(in_array($action,['retry','remit','refund','refund_prepaid','inspect']) || ($action==='cancel' && $branch->business_type==='restaurant' && !empty($s['reserved_at'])))<label>รหัสผ่านผู้จัดการ<input name="manager_password" type="password" required autocomplete="current-password"></label>@endif
<button type="submit">{{ $label }}</button><p role="alert" aria-live="polite"></p></form>
@endforeach</article>
@empty
<p>ยังไม่มีงานจัดส่งในหน้านี้</p>
@endforelse
{{ $deliveries->links() }}
<script src="/delivery.js" defer></script></body></html>
