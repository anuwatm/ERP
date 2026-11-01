<!doctype html>
<html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>รหัสกู้คืน 2FA</title>
<style>body{font-family:system-ui,sans-serif;background:#f1f5f9;color:#172b3a}main{max-width:600px;margin:40px auto;padding:24px;background:white;border-radius:12px}ul{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px;list-style:none;padding:0}code{font-size:18px}a{display:inline-block;margin-top:16px}</style></head>
<body><main><h1>รหัสกู้คืน 2FA</h1><p>แสดงครั้งเดียว บันทึกไว้ในที่ปลอดภัยก่อนออกจากหน้านี้ แต่ละรหัสใช้ได้ครั้งเดียว</p>
<div id="recovery"><ul>@foreach($codes as $code)<li><code>{{ $code }}</code></li>@endforeach</ul></div>
<a href="{{ route('profile.edit') }}">บันทึกรหัสแล้ว — กลับโปรไฟล์</a></main>
<script>window.addEventListener('pagehide',()=>{document.getElementById('recovery')?.replaceChildren();});window.addEventListener('pageshow',e=>{if(e.persisted)window.location.replace(@json(route('profile.edit')));});</script></body></html>
