<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Tables with Row Level Security (RLS) enabled as secondary defense-in-depth.
     */
    protected array $rlsTables = [
        'students',
        'student_enrollments',
        'guardians',
        'fee_invoices',
        'fee_payments',
        'audit_logs',
        'file_uploads',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Only execute on PostgreSQL
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        // 1. Create helper function to read active session tenant context
        DB::statement("
            CREATE OR REPLACE FUNCTION current_tenant_id() RETURNS UUID AS $$
            BEGIN
                RETURN NULLIF(current_setting('app.current_tenant_id', true), '')::UUID;
            EXCEPTION
                WHEN OTHERS THEN RETURN NULL;
            END;
            $$ LANGUAGE plpgsql STABLE;
        ");

        // 2. Enable RLS and attach policy to critical tables
        foreach ($this->rlsTables as $table) {
            DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY;");
            DB::statement("
                DO $$
                BEGIN
                    IF NOT EXISTS (
                        SELECT 1 FROM pg_policies 
                        WHERE tablename = '{$table}' AND policyname = 'tenant_isolation_policy'
                    ) THEN
                        CREATE POLICY tenant_isolation_policy ON {$table}
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

        foreach ($this->rlsTables as $table) {
            DB::statement("DROP POLICY IF EXISTS tenant_isolation_policy ON {$table};");
            DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY;");
        }

        DB::statement("DROP FUNCTION IF EXISTS current_tenant_id();");
    }
};

