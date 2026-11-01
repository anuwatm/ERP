<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>ตั้งค่า POS — {{ $branch->name }}</title>
    <style>
        body{font-family:system-ui,sans-serif;background:#f2f5f8;color:#172b3a;margin:0}main{max-width:1100px;margin:auto;padding:24px}header{display:flex;justify-content:space-between;gap:16px;align-items:center}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,360px),1fr));gap:20px}section{background:white;border:1px solid #d5dfe5;border-radius:12px;padding:20px;margin:20px 0}label{display:block;margin:12px 0}input,select,button{box-sizing:border-box;width:100%;padding:11px;font:inherit;border:1px solid #8a9ba7;border-radius:6px}button{background:#165c50;color:white;cursor:pointer;margin-top:12px}table{width:100%;border-collapse:collapse}td,th{text-align:left;padding:8px;border-bottom:1px solid #ddd;overflow-wrap:anywhere}.scroll{overflow:auto}code{overflow-wrap:anywhere}small{display:block;color:#465b69}.notice{background:#fff5db;padding:14px;border-radius:8px}.error{color:#942727}a{color:#165c50}h2{margin-top:0}
    </style>
</head>
<body><main>
<header><div><h1>ตั้งค่า POS</h1><p>{{ $branch->name }} · {{ $branch->code }}</p></div><a href="/">กลับหน้าขาย</a></header>
<p class="notice">สำหรับ local เท่านั้น ยังไม่ได้ทดสอบกับระบบและอุปกรณ์จริง ผู้จัดการดูแลได้เฉพาะสาขาของตน การเพิ่มสาขา/ผู้จัดการคนแรกใช้ CLI ตามคู่มือ</p>
@if(session('status'))<p role="status">{{ session('status') }}</p>@endif
@if($errors->any())<div class="error" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<div class="grid">
<section><h2>รายละเอียดสาขา</h2><p>Branch UUID: <code>{{ $branch->id }}</code></p><p>รหัสสาขา: {{ $branch->code }} · สกุลเงิน: {{ $branch->currency }} · เขตเวลา: {{ $branch->timezone }}</p>
<form method="post" action="/config/branch">@csrf
<label>ชื่อสาขา<input name="name" value="{{ old('name', $branch->name) }}" required maxlength="255"></label>
<label>ประเภทกิจการ<select name="business_type">@foreach(['retail'=>'ร้านขายของทั่วไป','restaurant'=>'ร้านอาหาร','pharmacy'=>'ร้านขายยา'] as $value=>$label)<option value="{{ $value }}" @selected($branch->business_type === $value)>{{ $label }}</option>@endforeach</select></label>
<small>เปลี่ยนประเภทได้ก่อนเพิ่ม Terminal/catalog/quota เท่านั้น สาขาที่เริ่มตั้งค่าแล้วให้สร้างสาขาใหม่เพื่อเปลี่ยนกิจการ ไม่แก้รหัสสาขาหรือเลขเอกสารเดิม</small>
<label>รหัสผ่านผู้จัดการ<input type="password" name="manager_password" autocomplete="current-password" required></label><button>บันทึกสาขา</button></form></section>
<section><h2>เพิ่มเครื่องขาย</h2><form method="post" action="/config/terminal">@csrf
<label>รหัส Terminal<input name="code" placeholder="T01" required maxlength="30" pattern="[A-Za-z0-9_-]{1,30}"></label>
<small>สร้าง UUID ให้โดยระบบ ต้องลงทะเบียน UUID/code ให้ตรงกับ ERP ก่อนขาย</small>
<label>รหัสผ่านผู้จัดการ<input type="password" name="manager_password" autocomplete="current-password" required></label><button>เพิ่ม Terminal</button></form>
<div class="scroll"><table><thead><tr><th>รหัส</th><th>UUID</th><th>สถานะ</th></tr></thead><tbody>@forelse($terminals as $terminal)<tr><td>{{ $terminal->code }}</td><td>{{ $terminal->id }}</td><td>{{ $terminal->active ? 'เปิดใช้' : 'ปิดใช้' }}</td></tr>@empty<tr><td colspan="3">ยังไม่มีเครื่องขาย</td></tr>@endforelse</tbody></table></div></section>
<section><h2>เพิ่มพนักงานประจำสาขา</h2><form method="post" action="/config/staff">@csrf
<label>ชื่อ<input name="name" required maxlength="255" autocomplete="name"></label>
<label>อีเมล<input type="email" name="email" required maxlength="255" autocomplete="off"></label>
<label>บทบาท<select name="role"><option value="cashier">แคชเชียร์</option><option value="manager">ผู้จัดการ</option>@if($branch->business_type === 'restaurant')<option value="kitchen">ครัว</option>@endif @if($branch->business_type === 'pharmacy')<option value="pharmacist">เภสัชกร</option>@endif</select></label>
<label>รหัสผ่านพนักงาน (อย่างน้อย 12 ตัว)<input type="password" name="password" required minlength="12" maxlength="1000" autocomplete="new-password"></label>
<label>ยืนยันรหัสผ่านพนักงาน<input type="password" name="password_confirmation" required autocomplete="new-password"></label>
<label>รหัสผ่านผู้จัดการผู้บันทึก<input type="password" name="manager_password" required autocomplete="current-password"></label><button>เพิ่มพนักงาน</button></form>
<p>สิทธิ์ ERP ต้องลงทะเบียนแยกตาม Staff UUID ส่วนคุณสมบัติผู้จัดการเภสัชกรให้ผู้ดูแล provision ตามหลักฐาน ไม่เปิดรับรองตนเองจากหน้านี้</p></section>
<section><h2>สถานะและค่าจาก Server</h2><p>Instance UUID: <code>{{ config('pos.instance_id') ?: 'ยังไม่ตั้งค่า' }}</code></p>
<p>ร้านอาหารและร้านยาต้องเปิด feature flag ให้ตรงประเภทก่อนใช้งาน</p><ul>@foreach($features as $key=>$enabled)<li><code>{{ $key }}</code>: {{ $enabled ? 'เปิด' : 'ปิด' }}</li>@endforeach</ul>
<p>Feature flags ตั้งผ่าน environment ของ Server และ restart/config clear โดยผู้ดูแล ไม่เปลี่ยน .env หรือ keys จาก browser</p>
<p>ราคา/VAT/catalog/quota, สูตรอาหาร, lot/หน่วยยา, issuer profile และ GL mappings จัดการจาก ERP POS Integration แล้ว sync ผ่าน connector ที่ตั้งค่าไว้</p><p>e-Tax ยังรอ provider การเลือกกิจการไม่เปิด production หรือส่ง e-Tax อัตโนมัติ</p></section>
</div>
<section><h2>พนักงานในสาขา</h2><div class="scroll"><table><thead><tr><th>ชื่อ/อีเมล</th><th>Staff UUID</th><th>บทบาท</th><th>สถานะ</th></tr></thead><tbody>@foreach($staff as $person)<tr><td>{{ $person->name }}<br>{{ $person->email }}</td><td>{{ $person->id }}</td><td>{{ $person->role }}{{ $person->pharmacist_qualified ? ' · pharmacist qualified' : '' }}</td><td>{{ $person->active ? 'เปิดใช้' : 'ปิดใช้' }}</td></tr>@endforeach</tbody></table></div></section>
</main></body></html>
