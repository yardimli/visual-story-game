<?php
require dirname(__DIR__).'/vendor/autoload.php';
$app=require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
try {
    $tables=Illuminate\Support\Facades\Schema::getTableListing();
    echo json_encode(['connected'=>true,'tables'=>$tables],JSON_PRETTY_PRINT)."\n";
} catch (Throwable $e) { echo "Database connection failed; credentials were not printed. Verify database availability.\n"; exit(1); }
