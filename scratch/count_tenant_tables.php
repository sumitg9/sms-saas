<?php

require 'C:/Users/sumit/Downloads/Projects/SMS-SAAS/backend/vendor/autoload.php';
$app = require_once 'C:/Users/sumit/Downloads/Projects/SMS-SAAS/backend/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$tables = DB::select("SELECT table_name FROM information_schema.columns WHERE column_name = 'tenant_id' AND table_schema = 'public' ORDER BY table_name");
echo "Found " . count($tables) . " tables with tenant_id:\n";

$unindexed = [];
foreach ($tables as $t) {
    $tableName = $t->table_name;
    // Check indexes
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
        $unindexed[] = $tableName;
    }
}

echo "Tables without tenant_id index: " . count($unindexed) . "\n";
print_r($unindexed);

