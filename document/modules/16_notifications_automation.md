# 16 — Notifications และงานอัตโนมัติ

Source snapshot 2026-10-24 ฐาน commit `b03c393c19762a09862b4a7aab688f1fe47dcdb3`
อ่าน implementation และ representative tests; ไม่ได้รันทดสอบหรือส่งข้อความจริง

## ขอบเขต

แจ้งเหตุการณ์ธุรกิจแก่ staff ผ่าน in-app, email, LINE, Slack และ Telegram
แยกการสร้างเหตุการณ์ออกจากการส่งด้วย outbox
automation หลายรายการใช้ Laravel scheduler ใน routes/console.php
External integration webhooks ใช้ service และ outbox อีกชุด ดู module 18

## Source หลัก

- [NotificationService](../../backend/app/Services/NotificationService.php): `notify`
- [NotificationOutboxService](../../backend/app/Services/NotificationOutboxService.php): `enqueue`, `dispatchPending`, `dispatch`, `channelsFor`, `deliver`
- [NotificationPreferenceController](../../backend/app/Http/Controllers/Settings/NotificationPreferenceController.php)
- [Notification drivers](../../backend/app/Services/Notifications/NotificationChannelDriver.php)
- [Mail](../../backend/app/Mail/ErpNotificationMail.php)
- [UI](../../backend/resources/js/Pages/Settings/NotificationPreferences.tsx)
- [Schedules](../../backend/routes/console.php)
- [Routes](../../backend/routes/web.php)

## Routes และ access

| Method / path | Action | Gate เพิ่ม |
|---|---|---|
| GET /settings/notifications | edit | staff auth |
| PATCH /settings/notifications | update | staff auth |
| PATCH /settings/notifications/external-preferences | updateExternal | staff auth |
| POST /settings/notification-channels | storeChannel | settings.organization.update |
| PATCH /settings/notification-channels/{channel} | updateChannel | settings.organization.update |
| POST /settings/notification-channels/{channel}/test | testChannel | settings.organization.update |

channel mutations ใช้ password.confirm และ throttle:10,1; test ใช้ throttle:5,1
user preference กับ org channel config เป็นคนละระดับ
ค่าช่องทางต้องใช้ของ org ผู้ใช้ ไม่ควรถือว่ามี credential ในทุก environment

## Entities

- NotificationEvent: dedupe_key ต่อ org และ user
- NotificationPreference: legacy preference แยก event type, in_app_enabled/email_enabled
- NotificationChannel: org channel, encrypted config, is_enabled
- UserChannelPreference: enabled, urgent_only, quiet_hours_start/end
- NotificationOutbox: event_type, channel, payload, idempotency_key, status
- NotificationDispatch: บันทึกแต่ละ attempt และผลส่ง
- InAppNotification: รายการที่แสดงในแอป
- User/Organization: ผู้รับ email และ timezone

Migration อ้างอิง:
[Phase8 notifications](../../backend/database/migrations/2026_08_23_000003_create_phase8_notifications_tables.php)
และ [Phase21 outbox](../../backend/database/migrations/2026_09_09_000001_create_phase21_notification_outbox_tables.php)

## Flow ตั้งแต่เหตุการณ์ถึงปลายทาง

1. module เรียก NotificationService::notify(user, type, dedupeKey, title, body, url)
2. firstOrCreate NotificationEvent ด้วย org_id/user_id/dedupe_key
3. หาก event เดิม คืน false และไม่ enqueue เพิ่ม
4. channelsFor เลือกช่องทางตาม preference และ org config
5. enqueue สร้าง outbox แยก channel พร้อม idempotency key
6. หาก queue.default=`sync` จะเรียก dispatchPending ทันที
7. นอกนั้น scheduler `notifications:dispatch` ต้องทำงาน
8. dispatch lock row แล้วตั้ง processing ก่อนเรียกส่ง
9. สำเร็จสร้าง dispatch record และ outbox sent
10. ล้มเหลวสร้าง retrying หรือ failed ตาม attempt

NotificationEvent เป็น dedupe gate ก่อน dispatch ไม่ได้หมายความว่าส่งสำเร็จแล้ว

## Channel selection

ไม่มี legacy preference ปิดไว้ จะเลือก in_app และ email โดยค่าเริ่มต้น
external channel ต้อง org enabled และ user มี enabled preference
normal preference ส่งได้เมื่อไม่อยู่ quiet hours
urgent_only ส่งเฉพาะ priority=`urgent`
quiet hours คำนวณจาก timezone ของ organization หรือ app.timezone
รองรับช่วงเวลาที่ข้ามเที่ยงคืน
quiet hours ทำงานตอน enqueue selection; ข้อความถูก suppress ไม่ใช่เลื่อนส่งอัตโนมัติ

## Delivery state

pending/retrying → processing → sent
ข้อผิดพลาด: processing → retrying จน attempt_count ถึง 5 → failed
backoff=`2 ** attempt_count * 60` วินาที
last_error เป็นข้อความทั่วไปและ log เฉพาะ exception type เพื่อลดข้อมูลรั่ว
in_app สร้าง InAppNotification โดยตรง
email ใช้ Mail::queue(ErpNotificationMail)
ดังนั้น outbox sent ของ email ยืนยันการเข้าคิว mail ไม่ได้ยืนยันผู้รับได้รับแล้ว
external drivers ใช้ config ของ org channel ที่ยัง enabled

## Scheduled jobs ที่พบ

- documents:check-expiry ทุกวัน 08:15
- documents:enforce-retention ทุกวัน 08:30
- notifications:dispatch --limit=100 ทุกนาที และ withoutOverlapping
- notifications:daily-digest ทุกวัน 08:45 และ withoutOverlapping
- invoice due-soon/overdue และ e-Tax inquiry อยู่ใน console file เดียวกัน
- external-api:dispatch-webhooks --limit=20 ทุกนาที ใช้ outbox คนละชุด
- daily digest นับ due/overdue invoices ไม่ใส่ cash balance ในข้อความ

เวลาจาก schedule เป็นเวลาที่ Laravel ใช้ ต้องตรวจ app/organization timezone ตอน deploy
การมี Schedule declaration ไม่ได้ยืนยัน cron/scheduler/worker เปิดอยู่
mail queue ต้องมี transport และ worker ตาม queue.default ที่ติดตั้ง
LINE/Slack/Telegram ต้อง credential และ network จริง

## Tests — ไม่ได้รัน

- [Phase8NotificationsTest](../../backend/tests/Feature/Phase8NotificationsTest.php)
- [Phase21NotificationOutboxTest](../../backend/tests/Feature/Phase21NotificationOutboxTest.php)
- อ่าน failure test ที่ Http::fake Slack 500 แล้วได้ retrying, attempt_count=1
- อ่าน retry attempt ที่ 5 เปลี่ยน failed และมี failed_at
- มี tests ด้าน dedupe/encrypted channel config, quiet hours และ digest ไม่มี cash balance
- HTTP fake ยืนยัน contract ภายใน ไม่ยืนยัน credential/provider จริง
