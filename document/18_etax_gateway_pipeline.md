# e-Tax Invoice & RD Prep Gateway Architecture — Phase 24

> สถานะ: **เสร็จสมบูรณ์และผ่านการตรวจรับ 100% (Complete & Closed ณ 2026-09-20)**

```mermaid
sequenceDiagram
    autonumber
    actor Staff as Finance Staff
    participant System as ERP Invoicing Module
    participant ETaxAdapter as RdApiSubmissionAdapter
    participant SignEngine as ETaxXmlSignatureService
    participant Queue as Queue Worker (SubmitETaxDocument)
    participant Gateway as Certified Gateway / RD API
    participant Webhook as ETaxWebhookController
    participant Artisan as artisan etax:inquire-status

    Staff->>System: สั่งสร้าง e-Tax (Tax Invoice / Credit Note)
    System->>System: ตรวจสอบข้อมูลผู้ซื้อ/ผู้ขาย & Tax ID 13 หลัก
    System->>SignEngine: สร้าง ETDA XML มาตรฐาน & ส่งข้อมูล Sign
    Note over SignEngine: XML Canonicalization (C14N)<br/>RSA-SHA256 DigestValue<br/>X.509 KeyInfo Reference
    SignEngine-->>System: คืนค่า Enveloped XML-DSig Signed XML
    System->>System: บันทึก etax_documents (สถานะ: signed)
    
    alt โหมด Mock / Manual Export
        System-->>Staff: ดาวน์โหลด Signed XML / PDF e-Tax ทันที
    else โหมด Live Provider Gateway
        System->>Queue: Push Job SubmitETaxDocument (Queue: etax)
        Queue->>ETaxAdapter: ประมวลผลส่งผ่าน Gateway Adapter
        ETaxAdapter->>Gateway: POST /api/v1/etax/submit (Base64 Encoded XML)
        Gateway-->>ETaxAdapter: ตอบกลับ HTTP 202 Accepted + Submission UID
        ETaxAdapter->>System: อัปเดตสถานะ etax_documents เป็น submitted
        
        par Webhook Callback
            Gateway->>Webhook: POST /api/e-tax/{config}/callback (HMAC-SHA256)
            Webhook->>Webhook: ตรวจสอบ X-ETax-Signature & Multi-tenant Scope
            Webhook->>System: อัปเดตสถานะเอกสารเป็น accepted หรือ rejected
        and Async Scheduled Inquiry
            Artisan->>Gateway: GET /api/v1/etax/status/{uid} (ทุก 30 นาที)
            Gateway-->>Artisan: คืนสถานะเอกสารทางการจากกรมสรรพากร
            Artisan->>System: ซิงค์สถานะและเลขรับเอกสารทางการ RD Code
        end
    end
```

### รายละเอียดสถาปัตยกรรมและการควบคุมความปลอดภัย

1. **Digital Signature Pipeline:** ท่อประมวลผลลายมือชื่อดิจิทัลรองรับมาตรฐาน ETDA Enveloped XML-DSig (`http://www.w3.org/2000/09/xmldsig#`) โดยแยก Private Key ไว้นอกฐานข้อมูลอย่างเข้มงวด (จัดเก็บบน Server/Cloud Vault Reference เท่านั้น)
2. **Provider Adapters:** รองรับ 4 โหมดการทำงาน: `disabled`, `manual_export`, `mock`, และ `provider` (เชื่อมต่อ RD Web Service, Certified Provider INET, หรือ Sandbox)
3. **Queue & Idempotency:** ใช้ Job Worker `SubmitETaxDocument` พร้อมเพดาน Retry 3 ครั้งและบันทึกประวัติการส่งลงในตาราง `etax_submission_attempts`
4. **Webhook Security:** ตรวจสอบ HMAC-SHA256 Signature ทุก Callback Request ป้องกันการปลอมแปลงผลการอนุมัติเอกสาร

ดูรายละเอียดเพิ่มเติมที่ [`../docs/PHASE_24_ETAX_FOUNDATION.md`](../docs/PHASE_24_ETAX_FOUNDATION.md).
