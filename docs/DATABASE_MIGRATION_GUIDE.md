# Database Migration Guide (Supabase PostgreSQL)
**School ERP & Multi-Tenant SaaS Platform**

This guide provides procedures for executing zero-downtime database migrations against the production **Supabase PostgreSQL** project.

---

## 1. Supabase Connection Strategy for Migrations

Supabase provides two connection endpoints with distinct behaviors:

| Endpoint | Port | Mode | When to Use |
| :--- | :---: | :--- | :--- |
| **Direct Connection** | `5432` | Session | **MIGRATIONS & DDL:** Use this port for `php artisan migrate`, adding indexes, and running schema scripts. Supports transactional DDL and locks. |
| **Connection Pooler** | `6543` | Transaction | **RUNTIME TRAFFIC:** Use this port for web workers, API requests, and fast queries. Does not support long-running schema locks. |

> [!IMPORTANT]
> When executing migrations in CI/CD or deployment pipelines, temporarily override `DB_PORT=5432` or specify the direct database URL to ensure schema locks and DDL statements execute without PgBouncer transaction termination.

---

## 2. Standard Migration Execution Procedure

### Step 1: Pre-Migration Backup Snapshot
Before running any schema migrations on production:
1. Log into your Supabase Dashboard.
2. Navigate to **Database** -> **Backups**.
3. Confirm an active automated backup exists within the last 24 hours, or trigger a manual snapshot:
   ```bash
   pg_dump -h db.YOUR_PROJECT_REF.supabase.co -U postgres -d postgres -F c -b -v -f pre_migration_backup.dump
   ```

### Step 2: Run Pending Migrations
Execute the migrations with the force flag from the backend directory:
```bash
cd /var/www/school-erp/backend
php artisan migrate --force
```

### Step 3: Post-Migration Schema Verification
Run the verification command to confirm all tables retain Row Level Security and composite tenant indexes:
```bash
php artisan tinker --execute="
\$unindexed = [];
\$tables = DB::select(\"SELECT table_name FROM information_schema.columns WHERE column_name = 'tenant_id' AND table_schema = 'public'\");
foreach (\$tables as \$t) {
    \$idx = DB::select(\"SELECT indexname FROM pg_indexes WHERE tablename = ? AND indexdef LIKE '%tenant_id%'\", [\$t->table_name]);
    if (empty(\$idx)) \$unindexed[] = \$t->table_name;
}
echo 'Unindexed tenant tables: ' . count(\$unindexed) . PHP_EOL;
"
# Expected output: Unindexed tenant tables: 0
```

---

## 3. Zero-Downtime Migration Principles

To prevent table locks and request timeouts when altering tables with millions of records:

### 1. Adding Columns
- Always add columns as `nullable` or with a constant default:
  ```php
  $table->string('emergency_contact')->nullable();
  ```
- Do not add `NOT NULL` columns without a default in a single step on populated tables.

### 2. Creating Indexes
- On tables with > 500,000 rows, use PostgreSQL's `CONCURRENTLY` feature:
  ```php
  DB::statement('CREATE INDEX CONCURRENTLY idx_student_marks_exam_id ON student_marks (exam_id);');
  ```
- Note: `CONCURRENTLY` cannot run inside a migration transaction (`$this->withinTransaction = false`).

### 3. Renaming Columns (Expand-and-Contract Pattern)
Never rename a column in-place on a live application. Follow the 3-phase rollout:
1. **Phase 1 (Expand):** Add the new column; configure the application to write to both old and new columns.
2. **Phase 2 (Backfill):** Run a background job to copy historical data from old column to new column.
3. **Phase 3 (Contract):** Update application to read exclusively from the new column, then drop the old column in a subsequent release.

---

## 4. Emergency Migration Rollback Procedure

If a migration fails or causes unexpected query degradation:

```bash
# Roll back only the last migration batch
php artisan migrate:rollback --step=1 --force

# Clear cached schema and route information
php artisan optimize:clear
```
> [!CAUTION]
> Avoid rolling back migrations that have dropped columns, as dropped column data cannot be recovered without restoring from a physical database backup.

