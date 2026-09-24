<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

var_dump(app()->environment());
var_dump(app()->runningUnitTests());
var_dump(config('database.default'));
var_dump(config('database.connections.sqlite.database'));

Schema::dropIfExists('personal_access_tokens');
Schema::dropIfExists('roles');
Schema::dropIfExists('responders');
Schema::dropIfExists('users');

Schema::create('roles', function($t){ $t->increments('role_id'); $t->string('role_key'); $t->string('role_name'); });
Schema::create('users', function($t){ $t->string('user_id')->primary(); $t->string('first_name')->nullable(); $t->string('last_name')->nullable(); $t->string('name')->nullable(); $t->string('username')->nullable(); $t->string('email')->nullable(); $t->string('password')->nullable(); $t->integer('role_id')->nullable(); $t->string('contact_number')->nullable(); $t->boolean('is_active')->nullable(); $t->timestamp('created_at')->nullable(); $t->timestamp('updated_at')->nullable(); });
Schema::create('responders', function($t){ $t->unsignedBigInteger('responder_id')->primary(); $t->string('user_id'); $t->string('responder_code'); $t->string('username'); $t->string('password_hash'); $t->boolean('is_validated')->default(true); $t->string('full_name')->nullable(); $t->string('title')->nullable(); $t->string('contact_number')->nullable(); $t->timestamp('created_at')->nullable(); $t->timestamp('updated_at')->nullable(); });
DB::table('roles')->insert(['role_id'=>5,'role_key'=>'rescuer','role_name'=>'Rescuer']);
DB::table('users')->insert(['user_id'=>'USR-RESCUER-BDRRM-SAR-001','first_name'=>'Miguel','last_name'=>'Reyes','name'=>'Miguel Reyes','username'=>'miguel.reyes','email'=>'bdrrm.sar.001@rescuer.resqperation.local','password'=>Hash::make('password'),'role_id'=>5,'contact_number'=>'09170001001','is_active'=>1,'created_at'=>now(),'updated_at'=>now()]);
DB::table('responders')->insert(['responder_id'=>2024035502,'user_id'=>'USR-RESCUER-BDRRM-SAR-001','responder_code'=>'BDRRM-SAR-001','username'=>'BDRRM-SAR-001','password_hash'=>Hash::make('password'),'is_validated'=>1,'full_name'=>'Miguel Reyes','title'=>'Responder','contact_number'=>'09170001001','created_at'=>now(),'updated_at'=>now()]);

$svc = new App\Services\AuthService();
var_dump(DB::table('users')->whereRaw('LOWER(username) = ?', ['miguel.reyes'])->first());
var_dump(DB::table('responders')->whereRaw('LOWER(responder_code) = ?', ['bdrrm-sar-001'])->first());

$ref = new ReflectionClass($svc);
$method = $ref->getMethod('userFromResponderLogin');
$method->setAccessible(true);
$u = $method->invoke($svc, 'BDRRM-SAR-001');
var_dump($u ? $u->toArray() : null);

$request = new App\Http\Requests\LoginRequest(['login'=>'BDRRM-SAR-001','password'=>'password','device_name'=>'resqperation-mobile']);
$response = $svc->login($request);
var_dump($response->getStatusCode());
var_dump($response->getContent());
