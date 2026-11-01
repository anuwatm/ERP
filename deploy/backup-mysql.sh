#!/usr/bin/env bash
set -euo pipefail
umask 077
# No passwords in command arguments, environment dumps or logs.
credentials=/etc/company-erp/backup.cnf
backup_root=/var/backups/company-erp
test -f "$credentials"
test "$(stat -c '%a' "$credentials")" = 600
stamp=$(date -u +%Y%m%dT%H%M%SZ)
target="$backup_root/$stamp"
mkdir -p "$target"
mysqldump --defaults-extra-file="$credentials" --single-transaction --routines --triggers --events --hex-blob erp | gzip > "$target/erp.sql.gz.partial"
gzip -t "$target/erp.sql.gz.partial"
mv "$target/erp.sql.gz.partial" "$target/erp.sql.gz"
cd "$target"
sha256sum erp.sql.gz > SHA256SUMS
# No automatic pruning. Configure encrypted off-site replication separately.
printf 'ERP database backup verified: %s\n' "$stamp"
