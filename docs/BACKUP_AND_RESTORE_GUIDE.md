# Database Backup & Restoration Guide
**School ERP & Multi-Tenant SaaS Platform**

This document establishes the backup strategy, disaster recovery runbooks, and validation procedures for the **Supabase PostgreSQL** production database.

---

## 1. Backup Strategy Overview

| Tier | Backup Type | Frequency | Retention | RPO / RTO Target |
| :--- | :--- | :---: | :---: | :--- |
| **Tier 1** | Supabase Point-in-Time Recovery (PITR) | Continuous (WAL) | 7 to 30 Days | **RPO:** < 5 mins / **RTO:** < 30 mins |
| **Tier 2** | Supabase Daily Automated Snapshots | Daily (02:00 UTC) | 30 Days | **RPO:** 24 hours / **RTO:** < 15 mins |
| **Tier 3** | Offsite Encrypted Logical Dump (`pg_dump`) | Daily (03:00 UTC) | 90 Days | **RPO:** 24 hours / **RTO:** < 1 hour |

---

## 2. Automated Offsite Backup Script (`pg_dump`)

Deploy this script on a secure maintenance server or GitHub Action running on a nightly cron:

```bash
#!/usr/bin/env bash
set -eo pipefail

TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
BACKUP_DIR="/var/backups/school-erp"
FILENAME="school_erp_backup_${TIMESTAMP}.dump"
ENCRYPTED_FILE="${FILENAME}.gpg"

mkdir -p "${BACKUP_DIR}"

echo "[$(date)] Starting logical database dump from Supabase..."

# 1. Execute logical dump in custom binary format (supports parallel restore)
PGPASSWORD="${DB_PASSWORD}" pg_dump \
  -h "${DB_HOST}" \
  -p 5432 \
  -U "${DB_USERNAME}" \
  -d postgres \
  -F c \
  -b \
  -v \
  -f "${BACKUP_DIR}/${FILENAME}"

echo "[$(date)] Dump complete. Size: $(du -h "${BACKUP_DIR}/${FILENAME}" | cut -f1)"

# 2. Encrypt dump using symmetric AES-256 GPG key
gpg --batch --yes --passphrase "${BACKUP_ENCRYPTION_PASSPHRASE}" \
  -c "${BACKUP_DIR}/${FILENAME}"

# 3. Upload encrypted backup to off-site cloud storage (AWS S3 / Cloudflare R2)
aws s3 cp "${BACKUP_DIR}/${ENCRYPTED_FILE}" \
  "s3://school-erp-cold-backups/daily/${ENCRYPTED_FILE}" \
  --sse AES256

echo "[$(date)] Encrypted backup uploaded to S3 successfully."

# 4. Clean up local artifacts older than 7 days
rm -f "${BACKUP_DIR}/${FILENAME}"
find "${BACKUP_DIR}" -name "*.dump.gpg" -mtime +7 -delete
```

---

## 3. Database Restoration Procedure

### Scenario A: Restoring to Supabase via PITR (Dashboard)
1. Navigate to **Supabase Dashboard** -> **Project Settings** -> **Backups**.
2. Click **Restore from Point in Time**.
3. Select the timestamp prior to the incident (e.g. 5 minutes before data corruption).
4. Confirm restoration. Supabase will provision the restored state with zero data loss within the window.

### Scenario B: Restoring from Logical Dump (`pg_restore`)
To restore to a fresh PostgreSQL instance or local disaster recovery environment:

```bash
# 1. Decrypt the backup
gpg --batch --passphrase "${BACKUP_ENCRYPTION_PASSPHRASE}" \
  -d school_erp_backup_20261120_030000.dump.gpg > restored_backup.dump

# 2. Terminate existing connections to destination database
psql -h "${RESTORE_DB_HOST}" -U "${RESTORE_DB_USER}" -d postgres -c "
SELECT pg_terminate_backend(pid) FROM pg_stat_activity 
WHERE datname = 'school_erp_saas' AND pid <> pg_backend_pid();
"

# 3. Drop and recreate database
psql -h "${RESTORE_DB_HOST}" -U "${RESTORE_DB_USER}" -d postgres -c "
DROP DATABASE IF EXISTS school_erp_saas;
CREATE DATABASE school_erp_saas;
"

# 4. Restore schema and data using pg_restore
pg_restore -h "${RESTORE_DB_HOST}" -p 5432 -U "${RESTORE_DB_USER}" \
  -d school_erp_saas \
  -v \
  --no-owner \
  --role=postgres \
  restored_backup.dump

# 5. Run post-restoration verification
php artisan test --filter=TenantIsolationTest
```

---

## 4. Backup Restoration Validation Test Runbook

Perform this drill quarterly to guarantee that backups are healthy and restorable:

1. Restore the latest backup into a temporary staging database: `school_erp_drill`.
2. Connect Laravel backend pointing to `school_erp_drill`.
3. Verify record counts:
   - `SELECT count(*) FROM tenants;`
   - `SELECT count(*) FROM students;`
   - `SELECT count(*) FROM fee_invoices;`
4. Confirm Row-Level Security remains active:
   ```sql
   SELECT tablename, rowsecurity FROM pg_tables WHERE schemaname = 'public' AND rowsecurity = false;
   -- Must return 0 tenant tables
   ```
5. Log validation success in compliance audit logs.

