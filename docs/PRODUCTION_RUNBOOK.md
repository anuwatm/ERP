# Production deployment / operations runbook — 2026-10-29

Templates and local verification only. Target server, live providers/hardware, production load and owner-led DR are not yet tested. POS remains local-only; do not bypass its production hard-block.

## Release and prerequisites

Use PHP/extension versions from composer.lock and setup requirements; install dependencies from lockfiles, build assets and run checks before creating an immutable release. Point ERP webroot only at backend/public. Protect .env/private storage/keys outside the served root. Use separate ERP/POS DB principals, APP_KEY, sessions and keys. Do not run ERP installer against an existing database.

1. Record release hash, operator, maintenance window and previous release; stop user writes and workers before schema changes that require it.
2. Verify encrypted off-site DB + private-document/archive + APP_KEY/key-material backups and restore evidence; keep POS recovery anchor independent of the DB restore set.
3. Review `php artisan migrate:status`, back up first, then `php artisan migrate --force`. Never use migrate:fresh or automatic destructive rollback against business data.
4. Run `php artisan storage:link` for PUBLIC assets only (logo/receipt paths). DMS, prescriptions, tax artifacts, POS archives and keys must stay private and use authorized download routes.
5. Set `APP_ENV=production`, `APP_DEBUG=false`, HTTPS APP_URL, `SESSION_SECURE_COOKIE=true`, `SESSION_HTTP_ONLY=true`, `SESSION_SAME_SITE=lax`. Scope host/cookie to ERP; use distinct POS cookie. Review proxy trust, session lifetime and TLS termination. Set SESSION_ENCRYPT=true where the chosen shared session backend supports it and verify round-trip before release.
6. Configure async queue, shared cache and actual mail transport. `MAIL_MAILER=log` is not a delivery verification. Keep external integrations disabled until their separate acceptance.
7. Run config/route/view caches, start workers/scheduler, then `php artisan queue:restart` after each release. Re-enable traffic only after smoke and health checks pass.

## Scheduler, workers and monitoring

- [Cron example](../deploy/cron/company-erp.cron), [Supervisor](../deploy/supervisor/company-erp.conf), [systemd](../deploy/systemd/company-erp-worker.service). Choose one worker manager; do not start duplicate unmanaged worker sets.
- Worker timeout60s must be LOWER than queue retry_after90s; verify actual config and all HTTP/job timeouts. Raise both consistently for measured long jobs. Stop grace120s must exceed job timeout. Protect and rotate logs; do not log raw failed payloads.
- Set `OPS_HEARTBEATS_ENABLED=true`, `OPS_MONITORED_QUEUES=default,etax` only after shared cache and async workers exist. Scheduler writes its own heartbeat and queues a heartbeat job per monitored queue. The worker must consume each job for evidence to refresh.
- `php artisan ops:health` emits status/check booleans only and exits1 if evidence is missing/stale, sync queue is used, or dependencies fail. Scheduler threshold180s/worker300s. An idle queue still gets heartbeat jobs. This checks queue consumption, not successful payment/e-Tax delivery.
- External monitoring owner must alert on nonzero health, missed polls, failed jobs, backup failures and disk thresholds; monitor endpoints from outside the application too. Record on-call owner/escalation and delivery test before go-live. `/up` alone does not prove scheduler/worker or DB health.

## Failed jobs / replay

Use `queue:failed` only on a private operator terminal; failed payloads may contain sensitive data. Record job UUID and sanitized exception class. Fix the root cause first; verify source business state/idempotency and original event identity, then `queue:retry <uuid>` for approved jobs. Do not replay all failed jobs, change payloads or create replacement sale/event IDs to bypass guards. Reconcile source/ERP Payment/GL/Stock after retry. POS dependency/recovery-fence cases use the dedicated [POS runbook](PHASE_28_OPERATIONS.md).

## PDF / fonts

Sarabun Regular/Bold and OFL are bundled under backend/resources/fonts with source URLs/SHA256 in manifest.json. Source: [Google Fonts Sarabun](https://github.com/google/fonts/tree/main/ofl/sarabun). Keep OFL.txt when packaging; do not silently substitute proprietary fonts. DomPDF templates embed the local TTF data through documents/pdf-fonts, so Linux does not depend on Windows-installed fonts or remote font fetching. Allow the service user to read resources/fonts and write DomPDF font cache/temp within private storage. Confirm real Thai combining marks and pagination on Invoice, PO and 50-Tawi; a successful HTTP/PDF binary or text extraction alone is insufficient.

## Storage / uploads smoke

Suggested starting limits: PHP upload_max_filesize=10M, post_max_size=12M; Nginx client_max_body_size=12m. Application validation may be lower and remains authoritative. Review each upload type rather than accepting arbitrary MIME/extensions. Directories owned by release/deploy user; service user gets write only to required storage and bootstrap/cache, never chmod777 or webroot write. Verify actual ownership/ACL under the real service account.

In a disposable test organization, upload/download a synthetic logo, receipt and authorized attachment. Verify size/MIME checks, cross-org403/404, rejected file handling, progress/retry, private-file URL denial and no directory listing. Record bytes/hash/latency and screenshot. A local pass does not verify target storage permissions.

## Notifications / release smoke

On isolated data create sent/partially-paid invoices due soon and overdue plus an already-paid invoice. Run invoices:notify-due-soon and invoices:mark-overdue; verify status, dedupe and authorized notification recipient. Run again to prove no duplicate notifications. Use fake/array mail locally; real mail requires mailbox evidence separately. Include invoices with zero balance and cross-org recipients.

After release check HTTPS/cookie flags, login/2FA/logout, authorized dashboard, DB SELECT1, queue consumption, scheduler heartbeat, Thai PDF, upload/download and failed-job counts. Compare routes/build hashes and avoid real payment/tax writes in smoke tests. Record release/role/browser/time/expected/actual. No production smoke has been claimed by these templates.

## Backup, rollback and restore

- Daily database backup at02:15 UTC by [example script](../deploy/backup-mysql.sh), credential file /etc/company-erp/backup.cnf mode0600; never put passwords in shell history/arguments. Replace fixed database name/path for approved installation. Script verifies gzip and SHA256, retains partial files on failure and never prunes automatically.
- Proposed operational retention: daily copies for at least90days, independent encrypted off-site copies and immutability/legal holds. Data owner must approve retention/deletion and key custody before enabling any cleanup. POS accepted-event archive90days is a separate policy, not permission to delete backups or pending/hold evidence.
- DB-only backups are incomplete: capture private documents, POS encrypted archives, original APP_KEY/RSA/cert material and release/config version using separate restricted encrypted custody. Record event high-watermark/checkpoint. Do not restore the independent POS recovery anchor backward with the DB.
- Restore drill: use a NEW isolated DB/storage path/keys access scope, verify SHA256/gzip, restore, check FK/integrity and row/source ledger totals, test archive decryption, then simulate duplicate/reordered events and recovery fence. Never point a drill at operational databases or start provider workers from restored data. Capture operator, backup ID, timestamps, counts and measured RPO/RTO. Owner-led target drill is still a go-live gate.
- Rollback code only when schema is backward-compatible. Keep the forward-compatible schema and source evidence where possible; otherwise restore the coordinated approved backup under stopped writes, assess lost events and fence POS/ERP until reconciled. Never use migrate:rollback blindly or reset numbering/generation/anchor to clear a fence.

## Log review

Review application logs separately from authorized audit records. Search for request bodies, passwords, bearer tokens, 2FA secrets/recovery codes, cookies, bank/health data and exception SQL/provider bodies. Keep sample verification synthetic and reports limited to file/line and finding category; never copy secrets into reports. External API handler currently suppresses raw exception details; this does not certify every log channel. Global production log review and real monitoring-owner sign-off remain required.
