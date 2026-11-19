<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("
            DO $$
            DECLARE
                r RECORD;
            BEGIN
                FOR r IN 
                    SELECT table_name 
                    FROM information_schema.columns 
                    WHERE table_schema = 'public' 
                      AND column_name = 'id' 
                      AND data_type = 'uuid' 
                      AND column_default IS NULL
                LOOP
                    EXECUTE format('ALTER TABLE %I ALTER COLUMN id SET DEFAULT gen_random_uuid()', r.table_name);
                END LOOP;
            END $$;
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("
            DO $$
            DECLARE
                r RECORD;
            BEGIN
                FOR r IN 
                    SELECT table_name 
                    FROM information_schema.columns 
                    WHERE table_schema = 'public' 
                      AND column_name = 'id' 
                      AND data_type = 'uuid' 
                      AND column_default LIKE '%gen_random_uuid%'
                LOOP
                    EXECUTE format('ALTER TABLE %I ALTER COLUMN id DROP DEFAULT', r.table_name);
                END LOOP;
            END $$;
        ");
    }
};

