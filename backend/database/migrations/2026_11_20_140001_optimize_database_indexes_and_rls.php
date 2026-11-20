<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations to index all tenant_id columns and enforce PostgreSQL RLS.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        // Get all tables having tenant_id
        $tables = DB::select("
            SELECT DISTINCT c.table_name
            FROM information_schema.columns c
            JOIN information_schema.tables t ON t.table_name = c.table_name AND t.table_schema = 'public'
            WHERE c.column_name = 'tenant_id'
              AND c.table_schema = 'public'
              AND t.table_type = 'BASE TABLE'
            ORDER BY c.table_name
        ");

        foreach ($tables as $t) {
            $tableName = $t->table_name;

            // 1. Check existing indexes for tenant_id
            $indexes = DB::select("
                SELECT indexname, indexdef 
                FROM pg_indexes 
                WHERE tablename = ? AND schemaname = 'public'
            ", [$tableName]);

            $hasTenantIndex = false;
            foreach ($indexes as $idx) {
                if (preg_match('/\(tenant_id[,\)]/', $idx->indexdef)) {
                    $hasTenantIndex = true;
                    break;
                }
            }

            if (!$hasTenantIndex) {
                $hasCreatedAt = Schema::hasColumn($tableName, 'created_at');
                $indexName = substr("idx_{$tableName}_tenant_id", 0, 60);

                if ($hasCreatedAt) {
                    DB::statement("CREATE INDEX IF NOT EXISTS {$indexName} ON {$tableName} (tenant_id, created_at DESC)");
                } else {
                    DB::statement("CREATE INDEX IF NOT EXISTS {$indexName} ON {$tableName} (tenant_id)");
                }
            }

            // 2. Enable Row Level Security (RLS) as secondary defense-in-depth
            DB::statement("ALTER TABLE {$tableName} ENABLE ROW LEVEL SECURITY;");

            // Attach standard tenant isolation policy if not already attached
            DB::statement("
                DO $$
                BEGIN
                    IF NOT EXISTS (
                        SELECT 1 FROM pg_policies 
                        WHERE tablename = '{$tableName}' AND policyname = 'tenant_isolation_policy'
                    ) THEN
                        CREATE POLICY tenant_isolation_policy ON {$tableName}
                            FOR ALL
                            USING (
                                tenant_id = current_tenant_id()
                                OR current_setting('app.is_super_admin', true) = 'true'
                                OR current_tenant_id() IS NULL
                            )
                            WITH CHECK (
                                tenant_id = current_tenant_id()
                                OR current_setting('app.is_super_admin', true) = 'true'
                                OR current_tenant_id() IS NULL
                            );
                    END IF;
                END $$;
            ");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        $preserveRlsTables = [
            'students',
            'student_enrollments',
            'guardians',
            'fee_invoices',
            'fee_payments',
            'audit_logs',
            'file_uploads',
        ];

        $tables = DB::select("
            SELECT DISTINCT table_name 
            FROM information_schema.columns 
            WHERE column_name = 'tenant_id' AND table_schema = 'public'
        ");

        foreach ($tables as $t) {
            $tableName = $t->table_name;
            $indexName = substr("idx_{$tableName}_tenant_id", 0, 60);

            DB::statement("DROP INDEX IF EXISTS {$indexName}");

            if (!in_array($tableName, $preserveRlsTables, true)) {
                DB::statement("DROP POLICY IF EXISTS tenant_isolation_policy ON {$tableName}");
                DB::statement("ALTER TABLE {$tableName} DISABLE ROW LEVEL SECURITY");
            }
        }
    }
};

