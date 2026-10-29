<?php
require dirname(__DIR__).'/vendor/autoload.php';
$app=require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['database.connections.audit_klint'=>array_replace(config('database.connections.mysql'),['url'=>null,'host'=>'100.73.14.100','database'=>'klint','username'=>'groupmate','password'=>getenv('RESQ_AUDIT_PASSWORD')]),'database.default'=>'audit_klint']);
Illuminate\Support\Facades\DB::setDefaultConnection('audit_klint');
$status=Illuminate\Support\Facades\Artisan::call('resq:database-audit',['--database'=>'audit_klint']);
file_put_contents(__DIR__.'/klint-eloquent-audit.json',Illuminate\Support\Facades\Artisan::output());
echo 'Audit exit status: '.$status.PHP_EOL;
if($status) echo Illuminate\Support\Facades\Artisan::output();
