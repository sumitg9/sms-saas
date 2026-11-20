<?php

require 'C:/Users/sumit/Downloads/Projects/SMS-SAAS/backend/vendor/autoload.php';
$app = require_once 'C:/Users/sumit/Downloads/Projects/SMS-SAAS/backend/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$rows = DB::select("SELECT tablename, rowsecurity FROM pg_tables WHERE schemaname = 'public' AND rowsecurity = true");
echo "Tables with RLS enabled: " . count($rows) . "\n";
foreach ($rows as $r) {
    echo " - " . $r->tablename . "\n";
}

