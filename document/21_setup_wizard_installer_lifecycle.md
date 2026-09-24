# Web Setup Wizard Architecture & Installer Lifecycle

> สถานะ: **เสร็จสมบูรณ์และผ่านการตรวจรับ 100% (Complete & Closed ณ 2026-09-22)**

```mermaid
flowchart TD
    subgraph AccessGuard["1. Security Gate & Entry Points"]
        Req["Request: GET /setup"] --> CheckInstalled{"Is Installed?\nstorage/app/installer/installed.json"}
        CheckInstalled -- "Yes (Locked)" --> Resp404["HTTP 404 Not Found\nInstallation Closed"]
        CheckInstalled -- "No" --> CheckToken{"Is Enabled?\nactivationToken() !== null"}
        CheckToken -- "No" --> Resp403["HTTP 403 Forbidden\nInstaller is disabled"]
        CheckToken -- "Yes" --> WizardUI["Setup Wizard Steps (1-4)"]
        
        TokenSource["Activation Token (>= 32 chars)\n1. Server Env: SETUP_TOKEN\n2. File: backend/setup/enable.php"] -.-> CheckToken

        RequirementsReq["Request: GET /setup/system-requirements.php"] --> RequirementsLock{"Is Installed?\ninstalled.json"}
        RequirementsLock -- "Yes (Locked)" --> Requirements404["HTTP 404 Not Found"]
        RequirementsLock -- "No" --> RequirementsReport["Public System Requirements Report\n• No Setup Token\n• No secrets or phpinfo()\n• PHP, extensions, runtime, release checks"]
        RequirementsReport --> Advice["Failed / Warning Guidance"]
        Advice --> SafeFix["Safe Fix (CSRF)\nCreate only missing storage/\nor bootstrap/cache/\nwhen parent is writable"]
    end

    subgraph StepFlow["2. Multi-Step Wizard Flow"]
        WizardUI --> Step1["Step 1: Security & Preflight\n• PHP 8.3+, PDO MySQL, OpenSSL, mbstring, fileinfo\n• DOM, SimpleXML, cURL\n• Writable: storage/, bootstrap/cache/, root\n• Clean Release: ! is_file(.env)\n• Verify Setup Token (hash_equals)"]
        Step1 --> Step2["Step 2: Server & Database\n• App Name & App URL (http/https)\n• Host, Port (1-65535), DB Name, Username\n• Test Connection: PDO SELECT 1"]
        Step2 --> Step3["Step 3: Organization & Owner\n• Org Name, Legal Name, Tax ID\n• Owner Name, Email\n• Owner Password (>= 12 chars)"]
        Step3 --> Step4["Step 4: Install Confirmation\n• Type 'INSTALL' to confirm execution"]
    end

    subgraph InstallEngine["3. Installer Execution Engine (Flock Lock)"]
        Step4 --> Lock["Acquire File Lock (install.lock)\nPrevent Concurrent Execution"]
        Lock --> EmptyCheck{"Check Target Database\nSHOW TABLES LIKE 'migrations'"}
        EmptyCheck -- "Migrations Found" --> AbortDB["Abort: Refusing to overwrite DB"]
        EmptyCheck -- "Empty DB" --> WriteEnv["Write .env Atomically\n• Clone .env.example\n• Generate random 32-byte APP_KEY\n• Set chmod 0600\n• Clear config/services cache"]
        
        WriteEnv --> RunMigrate["Run Database Migrations\nArtisan migrate --force"]
        RunMigrate --> Provision["Provision Owner & Master Data\n• OrganizationProvisioner::registerOwner()\n• Seed 7 System Roles & Permissions Catalog\n• Branch, Division, Department, Sequences"]
        
        Provision --> WriteLock["Write Install Lock\nstorage/app/installer/installed.json"]
        WriteLock --> Cleanup["Cleanup Activation File\n@unlink(backend/setup/enable.php)"]
        Cleanup --> Unlock["Release File Lock (install.lock)"]
        Unlock --> SuccessRedirect["Redirect: /login?installed=1\nRegenerate Session"]
    end
```

### มาตรการความปลอดภัยเชิงสถาปัตยกรรม (Security Architecture Guardrails)

1. **Direct Access Protection:** โฟลเดอร์ซอร์สโค้ดและแกนของตัวติดตั้ง `backend/setup/` ถูกปิดกั้นจากการเข้าถึงผ่านเว็บโดยตรงด้วยทั้ง Root `.htaccess` (`RewriteRule ^backend/setup(?:/|$) - [F,L]`) และ `backend/setup/.htaccess` (`Require all denied`)
2. **Double Activation Guard:** ตัวติดตั้งปิดการทำงานเป็นค่าเริ่มต้น ต้องเปิดใช้งานด้วย Setup Token ที่มีความยาวอย่างน้อย 32 ตัวอักษร ผ่านตัวแปรสภาพแวดล้อม `SETUP_TOKEN` หรือไฟล์ชั่วคราว `backend/setup/enable.php`
3. **Clean Release & No Overwrite:** ปฏิเสธการติดตั้งบนไดเรกทอรีที่มีไฟล์ `.env` อยู่แล้ว และปฏิเสธฐานข้อมูลที่มีตาราง `migrations` อยู่เดิม เพื่อป้องกันการเขียนทับระบบหรือข้อมูลการผลิตเดิมโดยไม่ได้ตั้งใจ
4. **Atomic Operations & Lockout:** การสร้าง `.env` ทำงานผ่าน Temporary File + Atomic Rename พร้อมตั้งสิทธิ์ `chmod 0600`, ใช้ File Lock ป้องกันคำขอติดตั้งซ้ำซ้อน และหลังติดตั้งสำเร็จไฟล์ `enable.php` จะถูกลบทิ้งทันทีพร้อมเขียน Lock `installed.json` ส่งผลให้หน้า `/setup` ตอบกลับด้วย HTTP 404 ถาวร
5. **Public Readiness Check:** `/setup/system-requirements.php` เปิดได้ก่อนติดตั้งโดยไม่ใช้ Setup Token เพื่อให้ผู้ดูแล server แก้ environment ได้ก่อนเปิด installer; ไม่เปิดเผย secret หรือ `phpinfo()`. การแก้ไขจากหน้าเว็บจำกัดเฉพาะการสร้าง `storage/` หรือ `bootstrap/cache/` ที่ยังไม่มี, ตรวจ CSRF และทำได้เมื่อ parent directory เขียนได้เท่านั้น

ดูรายละเอียดเพิ่มเติมที่ [`../docs/SETUP_WIZARD.md`](../docs/SETUP_WIZARD.md).
