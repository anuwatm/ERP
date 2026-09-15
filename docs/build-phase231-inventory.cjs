// Documentation audit only. Run from repository root: node docs/build-phase231-inventory.cjs
// Requires installed backend dependencies and PHP; does not migrate or write application data.
const fs = require('node:fs');
const path = require('node:path');
const cp = require('node:child_process');
const ts = require('../backend/node_modules/typescript');
const root = path.resolve(__dirname, '..');
process.chdir(root);
const read = f => fs.readFileSync(f, 'utf8');
const walk = d => fs.readdirSync(d, { withFileTypes: true }).flatMap(e => e.isDirectory() ? walk(`${d}/${e.name}`) : [`${d}/${e.name}`]);
const unique = a => [...new Set(a)];
const manifest = JSON.parse(read('docs/phase231-inventory.manifest.json'));
const routes = JSON.parse(cp.execFileSync('php', ['artisan', 'route:list', '--json'], { cwd: 'backend', encoding: 'utf8', maxBuffer: 8e6 }));
const routeByName = name => {
    const named = routes.find(r => r.name === name && /POST|PUT|PATCH|DELETE/.test(r.method)) || routes.find(r => r.name === name);
    // Breeze names the GET login/register/confirm route; its form POST shares the URI.
    return named && ['login', 'register', 'password.confirm'].includes(name) ? routes.find(r => r.uri === named.uri && r.method === 'POST') || named : named;
};
const ui = walk('backend/resources/js').filter(f => /\.[jt]sx?$/.test(f)).map(file => {
    const source = read(file), sf = ts.createSourceFile(file, source, ts.ScriptTarget.Latest, true, /tsx$/.test(file) ? ts.ScriptKind.TSX : ts.ScriptKind.TS);
    const forms = [], useForms = [], literals = [];
    function visit(n) {
        if (ts.isStringLiteralLike(n)) literals.push(n.text);
        if (ts.isCallExpression(n) && n.expression.getText(sf) === 'useForm') useForms.push({ line: sf.getLineAndCharacterOfPosition(n.getStart()).line + 1, source: n.getText(sf) });
        if (ts.isJsxElement(n) && n.openingElement.tagName.getText(sf) === 'form') {
            const ancestors = []; let p = n.parent;
            while (p) { if (ts.isJsxElement(p)) ancestors.push(p.openingElement.tagName.getText(sf)); p = p.parent; }
            forms.push({ line: sf.getLineAndCharacterOfPosition(n.getStart()).line + 1, source: n.getText(sf), surface: ancestors.includes('Modal') ? 'existing modal' : 'embedded page/card/sidebar form' });
        }
        ts.forEachChild(n, visit);
    }
    visit(sf);
    return { file, source, forms, useForms, literals: unique(literals) };
});
const methodCache = new Map();
function methods(file) {
    if (methodCache.has(file)) return methodCache.get(file);
    if (!fs.existsSync(file)) return [];
    const s = read(file), matches = [...s.matchAll(/(?:public|protected|private)\s+(?:static\s+)?function\s+(\w+)\s*\(/g)];
    const result = matches.map((m, i) => ({ name: m[1], file, line: s.slice(0, m.index).split('\n').length, source: s.slice(m.index, matches[i + 1]?.index ?? s.length) }));
    methodCache.set(file, result); return result;
}
function contract(route) {
    if (!route || !route.action.startsWith('App\\')) return { fields: [], sources: [], guards: [] };
    const [cls, action = '__invoke'] = route.action.split('@'), file = `backend/app/${cls.slice(4).replaceAll('\\', '/')}.php`;
    const all = methods(file), first = all.find(x => x.name === action), used = [];
    function add(m) { if (!m || used.includes(m)) return; used.push(m); for (const x of m.source.matchAll(/\$this->(\w+)\(/g)) add(all.find(y => y.name === x[1])); }
    add(first);
    // FormRequest validation lives outside its controller method.
    if (first) for (const x of first.source.slice(0, first.source.indexOf('{')).matchAll(/(\w+Request)\s+\$/g)) {
        const req = walk('backend/app/Http/Requests').find(f => f.endsWith(`/${x[1]}.php`));
        if (req) used.push(...methods(req).filter(m => ['rules', 'authorize'].includes(m.name)));
    }
    const fields = unique(used.flatMap(m => [...m.source.matchAll(/['"]([\w.*]+)['"]\s*=>\s*(?:\[\s*(?:['"](?:required\w*|nullable|sometimes|boolean|array|string|numeric|integer|email|uuid|date|file|confirmed|current_password|alpha_dash)(?::[^'"]*)?['"]\s*[,\]]|Rule::)|['"]required(?:[|:][^'"]*)?['"])/g)].map(x => x[1])));
    return { fields, sources: used.map(({ file, line, name }) => ({ file, line, method: name })), guards: used.flatMap(m => m.source.split('\n').flatMap((s, i) => /abort_(?:if|unless)|throw |ValidationException::|Only .* (?:edit|paid|post|approv)|status.*(?:===|!==)|locked|immutable/i.test(s) ? [{ file: m.file, line: m.line + i, text: s.trim() }] : [])) };
}
const stateNotes = {
    'Invoice': 'Edit: backend อนุญาต draft เท่านั้น; UI ปัจจุบันแสดง Edit เมื่อไม่ void และ paid_amount <= 0 จึงกว้างกว่า backend (G02). Create เลือก draft/sent; sent มี GL posting.',
    'Expense': 'Edit เฉพาะ draft; approve/pay/reject เป็นคนละ action; เก็บ receipt และ supplier/project/PO เดิม.',
    'Quotation': 'UI มี Create เท่านั้น; backend update อนุญาต draft/sent. ห้ามเพิ่ม Edit ให้ approved/converted/rejected.',
    'Purchase order': 'UI มี Create เท่านั้น; backend update อนุญาต draft เท่านั้น; approve draft/sent, cancel ห้าม received/closed/cancelled.',
    'Manual journal': 'Create แล้ว post ผ่าน JournalPostingService; lines ต้อง debit=credit, บัญชี active/postable, period เปิด; posted แก้ผ่าน reverse เท่านั้น ไม่มี Edit route.',
    'Accounting period': 'Create ห้ามช่วงทับซ้อน; close เฉพาะ open และไม่มี draft journal; ไม่มี Edit route.',
    'Role permissions': 'owner role immutable; ไม่สร้าง editor ให้ owner. บันทึก permission_ids ของ role เดิม.',
    'Edit user': 'org scope, branch/division/department chain, ห้ามถอด owner คนสุดท้าย; role/person_id อยู่ใน payload เดิม.',
    'Invite user': 'สร้าง invitation ผ่าน users.invite; token acceptance เป็น security exception แยก.',
    'Document version': 'DocumentService::addVersion: document ต้อง org เดียวกันและ active; ห้าม overwrite version เดิม; upload ใหม่.',
    'Document link': 'DocumentService ตรวจ supported type + parentAccess + org; ต้องคง role และ parent id.',
    'Payroll run': 'UI สร้าง run; calculate ได้ draft/calculated, approve ได้ calculated, pay ได้ approved; ห้ามเปิด Edit run approved/paid.',
    'Capitalize asset': 'FixedAssetService: source ต้องยังไม่ capitalize; expense ต้อง approved/paid หรือ goods receipt มีต้นทุน; salvage < cost; ไม่มี Edit asset route. Dispose/write-off เฉพาะ active เป็น command แยก.',
    'Stock count': 'ส่ง items[] จากฟอร์ม product/count; backend บันทึก stock_count เป็น posted พร้อม adjustment; ไม่มี Edit posted count route.',
    'Stock transfer': 'source/destination และ lot ต้องตรง product/org; stock ต้นทางต้องพอ; บันทึก movements คู่กัน ไม่มี Edit route.',
    'Goods receipt': 'อ้าง PO/PO item; รับไม่เกิน remaining quantity; lot ต้องตรง product และ bin ต้องตรง warehouse; บันทึก stock/GL ไม่สร้าง Edit GRN.',
    'Leave request': 'สร้าง draft; submit draft เท่านั้นพร้อม overlap/balance checks; cancel ได้ submitted; ไม่มี Edit route.',
    'Attendance summary': 'สร้าง summary พร้อม cutoff; lock/reverse เป็นคำสั่งแยก; ไม่มี Edit route สำหรับ locked summary.',
    'Attendance privacy policy': 'G01: controller updateAttendancePrivacy มีอยู่ แต่ไม่มี named route settings.organization.attendance-privacy.update; ต้องแก้ route integration ก่อน rollout.',
    'Notification channel': 'Create channel พร้อม encrypted config; UI update ปัจจุบันเป็น toggle is_enabled เท่านั้น ไม่ใช่ full-config Edit.',
    'Vendor bill intake': 'Portal grant/session, supplier/org และไฟล์; ไม่ใช้ internal auth.permissions แทน portal authorization.',
    'Deal': 'SalesAccess ตรวจ org/owner และ customer/contact chain; lost ต้อง reason; won/lost timestamps เป็น backend; ไม่พบ stage lock สำหรับ update.',
    'Activity': 'SalesAccess ทั้ง original/target entity; owner/org; entity_type/entity_id ต้องคงเดิม; completed_at เก็บเดิม; Done เป็น command.',
    'Customer': 'SalesAccess org/owner; เก็บ owner เดิมที่ไม่อยู่ในตัวเลือก; delete มี dependency guard แยก.',
    'Contact': 'SalesAccess ผ่าน parent customer; update ไม่เปลี่ยน parent; is_primary มี uniqueness handling.'
};
const serviceEvidence = {
    'Manual journal': ['JournalPostingService', 'AccountingPeriodService'],
    'Capitalize asset': ['FixedAssetService', 'JournalPostingService'],
    'Payroll run': ['PayrollService'],
    'Leave request': ['HrAttendanceService'],
    'Attendance summary': ['HrAttendanceService'],
    'Document and initial version': ['DocumentService'],
    'Document version': ['DocumentService'],
    'Document link': ['DocumentService'],
    'Workflow definition and steps': ['WorkflowEngineService'],
    'Workflow delegation': ['WorkflowEngineService']
};
function generalState(name, contracts, hasUpdate) {
    if (stateNotes[name]) return stateNotes[name];
    const guards = unique(contracts.flatMap(c => c.guards.map(g => g.text)));
    return (hasUpdate ? 'Create/update ตาม route ที่มีจริง; ' : 'เฉพาะ action ที่ระบุ; ไม่เพิ่ม Edit route; ') + (guards.length ? guards.join(' / ') : 'คง validation/reference scope และ service authorization ตาม source contract; ไม่อนุมานสิทธิ์จากปุ่ม UI');
}
const rows = [];
function addRow(page, name, routeNames, kind, form, extraFields = []) {
    const file = `backend/resources/js/Pages/${page}.tsx`, source = ui.find(x => x.file === file);
    const resolved = routeNames.map(n => routeByName(n));
    const contracts = resolved.map(contract);
    if (kind === 'gap') contracts.push(contract({ action: 'App\\Http\\Controllers\\Settings\\OrganizationSettingsController@updateAttendancePrivacy' }));
    const uiFields = unique([...form.source.matchAll(/(?:name=|setData\(\s*)['"]([^'"]+)|\.data\.([a-zA-Z_]\w*)/g)].map(m => m[1] || m[2]).concat(extraFields));
    const fields = unique(contracts.flatMap(c => c.fields).concat(uiFields));
    const refs = fields.filter(f => /(?:_ids?|_code|_type)$/.test(f) && !/tax_id|person_id|qr_type|account_type|employment_type|payment_method|activity_type|customer_type/.test(f));
    const lines = fields.filter(f => /^(?:items|lines|steps|formats|preferences|brackets_json)(?:\.|$)/.test(f));
    const attachments = fields.filter(f => /^(?:file|attachment|receipt|logo|statement)$/.test(f));
    const backendSources = unique(contracts.flatMap(c => c.sources.map(s => `${s.file}:${s.line} (${s.method})`)));
    for (const service of serviceEvidence[name] || []) backendSources.push(`backend/app/Services/${service}.php (service state guards)`);
    const status = kind || 'pending';
    rows.push({ id: `F${String(rows.length + 1).padStart(3, '0')}`, module: page.split('/')[0], page, name, status, uiSource: `${file}:${form.line}`, fields, uiFields, references: refs, lineItems: lines, attachments,
        actions: routeNames.map((n, i) => ({ name: n, method: resolved[i]?.method || 'MISSING', uri: resolved[i] ? `/${resolved[i].uri}` : 'MISSING', middleware: resolved[i]?.middleware || [], controller: resolved[i]?.action || 'MISSING' })),
        businessState: generalState(name, contracts, routeNames.some(n => /update/.test(n))),
        oldUI: status === 'migrated' ? 'Sales sidebar/inline ก่อน rollout' : form.surface,
        currentUI: status === 'migrated' ? 'CreateEditDialog: normal/expanded/minimized + draft guard' : form.surface,
        targetUI: status === 'exception' ? 'คง specialized/security/action flow; ไม่บังคับ CRUD dialog' : 'shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI',
        testResult: status === 'migrated' ? 'ผลรอบก่อน: Sales 12 tests/196 assertions + history 9 tests; TypeScript/ESLint/build ผ่าน; browser regression ยังไม่ผ่านการตรวจ' : status === 'gap' ? 'static audit พบ missing route; ยังไม่แก้/ไม่ทดสอบ browser' : 'static source/route audit เท่านั้น; ไม่ได้รัน runtime/browser ของฟอร์มนี้',
        routeBindings: unique(resolved.filter(Boolean).flatMap(r => [...r.uri.matchAll(/\{([^}]+)\}/g)].map(m => m[1]))),
        backendSources, guardEvidence: contracts.flatMap(c => c.guards), uiFormSource: form.source,
        exceptionReason: status === 'exception' ? (/policy/i.test(name) ? 'security policy' : /import/i.test(name) ? 'file import workflow' : /Clock/.test(name) ? 'attendance event command' : 'posting/payment/generation/decision command; ไม่ใช่แก้ entity ทั่วไป') : null });
}
for (const [page, specs] of Object.entries(manifest)) {
    const source = ui.find(x => x.file === `backend/resources/js/Pages/${page}.tsx`);
    if (!source || source.forms.length !== specs.length) throw Error(`Unmapped form count: ${page} (${source?.forms.length} vs ${specs.length})`);
    specs.forEach((spec, i) => { const [name, names, status] = spec.split('|'); addRow(page, name, names.split(','), status, source.forms[i]); });
}
for (const [name, page, names, index, fields] of [
    ['Customer', 'Sales/Customers', 'customers.store,customers.update', 0, 'company_name,tax_id,customer_type,status,owner_id,phone,email,line_id,website,address,source,note'],
    ['Contact', 'Sales/Customers', 'contacts.store,contacts.update', 1, 'name,position,phone,email,line_id,is_primary,note,customer_id'],
    ['Deal', 'Sales/Deals', 'deals.store,deals.update', 0, 'title,customer_id,contact_id,stage,value_amount,currency,probability,expected_close_date,owner_id,source,lost_reason,note'],
    ['Activity', 'Sales/Deals', 'activities.store,activities.update', 1, 'entity_type,entity_id,activity_type,subject,body,activity_at,follow_up_at,completed_at,owner_id']
]) {
    const source = ui.find(x => x.file === `backend/resources/js/Pages/${page}.tsx`);
    addRow(page, name, names.split(','), 'migrated', { ...source.useForms[index], surface: 'shared CreateEditDialog' }, fields.split(','));
}
// Upload control uses a button and useForm without a JSX <form>.
const voucher = ui.find(x => x.file.endsWith('/Finance/CommercialDocuments.tsx'));
addRow('Finance/CommercialDocuments', 'Voucher attachment', ['vouchers.attachment.store'], 'pending', { ...voucher.useForms[1], surface: 'inline upload button' }, ['attachment']);
// Every excluded JSX form still receives its own inventory row.
for (const source of ui.filter(s => s.file.startsWith('backend/resources/js/Pages/'))) {
    const page = source.file.replace('backend/resources/js/Pages/', '').replace(/\.tsx$/, '');
    if (manifest[page] || page.startsWith('Sales/')) continue;
    for (const form of source.forms) {
        if (!/^(Auth\/|Profile\/Partials\/(DeleteUserForm|UpdatePasswordForm)$|Dashboard$|Finance\/TaxReports$)/.test(page)) throw Error(`Unclassified form: ${page}:${form.line}`);
        const authSubmit = { 'Auth/AcceptInvite': ['invites.accept.update'], 'Auth/ConfirmPassword': ['password.confirm'], 'Auth/ForgotPassword': ['password.email'], 'Auth/Login': ['login'], 'Auth/Register': ['register'], 'Auth/ResetPassword': ['password.store'], 'Auth/TwoFactorChallenge': ['two-factor.challenge.verify'], 'Auth/TwoFactorSetup': ['two-factor.setup.confirm'], 'Auth/VerifyEmail': ['verification.send'] };
        const names = authSubmit[page] || source.literals.filter(n => routes.some(r => r.name === n));
        addRow(page, page.split('/').at(-1), names, 'exception', form, page === 'Dashboard' ? ['period', 'month', 'year', 'from', 'to'] : page === 'Auth/ResetPassword' ? ['token'] : []);
        rows.at(-1).exceptionReason = /Dashboard|TaxReports/.test(page) ? 'search/filter/report ไม่ใช่ Create/Edit' : 'authentication/password/verification/deletion security flow';
    }
}
const covered = new Set(rows.flatMap(r => r.actions.map(a => a.name)));
const backendOnly = new Set(['accounting.chart-of-accounts.update', 'bank-transfers.store', 'billing-notes.store', 'credit-debit-notes.store', 'hr.leave-balances.store', 'portal-users.store', 'purchase-orders.update', 'purchase-requests.store', 'quotations.update', 'users.role.update', 'users.structure.update', 'vouchers.store']);
const actionLedger = routes.filter(r => /POST|PUT|PATCH|DELETE/.test(r.method)).map(r => {
    const consumers = ui.filter(s => r.name && s.literals.includes(r.name)).map(s => s.file);
    const entry = covered.has(r.name) ? 'form inventory' : backendOnly.has(r.name) ? 'backend-only; ไม่มี UI editor ปัจจุบัน' : 'exception: command/security/integration';
    return { ...r, entry, consumers, contract: contract(r) };
});
const pages = ui.filter(s => s.file.startsWith('backend/resources/js/Pages/')).map(s => ({ file: s.file, jsxForms: s.forms.length, rows: rows.filter(r => r.uiSource.startsWith(`${s.file}:`)).map(r => r.id), payloadInitializers: s.useForms, routeReferences: s.literals.filter(n => routes.some(r => r.name === n)), classification: s.forms.length || s.useForms.length ? 'form/shared-dialog participant (see rows)' : /Dialog/.test(s.file) ? 'Sales shared dialog body/fields' : 'read-only/report/filter/navigation/wrapper; no standalone CRUD form' }));
const missingNames = unique(ui.flatMap(s => [...s.source.matchAll(/route\(\s*['"]([^'"]+)/g)].map(m => m[1])).filter(n => !routes.some(r => r.name === n) && !n.includes('*')));
const counts = Object.fromEntries(['migrated', 'pending', 'gap', 'exception'].map(status => [status, rows.filter(r => r.status === status).length]));
const report = { auditedAt: '2026-09-11', scope: 'Phase 23.1.1 inventory only; implementation and runtime acceptance remain separate', counts, totals: { uiSourceFiles: ui.length, pages: pages.length, registeredRoutes: routes.length, writeRoutes: actionLedger.length, inventoryRows: rows.length, backendOnlyRoutes: actionLedger.filter(r => backendOnly.has(r.name)).length }, missingNames, forms: rows, pages, actionLedger,
    sharedAndNavigation: ui.filter(s => !s.file.includes('/Pages/') && (s.forms.length || s.literals.some(n => routes.some(r => r.name === n)))).map(s => ({ file: s.file, forms: s.forms.length, routeReferences: s.literals.filter(n => routes.some(r => r.name === n)) })) };
fs.writeFileSync('docs/PHASE_23_1_FORM_INVENTORY.json', JSON.stringify(report, null, 2) + '\n');
const esc = x => String(x ?? '').replaceAll('|', '\\|').replaceAll('\n', ' ');
const list = a => a.length ? a.join(', ') : '—';
const label = { migrated: 'เปลี่ยนแล้ว · รอ browser', pending: 'ต้องเปลี่ยน', gap: 'ต้องแก้ integration + เปลี่ยน', exception: 'ข้อยกเว้น' };
let md = `# Phase 23.1.1 — Form inventory\n\nตรวจจาก source วันที่ 2026-09-11; **inventory เสร็จ ไม่ใช่ rollout ทั้งระบบเสร็จ**.\n\n${rows.length} form/action surfaces: เปลี่ยนแล้ว ${counts.migrated}, ต้องเปลี่ยน ${counts.pending}, มี integration gap ${counts.gap}, ข้อยกเว้น ${counts.exception}. ตรวจ ${pages.length} Pages, ${ui.length} JS/TS source files, ${routes.length} registered routes (${actionLedger.length} write routes).\n\n## วิธีอ่านและขอบเขต\n\n- หนึ่งแถวต่อฟอร์มที่ render จริง; Create/Edit ที่ใช้ฟอร์มร่วมกันนับหนึ่งแถว ส่วน Organization Structure ที่แยกฟอร์มนับแยก.\n- Fields = backend validation contract รวม UI field ที่ตรวจพบ; backend รองรับ field มากกว่า control ที่แสดงได้. UI field และ source เต็มแยกไว้ใน JSON เพื่อไม่เข้าใจว่า field ทุกตัวแสดงในหน้าแล้ว.\n- Reference/line items/file แยกตาม field contract; polymorphic refs, nested arrays และค่าเดิมต้องตรวจรักษาระหว่าง rollout.\n- Middleware ใช้ผล php artisan route:list --json จริง; backend-only route ไม่ใช่ฟอร์มที่ต้องสร้างใหม่อัตโนมัติ.\n- Source audit ไม่ใช่ runtime/browser test. Sales อ้างผลทดสอบรอบก่อน; browser regression ของ dialog ทั้ง 4 ยังค้าง.\n- Full evidence, exact route middleware, controller helper links และ UI source: [JSON inventory](PHASE_23_1_FORM_INVENTORY.json).\n- สร้าง snapshot ซ้ำ: node docs/build-phase231-inventory.cjs (จาก repo root); manifest จะ fail หากจำนวน JSX forms เปลี่ยนและยังไม่จัดประเภท.\n\n## ช่องว่างที่พบ\n\n| ID | จุดที่ต้องแก้ก่อน rollout | สถานะ |\n| --- | --- | --- |\n| G01 | Organization.tsx เรียก settings.organization.attendance-privacy.update แต่ไม่มี registered route; controller updateAttendancePrivacy มีอยู่ | บันทึกแล้ว ยังไม่แก้โค้ด |\n| G02 | Invoices.tsx เปิด Edit เมื่อไม่ void และ paid_amount <= 0 แต่ InvoiceController::update รับเฉพาะ draft; ต้องทำเงื่อนไขปุ่มให้ตรง backend | บันทึกแล้ว ยังไม่แก้โค้ด |\n| G03 | Quotation/PO/COA มี update route แต่ไม่มี UI Edit; CN/DN, billing note, PR, voucher มี store route แต่ไม่มี create editor | documented backend-only; ไม่สร้างปุ่มใหม่ในงานย้ายฟอร์ม |\n\n## ตารางรายฟอร์ม\n\n| ID | Module / form | สถานะ | Entry point / route action | เดิม → เป้าหมาย |\n| --- | --- | --- | --- | --- |\n`;
for (const r of rows) md += `| ${r.id} | ${r.page}: ${r.name} | ${label[r.status]} | ${r.uiSource}; ${r.actions.map(a => `${a.method} ${a.uri} (${a.name})`).join('; ')} | ${r.oldUI} → ${r.targetUI} |\n`;
md += '\n## Field / reference / state / permission contracts\n\n';
for (const r of rows) {
    md += `### ${r.id} ${r.name}\n\n| Contract | รายละเอียด |\n| --- | --- |\n`;
    for (const [k, v] of Object.entries({ Page: r.page, Fields: list(r.fields), 'UI fields (static)': list(r.uiFields), References: list(r.references), 'Route model bindings (context)': list(r.routeBindings), 'Line items / nested payload': list(r.lineItems), Attachment: list(r.attachments), 'Permission / middleware': r.actions.map(a => `${a.name}: ${list(a.middleware)}`).join('; '), 'Business state': r.businessState, 'Current UI': r.currentUI, 'New UI': r.targetUI, Test: r.testResult, Exception: r.exceptionReason || '—', Evidence: list(r.backendSources) })) md += `| ${k} | ${esc(v)} |\n`;
}
md += '\n## Backend-only routes: ไม่มี editor ให้ย้ายใน UI ปัจจุบัน\n\nไม่ตีความว่า backend ใช้ไม่ได้; เป็นขอบเขตที่ต้องอนุมัติเป็น feature เพิ่มก่อนสร้าง UI ใหม่. ทุก action มี field/guard contract ใน JSON.\n\n| Route | Controller |\n| --- | --- |\n';
for (const r of actionLedger.filter(r => backendOnly.has(r.name))) md += `| ${r.method} /${r.uri} (${r.name}) | ${r.action} |\n`;
md += '\n## Write-route coverage / non-form actions\n\nคำสั่ง approve/post/pay/reverse/delete/disable/enable/complete/accept/test, state toggle, security และ webhook คง flow/confirmation เดิม. Upload subforms ที่เป็นงานบันทึก metadata แยกไว้ใน inventory.\n\n| Route | Classification | UI consumers |\n| --- | --- | --- |\n';
for (const r of actionLedger) md += `| ${r.method} /${r.uri} (${r.name || 'unnamed'}) | ${r.entry} | ${list(r.consumers)} |\n`;
md += '\n## Page และ shared entry-point coverage\n\n| Source | Forms | Inventory IDs | Classification |\n| --- | --- | --- | --- |\n';
for (const p of pages) md += `| ${p.file} | ${p.jsxForms} | ${list(p.rows)} | ${p.classification} |\n`;
md += '\nDashboard/Executive/Finance/Delivery ใช้ DashboardController และ Dashboard.tsx: filter/deep links คง query/return context; Sales/Dashboard เป็น summary ไม่มี CRUD entry. Layout nav ชี้ index ของโมดูล; ไม่สร้าง editor ซ้ำ. Deals → invoices.index เก็บ customer_id/deal_id, Deals → projects เป็น command. Portal แยก vendor bill form ออกจาก accept/logout/download.\n\n';
for (const s of report.sharedAndNavigation) md += `- ${s.file}: ${s.forms} form; routes ${list(s.routeReferences)}\n`;
md += '\n## Acceptance และ rollout ต่อไป\n\nใช้ลำดับ 0–7 และ acceptance ใน [rollout plan](PHASE_23_1_DIALOG_ROLLOUT.md). แต่ละ pending/gap row ต้องย้ายฟอร์ม + ตรวจ payload/reference/permission/state/draft/attachment + browser desktop/mobile ก่อนเปลี่ยนเป็น accepted; migrated ยังไม่เท่ากับ accepted. backend-only และ command exceptions คงข้อจำกัดเดิม.\n';
fs.writeFileSync('docs/PHASE_23_1_FORM_INVENTORY.md', md);
let overview = `# Phase 23.1.1 — ต้องเปลี่ยนอะไร / เปลี่ยนแล้วอะไร\n\n**Scope & Inventory เสร็จแล้ว**. รอบนี้จัดทำรายการและหลักฐาน ไม่ได้เปลี่ยนหน้าจอเพิ่ม.\n\n| สถานะ | จำนวน | ความหมาย |\n| --- | ---: | --- |\n| เปลี่ยนแล้ว | ${counts.migrated} | Sales shared dialog; ยังรอ browser regression |\n| ต้องเปลี่ยน | ${counts.pending} | ฟอร์มเดิมยังไม่ได้ย้ายเข้า shared dialog |\n| ต้องแก้ integration แล้วเปลี่ยน | ${counts.gap} | Attendance Privacy เรียก route ที่ยังไม่ได้ register |\n| ข้อยกเว้น | ${counts.exception} | Security, filter, import และ command forms คง workflow เฉพาะ |\n\nนับตามฟอร์มที่ render จริง: Create/Edit ที่ใช้ฟอร์มร่วมกันนับหนึ่ง ส่วนที่แยกฟอร์มนับแยก. นอกเหนือจาก ${rows.length} แถวนี้ มี ${report.totals.backendOnlyRoutes} write routes ที่ไม่มี UI editor — ไม่สร้างฟอร์มใหม่โดยอัตโนมัติ.\n\n## เปลี่ยนแล้ว\n\n| ฟอร์ม | ไฟล์ที่เปลี่ยน | ผลที่เห็น |\n| --- | --- | --- |\n| Customer | Sales/Customers.tsx, CustomerDialog.tsx | dialog สร้าง/แก้ไข, owner search, validation, draft guard |\n| Contact | Sales/Customers.tsx, ContactDialog.tsx | dialog บนลูกค้าเดิม, primary contact, draft guard |\n| Deal | Sales/Deals.tsx, DealDialog.tsx | dialog, customer/contact/owner search, stage rules |\n| Activity | Sales/Deals.tsx, ActivityDialog.tsx | dialog สร้าง/แก้ไข, follow-up, UTC dates, owner search |\n\nทั้ง 4 ใช้ CreateEditDialog.tsx + DiscardChangesDialog.tsx + useDiscardGuard.ts + historyGuard.ts; มี normal/expanded/minimized. Quick-create reference และ browser acceptance ยังไม่เสร็จ. ผลทดสอบรอบก่อน: Sales 12 tests / 196 assertions, history 9 tests, TypeScript/ESLint/build ผ่าน.\n\n## ยังต้องเปลี่ยน — แยกตามหน้า\n\n| หน้า | ฟอร์มที่ต้องเปลี่ยน |\n| --- | --- |\n`;
for (const page of unique(rows.filter(r => ['pending', 'gap'].includes(r.status)).map(r => r.page))) overview += `| ${page}.tsx | ${rows.filter(r => r.page === page && ['pending', 'gap'].includes(r.status)).map(r => `${r.name} (${r.id})`).join(', ')} |\n`;
overview += '\nแต่ละฟอร์มย้ายตัวครอบเดิมเป็น shared dialog, เพิ่มปุ่มเปิดตาม action ที่มีอยู่, คง payload/line items/ไฟล์/สิทธิ์/สถานะ, เพิ่ม draft/error/focus handling และตรวจ desktop/mobile. ยังไม่เปลี่ยน backend business rules ตามการออกแบบ UI.\n\n## จุดที่ต้องแก้ให้ตรง backend\n\n| จุด | ปัจจุบัน | ต้องแก้ |\n| --- | --- | --- |\n| G01 — Settings/Organization.tsx:515 | เรียก settings.organization.attendance-privacy.update แต่ route ไม่มี; controller มี method แล้ว | เชื่อม route พร้อม middleware ที่เหมาะสมและทดสอบ ก่อนย้าย dialog |\n| G02 — Finance/Invoices.tsx:468 | Edit แสดงเมื่อไม่ void และ paid_amount <= 0 | จำกัดตาม draft + permission ให้ตรง InvoiceController::update |\n| G03 — Quotation/PO/COA และเอกสารการค้า | backend มี action แต่ UI ยังไม่มี editor บางรายการ | บันทึก backend-only; แยกเป็น feature เพิ่ม ไม่ถือว่าย้าย dialog แล้ว |\n\nG01/G02 **ยังไม่ได้แก้โค้ดในรอบ inventory นี้**.\n\n## หลักฐานและรายการครบ\n\n- [91 form contracts + route/page coverage](PHASE_23_1_FORM_INVENTORY.md): fields, references, line items, attachment, permission, business state, เดิม/ใหม่, ผลตรวจ และข้อยกเว้นรายฟอร์ม\n- [Machine-readable source evidence](PHASE_23_1_FORM_INVENTORY.json): UI snippets, payload initializers, exact middleware และ guard evidence\n- [Rollout order / acceptance](PHASE_23_1_DIALOG_ROLLOUT.md)\n- [Checklist กลาง](../checklist.md)\n\nตรวจ 60 Pages, 92 JS/TS source files และ 252 registered routes (173 write routes), รวม shared components, dynamic route names, dashboard links, Portal และ security flows. Inventory ผ่าน coverage checks; runtime/browser ของโมดูลที่ยังไม่ย้ายยังไม่ได้ทดสอบในรอบนี้.\n';
fs.writeFileSync('docs/PHASE_23_1_CHANGE_MAP.md', overview);
console.log(JSON.stringify({ counts, totals: report.totals, missingNames }, null, 2));
