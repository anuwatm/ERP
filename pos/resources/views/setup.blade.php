<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>POS Setup Wizard</title>
    @include('partials.theme-preference')
    <style>
        :root { color-scheme: light dark; font-family: system-ui, sans-serif; }
        :root[data-pos-theme="light"] { color-scheme: light; }
        :root[data-pos-theme="dark"] { color-scheme: dark; }
        body { margin: 0; background: light-dark(#f5f2ea, #101a18); color: light-dark(#112e28, #eaf4ef); }
        main { max-width: 48rem; margin: 2rem auto; padding: 0 1rem; }
        h1 { margin-bottom: .4rem; }
        p { line-height: 1.6; }
        fieldset, .notice { min-width: 0; margin: 1rem 0; padding: 1rem; border: 1px solid light-dark(#aebbb5, #657e73); border-radius: .75rem; }
        legend, label { font-weight: 650; }
        label { display: grid; gap: .25rem; margin-bottom: .75rem; }
        input, select, button { box-sizing: border-box; width: 100%; min-height: 2.75rem; padding: .5rem .75rem; border: 1px solid light-dark(#758982, #799489); border-radius: .4rem; font: inherit; }
        :focus-visible { outline: 2px solid light-dark(#08765e, #a0d9bf); outline-offset: 3px; }
        button { border: 0; background: light-dark(#a33b2c, #ffae9a); color: light-dark(#fff8f1, #32140e); font-weight: 700; cursor: pointer; }
        a { color: inherit; }
        .errors { color: light-dark(#912e22, #ffc2b3); }
        @media (min-width: 38rem) { .fields { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0 1rem; } }
    </style>
</head>
<body>
<main>
    <h1>POS Setup Wizard</h1>
    @if ($installed ?? false)
        <p role="status">สร้างสาขา เครื่องขาย และบัญชี Manager แล้ว</p>
        <p>ลบ <code>POS_SETUP_TOKEN</code> จากการตั้งค่า แล้ว <a href="{{ url('/') }}">เปิด POS และเข้าสู่ระบบ</a> ด้วยบัญชีที่สร้าง</p>
        <p>ก่อนขาย ให้เชื่อม ERP และเตรียม catalog และ quota ของสาขาตามคู่มือติดตั้ง</p>
    @elseif (! $ready)
        <p class="notice">ฐาน POS ยังไม่พร้อม ให้รัน <code>php artisan migrate</code> จากโฟลเดอร์ <code>pos/</code> แล้วเปิดหน้านี้ใหม่</p>
    @else
        <p>ตั้งค่าสาขาแรกสำหรับ Retail, Restaurant หรือ Pharmacy แล้วสร้างเครื่องขายและบัญชี Manager</p>
        @if ($errors->any())
            <div class="notice errors" role="alert">
                <strong>ตรวจข้อมูลก่อนติดตั้ง แล้วกรอก Token และรหัสผ่านอีกครั้ง</strong>
                <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif
        <form method="post" action="{{ route('setup.install') }}">
            @csrf
            <fieldset>
                <legend>1. สาขาและเครื่องขาย</legend>
                <div class="fields">
                    <label>รหัสสาขา<input name="branch_code" value="{{ old('branch_code') }}" required maxlength="30" pattern="[A-Za-z0-9_\-]+"></label>
                    <label>ชื่อสาขา<input name="branch_name" value="{{ old('branch_name') }}" required maxlength="255"></label>
                    <label>ประเภทกิจการ
                        <select name="business_type" required>
                            @foreach (['retail' => 'ร้านค้าทั่วไป', 'restaurant' => 'ร้านอาหาร', 'pharmacy' => 'ร้านขายยา'] as $type => $label)
                                <option value="{{ $type }}" @selected(old('business_type') === $type)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>รหัสเครื่องขาย<input name="terminal_code" value="{{ old('terminal_code', 'T01') }}" required maxlength="30" pattern="[A-Za-z0-9_\-]+"></label>
                </div>
            </fieldset>
            <fieldset>
                <legend>2. บัญชี Manager</legend>
                <div class="fields">
                    <label>ชื่อ<input name="name" value="{{ old('name') }}" required maxlength="255" autocomplete="name"></label>
                    <label>อีเมล<input name="email" type="email" required maxlength="255" autocomplete="username"></label>
                    <label>รหัสผ่านอย่างน้อย 12 ตัวอักษร<input name="password" type="password" required minlength="12" maxlength="72" autocomplete="new-password"></label>
                    <label>ยืนยันรหัสผ่าน<input name="password_confirmation" type="password" required minlength="12" maxlength="72" autocomplete="new-password"></label>
                </div>
            </fieldset>
            <fieldset>
                <legend>3. ยืนยันติดตั้ง</legend>
                <p>ตรวจประเภทกิจการก่อนติดตั้ง ประเภทจะถูกล็อกเมื่อสร้างเครื่องขาย</p>
                <label>Setup Token<input name="setup_token" type="password" required minlength="32" autocomplete="off"></label>
                <label>พิมพ์ INSTALL เพื่อยืนยัน<input name="confirm_install" required pattern="INSTALL" autocomplete="off"></label>
                <button type="submit">ติดตั้ง POS</button>
            </fieldset>
        </form>
    @endif
</main>
</body>
</html>
