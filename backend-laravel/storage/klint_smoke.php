<?php
require dirname(__DIR__).'/vendor/autoload.php';
$app=require dirname(__DIR__).'/bootstrap/app.php';
$kernel=$app->make(Illuminate\Contracts\Http\Kernel::class);
$app->instance('request', Illuminate\Http\Request::create('/')); $kernel->bootstrap();
config(['database.connections.audit_klint'=>array_replace(config('database.connections.mysql'), ['url'=>null,'host'=>'100.73.14.100','port'=>3306,'database'=>'klint','username'=>'groupmate','password'=>getenv('RESQ_AUDIT_PASSWORD')]),'database.default'=>'audit_klint','realtime.durable'=>false,'broadcasting.default'=>'null','app.debug'=>false,'app.schema_capability_cache_enabled'=>false]);
Illuminate\Support\Facades\DB::setDefaultConnection('audit_klint');
$user=App\Models\User::query()->whereHas('role',fn($q)=>$q->whereIn('role_key',['hq','admin','operations_admin']))->whereNull('deleted_at')->first();
if(!$user) {echo "No applicable HQ user found; skipping authenticated smoke checks.\n";exit(1);}
$results=[];
foreach(['/profile','/resource-requests','/dashboard/summary','/households/directory','/households','/dispatches','/rescue-teams','/field-reports','/map/overview','/situation-reports','/weather','/notifications','/archive/disaster-events','/archive/household-status-logs','/archive/dispatch-logs','/archive/radio-communication-logs','/archive/resource-requests','/archive/situation-reports','/archive/saved-groups'] as $path){
 Illuminate\Support\Facades\Auth::guard('sanctum')->setUser($user);
 $request=Illuminate\Http\Request::create('/api/v1'.$path,'GET',[],[],[],['HTTP_ACCEPT'=>'application/json']);
 $response=$kernel->handle($request);
 $body=json_decode($response->getContent(),true);
 $row=['path'=>$path,'status'=>$response->getStatusCode()];
 if($response->getStatusCode()>=400) $row['message']=$body['message']??'Non-JSON response';
 $results[]=$row; echo json_encode($row).PHP_EOL;
 $kernel->terminate($request,$response);
}
file_put_contents(__DIR__.'/klint-api-smoke.json',json_encode($results,JSON_PRETTY_PRINT));

