# ERP System Design System & UI Guidelines

เอกสารนี้คือกฎและข้อกำหนดการออกแบบ UI/UX สำหรับระบบ ERP (มาตรฐานกลางสำหรับ AI Agents: GPT, Gemini, Grok และนักพัฒนาทุกคน)

---

## 1. Visual Identity & Brand Essence

- **Design Tone**: Enterprise, Modern, Professional, Clean, Premium
- **Core Aesthetic**: High contrast, subtle borders (`border-slate-200/80`), soft shadows (`shadow-sm hover:shadow-md`), clean whitespace, readable typography, harmonized status badges.
- **Layout Model**: App Shell พร้อม **Left Sidebar Navigation** (Collapsible/Responsive) + **Sticky Top Header**

---

## 2. Color Palette & Tokens

### Primary & Base Colors
- **App Background**: Slate 50 (`#F8FAFC`)
- **Card Background**: White (`#FFFFFF`)
- **Primary Accent**: Indigo 600 (`#4F46E5`), Indigo 700 (`#4338CA`)
- **Sidebar Surface**: Slate 900 (`#0F172A`)
- **Text Primary**: Slate 900 (`#0F172A`)
- **Text Secondary**: Slate 500 (`#64748B`)
- **Borders & Dividers**: Slate 200 (`#E2E8F0`)

### Status & State Palette
- **Success / Active / Won**: Soft Emerald (`bg-emerald-50 text-emerald-700 border-emerald-200`)
- **Warning / Pending / In Progress**: Soft Amber (`bg-amber-50 text-amber-700 border-amber-200`)
- **Danger / Inactive / Overdue / Lost**: Soft Rose (`bg-rose-50 text-rose-700 border-rose-200`)
- **Info / Primary Action**: Soft Indigo (`bg-indigo-50 text-indigo-700 border-indigo-200`)
- **Special / Roles / VIP**: Soft Violet (`bg-violet-50 text-violet-700 border-violet-200`)
- **Neutral / Draft**: Soft Slate (`bg-slate-100 text-slate-700 border-slate-200`)

---

## 3. Typography Rules

- **Primary Font**: `Inter`, `Outfit`, หรือ `Figtree` (sans-serif)
- **Hierarchy**:
  - `Page Title`: `text-2xl font-bold text-slate-900 tracking-tight`
  - `Section Header`: `text-lg font-semibold text-slate-800`
  - `Body Text`: `text-sm text-slate-600 leading-relaxed`
  - `Caption / Helper`: `text-xs text-slate-500`
  - `KPI Numbers`: `text-3xl font-bold text-slate-900 tracking-tight font-sans`

---

## 4. Standard Reusable Components (`@/Components/UI/`)

1. **`StatCard`** (`@/Components/UI/StatCard`):
   - การ์ดสรุป KPI/Metric พร้อม Icon, Title, Big Number, และ Trend/Alert Badge
2. **`Badge`** (`@/Components/UI/Badge`):
   - Pill Status Badge สำหรับแสดงสถานะ Active, Invited, Inactive, Overdue
3. **`PageHeader`** (`@/Components/UI/PageHeader`):
   - ส่วนหัวของทุกหน้า ประกอบด้วย Title, Subtitle, Breadcrumb และ Action Buttons (e.g., Invite User, Create Customer)
4. **`Card`** (`@/Components/UI/Card`):
   - คอนเทนเนอร์การ์ดมาตรฐาน (`rounded-xl border border-slate-200/80 bg-white p-6 shadow-sm`)
5. **`DataTable`** (`@/Components/UI/DataTable`):
   - ตารางข้อมูลมาตรฐานพร้อม Header แถบสีอ่อน, Hover State, Empty State และ Pagination/Action slots

---

## 5. UI Rules for AI Code Generation (GPT & Gemini Enforcement)

1. **ห้ามใช้ Plain Gray / Browser Default**: ต้องใช้ Slate palette (`slate-900`, `slate-500`, `slate-200`) ร่วมกับ Indigo accent
2. **ห้ามสร้าง Table แบบเรียบเปลือย**: ต้องห่อหุ้มด้วย `Card` หรือ `border border-slate-200/80 rounded-xl overflow-hidden`
3. **ทุกหน้าต้องมี `PageHeader`**: มี Title และ Description เสมอ
4. **ทุกสถานะต้องใช้ `Badge`**: เช่น Status `active` -> Soft Emerald, `invited` -> Soft Amber, `inactive` -> Soft Rose
5. ** Responsive Design**: ใช้ Grid System (`grid-cols-1 md:grid-cols-2 lg:grid-cols-4`) รองรับ Mobile, Tablet, Desktop
