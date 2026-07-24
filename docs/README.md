# ERP Documentation

เอกสารออกแบบระบบ Company OS / Lightweight ERP

**เริ่มที่:** [`../PROJECT.md`](../PROJECT.md) — นิยามโปรเจกต์ + stack + authority ของเอกสาร

## โครงสร้าง

| Path | คำอธิบาย |
| --- | --- |
| [`../PROJECT.md`](../PROJECT.md) | **Project definition** (ตัวตน, stack, phase, authority) |
| [`../MVP_SCOPE.md`](../MVP_SCOPE.md) | ขอบเขต MVP ที่ล็อกแล้ว (authority สูงสุดเรื่อง scope) |
| [`ARCHITECTURE_DECISIONS.md`](./ARCHITECTURE_DECISIONS.md) | กฎ override schema/plan |
| [`SECURITY_REQUIREMENTS.md`](./SECURITY_REQUIREMENTS.md) | ข้อกำหนด security |
| [PHASE_1_LOGIN_IMPLEMENTATION.md](./PHASE_1_LOGIN_IMPLEMENTATION.md) | Phase 1 foundation (auth/RBAC/invite) |
| [VALIDATION_RULES.md](./VALIDATION_RULES.md) | กฎ validation ฝั่ง server |
| [PHASE_ACCEPTANCE_CRITERIA.md](./PHASE_ACCEPTANCE_CRITERIA.md) | DoD ราย Phase |
| [ROUTES_AND_SCREENS.md](./ROUTES_AND_SCREENS.md) | หน้าและ route ราย Phase |
| [SEED_DATA.md](./SEED_DATA.md) | default seed และ UAT dataset |
| [`database/DATABASE.md`](./database/DATABASE.md) | **Schema กลาง** — ต้อง sync กับ AD ก่อน migrate |
| [`modules/README.md`](./modules/README.md) | ดัชนี module docs (31 ไฟล์) |
| [`modules/*.md`](./modules/) | รายละเอียดต่อ module: หน้าที่, workflow, data flow, DB |
| [`../ERP_FEATURE_PLAN.md`](../ERP_FEATURE_PLAN.md) | แผนยาว / backlog (authority ต่ำสุดเมื่อขัด MVP) |
| [`../backend/`](../backend/) | Laravel app (ยังไม่ scaffold) |

## กฎสำคัญ

1. ถ้าเอกสารขัดกัน ใช้ลำดับใน [`PROJECT.md`](../PROJECT.md) §2
2. แก้โครงสร้างฐานข้อมูลที่ `database/DATABASE.md` **ก่อนเสมอ** (หลัง apply AD)
3. แล้วค่อยอัปเดต module doc ที่เกี่ยวข้องใน `modules/`
4. ห้ามให้ module ใดนิยามตาราง/คอลัมน์ขัดกับ schema กลาง + AD
5. **อย่า implement นอก `MVP_SCOPE.md` ก่อน DoD ของ flow หลัก**

## Core flow

```text
CRM → Deal → Invoice → Payment → Project → Task → Dashboard
```




