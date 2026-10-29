<?php
require dirname(__DIR__).'/vendor/autoload.php';
$app=require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['database.connections.audit_klint'=>array_replace(config('database.connections.mysql'), ['url'=>null, 'host'=>'100.73.14.100','port'=>3306,'database'=>'klint','username'=>'groupmate','password'=>getenv('RESQ_AUDIT_PASSWORD')]), 'database.default'=>'audit_klint']);
Illuminate\Support\Facades\DB::setDefaultConnection('audit_klint');
foreach (['2026_10_29_000001_repair_resq_database_integrity.php','2026_10_29_000002_preserve_resq_orphan_references.php','2026_10_29_000003_remove_resident_plaintext_credentials.php','2026_10_29_000004_reconcile_resq_module_schema.php','2026_10_29_000005_persist_resource_request_urgency_catalog.php','2026_10_29_000006_enforce_resq_module_relationships.php'] as $migration) {
 $status=Illuminate\Support\Facades\Artisan::call('migrate',['--database'=>'audit_klint','--path'=>'database/migrations/'.$migration,'--force'=>true]);
 echo Illuminate\Support\Facades\Artisan::output();
 if ($status) exit($status);
}
$status=Illuminate\Support\Facades\Artisan::call('resq:database-audit',['--database'=>'audit_klint']);
file_put_contents(__DIR__.'/klint-eloquent-audit.json',Illuminate\Support\Facades\Artisan::output());
echo 'Eloquent audit status: '.$status.PHP_EOL;

