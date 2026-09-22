# AI OCR Document Ingestion — Phase 26

> สถานะ: **เสร็จสมบูรณ์และผ่านการตรวจรับ 100% (Complete & Closed ณ 2026-09-21)**

```mermaid
sequenceDiagram
    autonumber
    actor User as Accounting Staff
    participant UI as OcrReviewer.tsx (Split-Screen)
    participant Ctrl as OcrDocumentController
    participant Preprocess as Image & PDF Pre-processor
    participant AIHub as Universal OpenAI-Compatible Vision Hub
    participant Validator as ExtractionValidator & MathReconciler
    participant DMS as Central DMS Repository
    participant Expense as Expense/Bill Module (Draft Only)

    User->>UI: อัปโหลดรูปภาพใบเสร็จ / PDF สแกน
    UI->>Ctrl: POST /finance/ocr/upload (Multipart file)
    Ctrl->>Preprocess: ตรวจสอบ MIME & Format
    
    alt เป็นไฟล์ Scanned PDF
        Preprocess->>Preprocess: สกัด JPEG Stream ด้วย extractJpegFromPdf()
    else เป็นไฟล์ภาพ (JPEG, PNG, WEBP)
        Preprocess->>Preprocess: จัดเตรียม High-Detail Tile Buffer
    end
    
    Ctrl->>AIHub: ส่ง Vision Request พร้อม Base64 Payload
    Note over AIHub: รองรับทั้ง Local AI (Ollama qwen2.5-vl)<br/>และ Cloud Hubs (GPT-4o, Gemini, OpenRouter)<br/>บังคับ Native JSON Mode & max_tokens
    AIHub-->>Ctrl: ส่งคืน Structured JSON Object
    
    Ctrl->>Validator: ตรวจสอบความถูกต้องและกระทบยอดตัวเลข
    Note over Validator: 1. Thai Tax ID 13 หลัก (Modulo 11 Check)<br/>2. Math: Subtotal + VAT - WHT == Total Amount<br/>3. คำนวณค่า Confidence Score
    Validator-->>Ctrl: ผลการตรวจสอบและ Flag (math_mismatch, tax_id_valid)
    
    Ctrl->>DMS: จัดเก็บไฟล์เอกสารต้นฉบับลง DMS (Protected Tenant Path)
    Ctrl-->>UI: ส่งข้อมูลกลับแสดงผลในหน้า Split-Screen Reviewer
    
    User->>UI: ตรวจสอบเปรียบเทียบภาพต้นฉบับทางซ้าย กับฟอร์มแก้ไขทางขวา
    User->>UI: กดยืนยัน "Convert to Expense"
    UI->>Ctrl: POST /finance/ocr/{id}/convert
    Ctrl->>Expense: สร้าง Draft Expense ในระบบ (Assisted Draft Flow)
    Note over Expense: 🔒 Human-in-the-Loop Guard:<br/>ห้าม Auto-post ลงบัญชีแยกประเภท (GL)<br/>ต้องผ่านการอนุมัติก่อนเสมอ
    Expense-->>UI: Redirect ไปยังหน้ารายละเอียด Expense
```

### รายละเอียดสถาปัตยกรรมและ Security Guardrails

1. **Universal OpenAI-Compatible Vision Architecture:**
   - ออกแบบ API Adapter มาตรฐานเดียว สามารถสลับใช้งานระหว่าง **On-Premise Local AI** (Ollama / LM Studio) เพื่อรักษาความลับสูงสุดของข้อมูลองค์กร และ **Cloud Vision Hubs** (GPT-4o, OpenRouter, Gemini) ได้แบบ Plug-and-Play
2. **Robust Extraction & Math Reconciliation:**
   - ตรวจสอบความถูกต้องของเลขประจำตัวผู้เสียภาษี 13 หลักด้วยอัลกอริทึม **Thai Tax ID Modulo 11**
   - คำนวณตรวจสอบสมดุลตัวเลข (Math Check): ยอดก่อนภาษี + ภาษีมูลค่าเพิ่ม 7% - ภาษีหัก ณ ที่จ่าย = ยอดรวมสุทธิ หากไม่ตรงระบบจะไฮไลต์เตือน `math_mismatch: true`
3. **Split-Screen Reviewer UI:**
   - หน้าจอตรวจสอบแบบแบ่ง 2 ฝั่ง: ฝั่งซ้ายแสดงเอกสารต้นฉบับพร้อมระบบ Pan & Zoom, ฝั่งขวาเป็นฟอร์มข้อมูลที่สกัดได้ พร้อมตัวบ่งชี้ความเชื่อมั่น (Confidence Badge)
4. **Human-in-the-Loop & No Auto-GL Guard:**
   - ผลการสแกนทุกรายการจะถูกแปลงเป็น **Draft Expense** หรือ Draft Vendor Bill เท่านั้น เพื่อให้พนักงานฝ่ายบัญชีตรวจสอบและลงนามอนุมัติ ห้ามระบบลงบัญชีแยกประเภททั่วไป (GL Posting) อัตโนมัติโดยเด็ดขาด

ดูรายละเอียดเพิ่มเติมที่ [`../gemini.md`](../gemini.md#8-บันทึกข้อสงสัย-การวินิจฉัยปัญหา-และแนวทางแก้ไขการเชื่อมต่อ-gpt-openai-diagnostic--resolution-record).
