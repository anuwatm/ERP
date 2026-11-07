@php
    $status = trim($__env->yieldContent('code'));
    [$heading, $description] = match ($status) {
        '401', '419' => ['กรุณาเข้าสู่ระบบใหม่', 'กลับไปหน้าขายเพื่อยืนยันบัญชีและเปิดหน้าที่ต้องการอีกครั้ง'],
        '403' => ['ไม่มีสิทธิ์เข้าหน้านี้', 'ตรวจบัญชีที่ใช้งาน หรือติดต่อผู้จัดการสาขา'],
        '404' => ['ไม่พบหน้าที่ต้องการ', 'ตรวจลิงก์หรือกลับไปเปิดหน้านี้จากหน้าขาย'],
        '429' => ['ส่งคำขอบ่อยเกินไป', 'รอสักครู่แล้วลองอีกครั้ง'],
        '503' => ['หน้านี้ยังไม่พร้อมใช้งาน', 'ติดต่อพนักงานหรือผู้จัดการสาขา'],
        default => ['เปิดหน้านี้ไม่ได้', 'กลับไปหน้าขายหรือลองอีกครั้งภายหลัง'],
    };
@endphp
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $heading }} · POS</title>
    @include('partials.workspace-theme')
    <style>
        body { display: grid; place-items: center; min-height: 100vh; box-sizing: border-box; margin: 0; padding: 1rem; font: 16px system-ui, sans-serif; }
        .error-card { box-sizing: border-box; width: min(100%, 30rem); background: var(--workspace-panel); border: 1px solid var(--workspace-border); border-radius: .75rem; padding: clamp(1rem, 5vw, 2rem); }
        h1 { font-size: clamp(1.4rem, 5vw, 2rem); margin: .25rem 0 .75rem; }
        p { line-height: 1.5; }
    </style>
</head>
<body class="pos-workspace">
    <main class="error-card">
        @if (preg_match('/^[45][0-9]{2}$/D', $status))
            <strong>{{ $status }}</strong>
        @endif
        <h1>{{ $heading }}</h1>
        <p>{{ $description }}</p>
        <a href="{{ url('/') }}">กลับหน้าขาย</a>
    </main>
</body>
</html>
