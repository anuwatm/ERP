# คู่มือและผังการทำงาน 6 กลุ่มโมดูลหลัก (Domain Group Workflows — 31 Modules)

เอกสารนี้รวบรวม **Diagram ผังการทำงาน, วงจรสถานะ (Lifecycle), และลำดับการทำงาน (Sequence)** ของทั้ง **6 กลุ่มงานหลัก ครอบคลุม 31 โมดูลทั้งหมด** ของระบบ Company OS / Lightweight ERP พร้อมคำอธิบายภาษาไทยอย่างละเอียดในทุกขั้นตอน

---

## 📑 สารบัญกลุ่มงาน (Table of Domain Groups)

| กลุ่ม | ชื่อกลุ่มงาน | โมดูลที่ครอบคลุม | จำนวน Diagram | รายละเอียดโดยสังเขป |
|:---:|---|---|:---:|---|
| **Group 1** | [Foundation & Security](#group-1-foundation--security-โมดูล-01-04) | `01-04` (Org, User/Role, Settings, Audit) | 3 | โครงสร้างองค์กร, สิทธิ์ RBAC 7 บทบาท, และ Audit Trail |
| **Group 2** | [CRM & Sales Pipeline](#group-2-crm--sales-pipeline-โมดูล-05-08) | `05-08` (Customers, Contacts, Deals, Quotations) | 3 | รับ Lead &rarr; จัดการผู้ติดต่อ &rarr; ดัน Deal &rarr; ออกใบเสนอราคา |
| **Group 3** | [Project Delivery & Execution](#group-3-project-delivery--execution-โมดูล-09-11) | `09-11` (Projects, Tasks, Milestones) | 3 | แปลง Deal สู่ Project &rarr; จ่ายงาน Tasks &rarr; ตรวจรับ Milestones |
| **Group 4** | [Finance, Billing & Cost Control](#group-4-finance-billing--cost-control-โมดูล-12-16) | `12-16` (Products, Suppliers, Invoices, Payments, Expenses) | 3 | ออกบิล &rarr; รับเงิน (Anti-Overpay) &rarr; บันทึกรายจ่ายและคู่ค้า |
| **Group 5** | [Insights, Platform & Automation](#group-5-insights-platform--automation-โมดูล-17-23) | `17-23` (Dashboard, Reports, Files, Notifications, Automation, Import/Export, API) | 3 | Event Trigger, Data Pipeline ขึ้น Dashboard, และระบบไฟล์แนบ |
| **Group 6** | [Operations & Advanced Extensions](#group-6-operations--advanced-extensions-phase-7-18) | `24-31` (PO, Inventory, Payroll, AI, Accounting, Portal) | 3 | สั่งซื้อสินค้า, สต็อก, เงินเดือน และเชื่อมระบบภายนอก |

---

## 🗺️ ภาพรวมความเชื่อมโยงระหว่าง 6 กลุ่มงาน (High-Level Interaction)

```mermaid
flowchart LR
    subgraph G1 ["Group 1: Foundation & IAM"]
        Org["Organization / Branch"]
        RBAC["RBAC & Session"]
    end

    subgraph G2 ["Group 2: CRM & Sales"]
        Customer["Customers / Contacts"]
        Deal["Deals Pipeline"]
        Quote["Quotations"]
    end

    subgraph G3 ["Group 3: Project Delivery"]
        Project["Projects"]
        Task["Tasks (Kanban)"]
        Milestone["Milestones"]
    end

    subgraph G4 ["Group 4: Finance & Billing"]
        Invoice["Invoices"]
        Payment["Payments"]
        Expense["Expenses"]
    end

    subgraph G5 ["Group 5: Platform & Insights"]
        Dash["Executive Dashboard"]
        Audit["Audit Log"]
        Auto["Automation & Notify"]
    end

    subgraph G6 ["Group 6: Operations & Extensions (Phase 7-18)"]
        Stock["PO & Inventory"]
        HR["HR & Payroll"]
        Ext["External Accounting"]
    end

    %% Flow connections
    Org --> Customer
    RBAC --> Customer
    Customer --> Deal --> Quote
    Deal -->|Won| Project
    Quote -->|Approved| Invoice
    Project --> Task --> Milestone
    Milestone -.->|Billing Trigger| Invoice
    Invoice --> Payment
    Payment --> Dash
    Expense --> Dash
    Invoice -.-> Ext
    Payment -.-> Ext
    Stock --> Expense
    HR --> Expense
    Payment --> Audit
    Invoice --> Auto
```

---

## Group 1: Foundation & Security (โมดูล 01-04)
**โมดูลที่เกี่ยวข้อง:** `01-organization`, `02-user-role-permission`, `03-settings`, `04-audit-log`

### 1.1 Diagram: Org Hierarchy & Multi-Tenancy Scoping Flow
ผังแสดงลำดับชั้นองค์กรและการจำกัดสิทธิ์ข้อมูลด้วย `org_id`

```mermaid
flowchart TD
    Tenant["Organization (Tenant - org_id)"] --> Branch["Branch (รหัส 6 หลัก e.g. 000001)"]
    Branch --> Division["Division (สายงาน)"]
    Division --> Department["Department (แผนก)"]
    Department --> User["User Account (1 User = 1 Org)"]

    subgraph QueryGuard ["Tenant Isolation Layer"]
        Middleware["OrgScope Middleware / Controller Guard"]
        Scope["WHERE org_id = current_org_id"]
    end

    User -->|ส่งคำขอ Request| Middleware
    Middleware --> Scope
    Scope --> DB[("MariaDB Database Tables")]

    classDef blue fill:#0284c7,stroke:#38bdf8,stroke-width:2px,color:#fff;
    classDef dark fill:#1e293b,stroke:#475569,stroke-width:1px,color:#e2e8f0;
    class Tenant,Branch,User blue;
    class Middleware,Scope,DB dark;
```

**คำอธิบายการทำงาน (Workflow Description):**
1. **ระดับชั้นสูงสุด (Root Tenant):** องค์กร (`Organization`) ถูกระบุด้วย `org_id` ซึ่งเป็น Partition สูงสุดของข้อมูล
2. **โครงสร้างสาขาและสายงาน:** ภายใต้องค์กรสามารถมีหลายสาขา (`Branch`) ระบุด้วยรหัส 6 หลัก (เช่น สำนักงานใหญ่ `000001`), มีสายงาน (`Division`), แผนก (`Department`)
3. **ผู้ใช้งาน (User):** ผู้ใช้ 1 คนจะผูกกับ 1 องค์กร และสังกัดแผนกที่กำหนด
4. **Tenant Isolation Guard:** ทุก Controller และ Model Query จะถูกบังคับใส่เงื่อนไข `WHERE org_id = current_org_id` เสมอ เพื่อป้องกันไม่ให้ข้อมูลข้ามองค์กรกัน

---

### 1.2 Diagram: RBAC Permission Resolution Flow
ลำดับขั้นตอนการตรวจสอบสิทธิ์ 7 บทบาทหลัก

```mermaid
sequenceDiagram
    autonumber
    actor User as ผู้ใช้งาน (User)
    participant Route as Route / Controller
    participant Gate as RBAC Policy Gate
    participant Session as Session Store
    participant DB as MariaDB

    User->>Route: ร้องขอทำงาน (เช่น POST /invoices)
    Route->>Gate: ตรวจสอบสิทธิ์ (invoices.create)
    Gate->>Session: อ่าน Role ของ User ใน Org นี้
    Note over Gate: ตรวจสอบ 7 บทบาท:<br>Owner, Admin, Sales, PM, Finance, Member, Viewer
    alt สิทธิ์ถูกต้อง (Authorized)
        Gate-->>Route: อนุญาตให้ดำเนินการ (Allow)
        Route->>DB: ทำงานกับข้อมูลตามคำขอ
        DB-->>Route: ผลการบันทึกข้อมูล
        Route-->>User: ตอบกลับผลสำเร็จ (Success Response)
    else ไม่มีสิทธิ์ (Unauthorized)
        Gate-->>Route: ปฏิเสธการเข้าถึง (403 Forbidden)
        Route-->>User: แสดงข้อความแจ้งเตือนไม่มีสิทธิ์เข้าถึง
    end
```

**คำอธิบายบทบาทและสิทธิ์ (Role & Permission Rules):**
- **Owner / Admin:** จัดการได้ทุกโมดูลในองค์กร ตั้งค่าระบบ และจัดการผู้ใช้
- **Sales:** จัดการลูกค้า (CRM), Contacts, Deals, และดูใบเสนอราคา/บิลที่เกี่ยวข้องได้
- **Project Manager (PM):** บริหาร Projects, Tasks, Milestones และดูต้นทุนโครงการ
- **Finance:** มีสิทธิ์เต็มในโมดูล Invoices, Payments, Expenses, และรายงานการเงิน
- **Member:** ผู้ใช้งานทั่วไป ทำงานกับ Tasks ที่ได้รับมอบหมาย และบันทึกเวลาทำงาน
- **Viewer:** สิทธิ์ดูข้อมูลอย่างเดียว (Read-only) ไม่สามารถสร้างหรือแก้ไขได้

---

### 1.3 Diagram: Application Audit Logging Trail
ผังการบันทึกประวัติการเปลี่ยนแปลงข้อมูลที่ไม่สามารถลบได้ (Immutable Log)

```mermaid
flowchart LR
    Action["User Mutation Action<br>(Create / Update / Delete)"] --> Controller["Controller / Service"]
    Controller --> Model["Eloquent Model Save()"]
    Model --> Trigger["Audit Trail Hook"]
    Trigger --> Payload["สร้าง Payload:<br>• user_id, org_id<br>• entity_type, entity_id<br>• action (created/updated/deleted)<br>• old_values vs new_values<br>• ip_address, user_agent"]
    Payload --> AuditTable[("ตาราง audit_logs<br>(ห้ามแก้ไข/ลบ - Append Only)")]

    classDef dark fill:#1e293b,stroke:#475569,stroke-width:1px,color:#e2e8f0;
    classDef orange fill:#ea580c,stroke:#fb923c,stroke-width:2px,color:#fff;
    class Action,AuditTable orange;
    class Controller,Model,Trigger,Payload dark;
```

**คำอธิบายกฎการบันทึก (Audit Rules):**
1. ทุกเหตุการณ์ที่เปลี่ยนแปลงข้อมูลการเงิน, สถานะสิทธิ์, หรือข้อมูลธุรกิจหลัก จะถูกบันทึกอัตโนมัติ
2. ตาราง `audit_logs` เป็นแบบ Append-Only ไม่เปิดให้ลบหรือแก้ไข เพื่อความโปร่งใสและตรวจสอบย้อนหลังได้ 100%

---

## Group 2: CRM & Sales Pipeline (โมดูล 05-08)
**โมดูลที่เกี่ยวข้อง:** `05-crm`, `06-contacts`, `07-deals`, `08-quotations`

### 2.1 Diagram: Lead-to-Quote Sales Process Flow
กระบวนการขายตั้งแต่ได้ลูกค้ารายใหม่จนถึงปิดการขาย

```mermaid
flowchart TD
    Start(["รับ Lead / ลูกค้าใหม่"]) --> AddCust["1. บันทึกข้อมูลลูกค้า (Customers)<br>ออกรหัส Customer Code อัตโนมัติ"]
    AddCust --> AddContact["2. เพิ่มผู้ติดต่อหลัก (Contacts)"]
    AddContact --> CreateDeal["3. เปิดโอกาสการขาย (Deals)<br>กำหนดมูลค่าคาดการณ์ (Value)"]
    CreateDeal --> StageProgression["4. ดำเนินการตาม Sales Funnel<br>โทร/ประชุม/บันทึก Activity"]
    StageProgression --> MakeQuote["5. จัดทำใบเสนอราคา (Quotations)<br>ดึงรายการสินค้าจาก Catalog"]
    MakeQuote --> SendQuote["6. ส่งใบเสนอราคาให้ลูกค้าพิจารณา"]
    
    SendQuote --> Decision{"ลูกค้าตัดสินใจ?"}
    Decision -- อนุมัติ/ตกลง --> DealWon["7. Deal WON (ชนะการขาย)"]
    Decision -- ปฏิเสธ/ยกเลิก --> DealLost["8. Deal LOST (แพ้การขาย)<br>บันทึก Lost Reason & Note"]
    
    DealWon --> ToProject["ส่งต่อเปิดโปรเจกต์ (Group 3)"]
    DealWon --> ToInvoice["ส่งต่อออกใบแจ้งหนี้ (Group 4)"]

    classDef green fill:#16a34a,stroke:#4ade80,stroke-width:2px,color:#fff;
    classDef red fill:#dc2626,stroke:#f87171,stroke-width:2px,color:#fff;
    classDef blue fill:#0284c7,stroke:#38bdf8,stroke-width:2px,color:#fff;
    class Start,AddCust,AddContact,CreateDeal,StageProgression,MakeQuote,SendQuote blue;
    class DealWon,ToProject,ToInvoice green;
    class DealLost red;
```

---

### 2.2 Diagram: Deal Stage & Funnel Lifecycle (State Machine)
วงจรสถานะของ Deal ในแต่ละช่วงการขาย

```mermaid
stateDiagram-v2
    [*] --> New: ลูกค้าแสดงความสนใจ
    New --> Contacted: ติดต่อครั้งแรกแล้ว
    Contacted --> Qualified: ประเมินแล้วมีงบประมาณ/ความต้องการจริง
    Qualified --> Proposal: จัดส่งข้อเสนอ/ใบเสนอราคา
    Proposal --> Negotiation: เจรจาต่อรองเงื่อนไขและราคา
    
    Negotiation --> Won: ลูกค้าตกลงสั่งซื้อ (ปิดการขายสำเร็จ)
    Negotiation --> Lost: ลูกค้าไม่ตกลง
    Proposal --> Lost: ลูกค้าปฏิเสธ
    Qualified --> Lost: ยกเลิกความสนใจ
    
    Won --> [*]: ส่งต่องานให้ Project / Finance
    Lost --> [*]: บันทึกเหตุผลการแพ้เพื่อวิเคราะห์
```

**คำอธิบายสถานะ Deal:**
- **New:** โอกาสการขายใหม่ ยังไม่ได้ติดต่อเชิงลึก
- **Contacted:** ได้พูดคุยเบื้องต้นทางโทรศัพท์/LINE/อีเมล
- **Qualified:** ประเมินแล้วว่าลูกค้ามีคุณสมบัติและงบประมาณตรงกับบริการ
- **Proposal:** ส่งข้อเสนอหรือใบเสนอราคาอย่างเป็นทางการ
- **Negotiation:** อยู่ระหว่างการปรับแก้ขอบเขตงานหรือราคา
- **Won (Terminal Success):** ปิดการขายสำเร็จ ล็อคมูลค่าและเตรียมส่งต่องาน
- **Lost (Terminal Closed):** ปิดไม่สำเร็จ ต้องระบุเหตุผล (เช่น แพ้ราคา, ลูกค้ายกเลิกโครงการ)

---

### 2.3 Diagram: Quotation Issuance & Conversion Sequence
ลำดับการออกใบเสนอราคาและการแปลงเอกสาร

```mermaid
sequenceDiagram
    autonumber
    actor Sales as ฝ่ายขาย (Sales)
    participant QC as Quotation Controller
    participant Catalog as Product/Service Catalog
    participant DB as MariaDB
    actor Customer as ลูกค้า (Customer)
    participant Next as Project / Invoice Module

    Sales->>QC: สร้างใบเสนอราคาใหม่ (เลือก Deal + Customer)
    QC->>Catalog: ดึงรายการสินค้า/บริการ และราคามาตรฐาน
    Catalog-->>QC: ข้อมูลราคาและหน่วยนับ
    Sales->>QC: ระบุส่วนลด, ภาษี, เงื่อนไขการชำระเงิน
    QC->>DB: บันทึก Quotation (สถานะ: Draft &rarr; Sent)
    QC-->>Customer: ส่งเอกสารใบเสนอราคา (PDF/Email)
    
    Customer->>Sales: ยืนยันสั่งซื้อ / เซ็นอนุมัติ
    Sales->>QC: อัปเดตสถานะเป็น "Approved / Accepted"
    QC->>DB: อัปเดต Deal เป็น Won
    QC->>Next: Trigger ระบบเพื่อสร้าง Project หรือ Invoice อัตโนมัติ
```

---

## Group 3: Project Delivery & Execution (โมดูล 09-11)
**โมดูลที่เกี่ยวข้อง:** `09-projects`, `10-tasks`, `11-milestones`

### 3.1 Diagram: Project Initiation & Delivery Workflow
กระบวนการดำเนินโครงการและส่งมอบงาน

```mermaid
flowchart TD
    Init(["เริ่มโครงการ (จาก Deal Won หรือสร้างตรง)"]) --> Setup["1. กำหนดรายละเอียด Project<br>• Project Manager (PM)<br>• ขอบเขตงาน และระยะเวลา (Start/Due)"]
    Setup --> CreateMilestones["2. กำหนดงวดงาน (Milestones)<br>แบ่งเป็น Milestone 1, 2, 3..."]
    CreateMilestones --> CreateTasks["3. แตกงานย่อย (Tasks) ในแต่ละงวด<br>กำหนดผู้รับผิดชอบ (Assignee)"]
    CreateTasks --> WorkBoard["4. ทีมงานลงมือทำ (Kanban Board)<br>บันทึกความคืบหน้า และสถานะงาน"]
    
    WorkBoard --> CheckTasks{"งานใน Milestone<br>เสร็จครบ 100% หรือไม่?"}
    CheckTasks -- ยังไม่ครบ --> WorkBoard
    CheckTasks -- ครบถ้วน --> VerifyMilestone["5. PM และลูกค้าตรวจรับงวดงาน<br>(Milestone Sign-off)"]
    
    VerifyMilestone --> BillTrigger["6. แจ้งเตือนฝ่ายบัญชีให้ออก Invoice ตามงวด"]
    BillTrigger --> AllDone{"ส่งมอบครบทุก Milestone หรือไม่?"}
    AllDone -- ยังมีงวดต่อไป --> WorkBoard
    AllDone -- ครบทุกงวด --> CompleteProject["7. ปิดโครงการ (Project Completed)"]

    classDef blue fill:#0284c7,stroke:#38bdf8,stroke-width:2px,color:#fff;
    classDef green fill:#16a34a,stroke:#4ade80,stroke-width:2px,color:#fff;
    class Init,Setup,CreateMilestones,CreateTasks,WorkBoard,VerifyMilestone blue;
    class BillTrigger,CompleteProject green;
```

---

### 3.2 Diagram: Task Kanban Lifecycle & Blocker Handling (State Machine)
วงจรสถานะของงานย่อยและการจัดการปัญหาติดขัด

```mermaid
stateDiagram-v2
    [*] --> Todo: สร้างงานใหม่ กำหนด Assignee
    Todo --> InProgress: เริ่มต้นลงมือทำงาน
    
    InProgress --> Blocked: พบปัญหาติดขัด (เช่น รอลูกค้าส่งข้อมูล)
    Blocked --> InProgress: ปัญหาได้รับการแก้ไข กลับมาทำต่อ
    
    InProgress --> Done: งานเสร็จสิ้นตามข้อกำหนด
    Todo --> Cancelled: ยกเลิกงาน (ไม่จำเป็นต้องทำแล้ว)
    InProgress --> Cancelled: ยกเลิกงานระหว่างทำ
    Blocked --> Cancelled: ยกเลิกงานเนื่องจากติดขัดถาวร
    
    Done --> [*]: ผ่านการตรวจรับ
    Cancelled --> [*]: สิ้นสุดงาน
```

**คำอธิบายการจัดการงานติดขัด (Blocker Rules):**
- เมื่องานถูกเปลี่ยนสถานะเป็น `Blocked` ระบบจะบังคับให้ใส่เหตุผลที่ติดขัด (`blocker_reason`)
- Dashboard และการแจ้งเตือนจะแจ้งเตือน PM ทันทีเพื่อเข้าช่วยเหลือแก้ไขปัญหาอย่างรวดเร็ว

---

### 3.3 Diagram: Milestone Verification & Billing Trigger Sequence
ลำดับการตรวจรับงวดงานและส่งสัญญาณออกบิล

```mermaid
sequenceDiagram
    autonumber
    actor Dev as ทีมงาน (Dev/Delivery)
    actor PM as ผู้จัดการโครงการ (PM)
    actor Client as ลูกค้า (Client)
    participant MilestoneSvc as ระบบบริหาร Milestones
    participant Notify as Notification Service
    actor Finance as ฝ่ายบัญชี (Finance)

    Dev->>MilestoneSvc: ส่งงาน Task สุดท้ายในงวด (Task Status: Done)
    MilestoneSvc->>PM: แจ้งเตือน Tasks ใน Milestone ครบ 100%
    PM->>Client: ส่งมอบงวดงานเพื่อขอตรวจรับ (Review Request)
    Client->>PM: ลงนาม / ยืนยันตรวจรับงวดงาน (Sign-off)
    PM->>MilestoneSvc: อัปเดต Milestone เป็น "Approved / Completed"
    MilestoneSvc->>Notify: ส่ง Event "milestone.approved"
    Notify->>Finance: ส่ง Notification แจ้งเตือนฝ่ายการเงิน
    Finance->>Finance: เปิดหน้าสร้าง Invoice อ้างอิง Milestone นี้ทันที
```

---

## Group 4: Finance, Billing & Cost Control (โมดูล 12-16)
**โมดูลที่เกี่ยวข้อง:** `12-products`, `13-suppliers`, `14-invoices`, `15-payments`, `16-expenses`

### 4.1 Diagram: Order-to-Cash & Expense Control Flow
ผังกระบวนการเงินทั้งขาเข้า (รับชำระ) และขาออก (ค่าใช้จ่าย)

```mermaid
flowchart TD
    subgraph InboundMoney ["รายรับ (Order-to-Cash)"]
        Trigger["Deal Won / Milestone Done"] --> CreateInv["1. สร้างใบแจ้งหนี้ (Invoices)<br>ใช้ทศนิยม DECIMAL(18,2)"]
        CreateInv --> SendInv["2. ส่งใบแจ้งหนี้ให้ลูกค้า (Sent)"]
        SendInv --> PayAction["3. ลูกค้าชำระเงิน (เต็มจำนวน หรือ แบ่งจ่าย)"]
        PayAction --> RecordPay["4. บันทึกรับเงิน (Payments)<br>• ตรวจสอบยอดไม่ให้เกิน (Anti-Overpay)<br>• แนบหลักฐานสลิปโอนเงิน"]
        RecordPay --> UpdateInv["5. ระบบคำนวณยอดคงเหลือ และปรับสถานะบิล<br>(Partially Paid หรือ Paid)"]
    end

    subgraph OutboundMoney ["รายจ่าย (Expense & Supplier)"]
        Buy["สั่งซื้อของ / จ้างงานภายนอก"] --> Supp["บันทึกคู่ค้า (Suppliers)"]
        Supp --> Exp["บันทึกค่าใช้จ่าย (Expenses)"]
        Exp --> LinkProj["ผูกค่าใช้จ่ายกับ Project (ต้นทุนจริง)"]
    end

    UpdateInv --> ExecKPI["สรุป Cash-In สู่ Dashboard"]
    LinkProj --> ExecKPI

    classDef green fill:#16a34a,stroke:#4ade80,stroke-width:2px,color:#fff;
    classDef orange fill:#ea580c,stroke:#fb923c,stroke-width:2px,color:#fff;
    class CreateInv,SendInv,PayAction,RecordPay,UpdateInv,ExecKPI green;
    class Buy,Supp,Exp,LinkProj orange;
```

---

### 4.2 Diagram: Invoice Status Machine & Anti-Overpayment Settlement Sequence
วงจรสถานะใบแจ้งหนี้และการดักจับการชำระเงินเกิน

```mermaid
sequenceDiagram
    autonumber
    actor Finance as ฝ่ายการเงิน (Finance)
    participant InvAPI as Invoice Controller
    participant PaySvc as Payment Service (DB Transaction)
    participant DB as MariaDB Table
    participant Audit as Audit Log

    Finance->>InvAPI: POST /invoices/{id}/payments (ระบุจำนวนเงิน amount)
    InvAPI->>PaySvc: processPayment(invoice_id, amount, payment_method)
    
    critical Atomic DB Lock
        PaySvc->>DB: SELECT total_amount, paid_amount FROM invoices FOR UPDATE
        DB-->>PaySvc: ส่งยอดรวมและยอดที่จ่ายแล้ว
        Note over PaySvc: คำนวณ remaining_balance = total_amount - paid_amount
        alt amount > remaining_balance (ยอดชำระเกินหนี้คงเหลือ)
            PaySvc-->>InvAPI: ปฏิเสธ (422 Error: Anti-Overpayment Violation)
            InvAPI-->>Finance: แจ้งเตือน: จำนวนเงินเกินยอดหนี้คงเหลือ
        else amount <= remaining_balance (ยอดถูกต้อง)
            PaySvc->>DB: INSERT INTO payments (amount, invoice_id, paid_at)
            PaySvc->>DB: UPDATE invoices SET paid_amount = paid_amount + amount
            Note over PaySvc: ถ้า paid_amount == total_amount ปรับ status = 'paid'<br>ถ้า paid_amount < total_amount ปรับ status = 'partially_paid'
            PaySvc->>Audit: บันทึก Event "payment_received"
            DB-->>PaySvc: บันทึกสำเร็จ
            PaySvc-->>InvAPI: สำเร็จ (Success)
            InvAPI-->>Finance: แสดงผลการรับชำระเงินและพิมพ์ใบเสร็จรับเงิน
        end
    end
```

---

### 4.3 Diagram: Expense Recording & Project Cost Allocation Flow
การบันทึกค่าใช้จ่ายและการวิเคราะห์กำไรของโครงการ

```mermaid
flowchart LR
    Staff["พนักงาน / ฝ่ายจัดซื้อ"] -->|สร้างคำขอเบิกเงิน| NewExp["บันทึก Expense<br>• จำนวนเงิน + ภาษี<br>• หมวดหมู่ค่าใช้จ่าย<br>• แนบรูปใบเสร็จ/สลิป"]
    NewExp --> SelectProj{"เป็นค่าใช้จ่ายของ<br>โปรเจกต์หรือไม่?"}
    
    SelectProj -- ใช่ (Project Cost) --> BindProj["ผูกกับ project_id<br>(คิดเป็นต้นทุนตรงของโครงการ)"]
    SelectProj -- ไม่ใช่ (Overhead) --> BindCompany["คิดเป็นค่าใช้จ่ายทั่วไป<br>(Company Overhead)"]
    
    BindProj --> CalcMargin["ระบบคำนวณ Project Profit Margin:<br>รายรับบิล - ต้นทุนค่าใช้จ่ายจริง"]
    BindCompany --> CashOut["ตัดยอดบัญชีเงินสดออก (Cash-Out)"]
    CalcMargin --> ExecRep["รายงานกำไรโครงการบน Dashboard"]
```

---

## Group 5: Insights, Platform & Automation (โมดูล 17-23)
**โมดูลที่เกี่ยวข้อง:** `17-dashboard`, `18-reports`, `19-files`, `20-notifications`, `21-automation`, `22-import-export`, `23-api`

### 5.1 Diagram: Event-Driven Platform & Automation Pipeline
โครงสร้างการกระจาย Event สู่การแจ้งเตือนและการทำงานอัตโนมัติ

```mermaid
flowchart TD
    subgraph CoreEvents ["Domain Events ในระบบ"]
        E1["invoice.created"]
        E2["invoice.paid"]
        E3["deal.won"]
        E4["task.blocked"]
    end

    Dispatcher["Event Dispatcher & Queue Pipeline"]
    CoreEvents --> Dispatcher

    subgraph Listeners ["Subscribers & Action Runners"]
        L1["Notification Service<br>• In-App Notifications<br>• Email Alerts<br>• LINE Notify"]
        L2["Automation Engine<br>• ทำงานตาม Rules ที่ตั้งไว้<br>• Auto-create Task/Project"]
        L3["Webhook Dispatcher<br>• ส่งข้อมูลให้ระบบภายนอก (API)"]
        L4["Audit Trail Logger<br>• บันทึกลงตาราง audit_logs"]
    end

    Dispatcher --> L1
    Dispatcher --> L2
    Dispatcher --> L3
    Dispatcher --> L4

    classDef dark fill:#1e293b,stroke:#475569,stroke-width:1px,color:#e2e8f0;
    classDef blue fill:#0284c7,stroke:#38bdf8,stroke-width:2px,color:#fff;
    class Dispatcher blue;
    class E1,E2,E3,E4,L1,L2,L3,L4 dark;
```

---

### 5.2 Diagram: Executive & Financial Dashboard Metric Lineage
ผังการรวบรวมข้อมูลดิบสู่ตัวชี้วัด KPI ของผู้บริหาร

```mermaid
flowchart LR
    subgraph RawData ["ตารางข้อมูลดิบ (Transactional Data)"]
        T1["invoices<br>(total_amount, status)"]
        T2["payments<br>(amount, paid_at)"]
        T3["expenses<br>(amount, category)"]
        T4["deals<br>(value, stage)"]
        T5["tasks<br>(status, due_date)"]
    end

    subgraph Aggregator ["Aggregation & Calculation Layer"]
        C1["คำนวณ Invoiced Revenue<br>(ยอดที่ออกบิลแล้ว)"]
        C2["คำนวณ Real Cash-In<br>(เงินสดที่รับเข้าจริง)"]
        C3["คำนวณ Outstanding AR<br>(ลูกหนี้ค้างชำระ & เกินกำหนด)"]
        C4["คำนวณ Net Cash Flow<br>(เงินสดรับ - เงินสดจ่าย)"]
        C5["คำนวณ Project Delivery Health"]
    end

    subgraph UI ["Executive Dashboard View"]
        D1["📊 KPI Card: Invoiced vs Real Cash"]
        D2["📈 AR Aging Chart (30/60/90 วัน)"]
        D3["📉 Net Cash Flow & Expense Trend"]
        D4["🎯 Sales Funnel Conversion Rate"]
    end

    T1 --> C1
    T2 --> C2
    T1 & T2 --> C3
    T2 & T3 --> C4
    T4 & T5 --> C5

    C1 & C2 --> D1
    C3 --> D2
    C4 --> D3
    C5 --> D4
```

---

### 5.3 Diagram: File Attachment & Tenant Storage Security Flow
ลำดับการอัปโหลดไฟล์แนบและรักษาความปลอดภัยของไฟล์

```mermaid
sequenceDiagram
    autonumber
    actor User as ผู้ใช้งาน (User)
    participant API as File Controller
    participant Storage as File Storage (Local / S3)
    participant DB as MariaDB (files table)

    User->>API: อัปโหลดไฟล์ (สลิป/เอกสารสัญญา) พร้อม entity_type และ entity_id
    API->>API: ตรวจสอบ MIME type, ไวรัส, และขนาดไฟล์ (Max size limit)
    API->>API: เข้ารหัสชื่อไฟล์ (Generate UUID Filename) ป้องกัน Path Traversal
    API->>Storage: บันทึกไฟล์ลง Storage ในโฟลเดอร์แยกตาม org_id
    Storage-->>API: ที่อยู่ไฟล์ (Storage Path)
    API->>DB: บันทึกข้อมูลลงตาราง files (org_id, file_path, original_name, mime_type, file_size)
    DB-->>API: File ID
    API-->>User: ผลการอัปโหลดสำเร็จ พร้อม URL สำหรับเปิดดูตามสิทธิ์
```

---

## Group 6: Operations & Advanced Extensions (Phase 7-18)
**โมดูลที่เกี่ยวข้อง:** `24-purchase-orders`, `25-inventory`, `26-employees`, `27-attendance-leave`, `28-payroll`, `29-ai-assistant`, `30-accounting-integration`, `31-customer-portal`

### 6.1 Diagram: Procurement & Inventory Movement Flow
กระบวนการจัดซื้อและการเคลื่อนไหวของสินค้าคงคลัง

```mermaid
flowchart TD
    Start(["ต้องการสั่งซื้อสินค้า/วัตถุดิบ"]) --> CreatePO["1. สร้างใบสั่งซื้อ (Purchase Order - PO)<br>ระบุ Supplier, รายการสินค้า และราคา"]
    CreatePO --> ApprovePO["2. ขออนุมัติใบสั่งซื้อ (PO Approval)"]
    ApprovePO --> SendSupplier["3. ส่ง PO ให้ Supplier จัดส่งสินค้า"]
    
    SendSupplier --> GoodsReceipt["4. รับสินค้าเข้าคลัง (Goods Receipt / Receive)"]
    GoodsReceipt --> CheckQC{"ตรวจนับและ QC สินค้า"}
    
    CheckQC -- ผ่านเกณฑ์ --> UpdateStock["5. เพิ่มสต็อกสินค้าในคลัง (Inventory In)<br>บันทึก Movement Log (IN)"]
    CheckQC -- ไม่ผ่าน --> ReturnGoods["ส่งคืนสินค้า / เคลมสินค้ากับ Supplier"]
    
    UpdateStock --> MatchInvoice["6. จับคู่ใบแจ้งหนี้ Supplier กับ PO (3-Way Matching)"]
    MatchInvoice --> RecordExpense["7. บันทึกเป็น Expense / เจ้าหนี้การค้า"]

    classDef blue fill:#0284c7,stroke:#38bdf8,stroke-width:2px,color:#fff;
    classDef green fill:#16a34a,stroke:#4ade80,stroke-width:2px,color:#fff;
    class CreatePO,ApprovePO,SendSupplier,GoodsReceipt,CheckQC blue;
    class UpdateStock,MatchInvoice,RecordExpense green;
```

---

### 6.2 Diagram: Phase 16B Payroll, Policy & GL Flow
Phase 16B ใช้ `users` เป็น employee identity; attendance, leave และ OT ยังไม่อยู่ใน calculation scope

```mermaid
flowchart TD
    User["User"] --> Profile["Employee Payroll Profile<br>salary / recurring allowance & deduction"]
    TaxPolicy["Tax Policy<br>effective-dated brackets"] --> Run["Payroll Run: draft<br>lock policy IDs"]
    SocialPolicy["Social Security Policy<br>effective-dated rates & ceiling"] --> Run
    Profile --> Calculate["Calculate payroll"]
    Run --> Calculate
    Calculate --> Items["Payroll Items<br>immutable calculation snapshot"]
    Items --> Approve["Approve run"]
    Approve --> GLAccrual["GL: debit salary/SS expense<br>credit payroll/tax/SS liabilities"]
    Approve --> Payslip["Owner-or-finance payslip PDF"]
    Approve --> Workpaper["PND1 / SSO workpaper CSV"]
    Approve --> Pay["Mark paid"]
    Pay --> GLSettlement["GL: debit payroll payable<br>credit mapped bank/cash"]
```

---

### 6.3 Diagram: External Accounting & Customer Portal Integration Grid
ผังการเชื่อมต่อระบบบัญชีภายนอกและการเปิดพอร์ทัลให้ลูกค้า

```mermaid
flowchart LR
    subgraph CoreERP ["Company OS / Lightweight ERP"]
        InvModule["Invoices Module"]
        PayModule["Payments Module"]
        ProjModule["Projects & Tasks"]
    end

    subgraph IntegrationLayer ["Integration & Security Gateway"]
        AuthBridge["Customer Portal Auth<br>(Magic Link / Token)"]
        AccountingSync["Accounting Sync Worker<br>(REST API / OAuth2)"]
    end

    subgraph ExternalServices ["ระบบภายนอก (External Platforms)"]
        PortalUI["🌐 Customer Portal Web<br>• ลูกค้าดูความคืบหน้าโปรเจกต์<br>• ตรวจสอบบิลและดาวน์โหลดใบเสร็จ"]
        FlowAccount["📦 FlowAccount API"]
        PEAK["📊 PEAK Engine API"]
        XeroQB["💼 Xero / QuickBooks"]
    end

    ProjModule --> AuthBridge
    InvModule --> AuthBridge
    AuthBridge --> PortalUI

    InvModule --> AccountingSync
    PayModule --> AccountingSync
    AccountingSync --> FlowAccount
    AccountingSync --> PEAK
    AccountingSync --> XeroQB
```

---

## 🎯 สรุปการนำไปใช้งาน (Usage & Navigation)

1. **สำหรับ Developers:** สามารถใช้อ้างอิง Entity State Transitions และ Service Workflow ในการเขียน Code และ Unit Tests
2. **สำหรับ Project Managers / Business Analysts:** ใช้ตรวจสอบความครบถ้วนของกระบวนการทำงานและเกณฑ์การตรวจรับงาน (Acceptance Criteria)
3. **สำหรับ Stakeholders / Executives:** ใช้ทำความเข้าใจภาพรวมการไหลของข้อมูลตั้งแต่ต้นน้ำ (Lead/Customer) จนถึงปลายน้ำ (Cash-In & Financial Statements)
