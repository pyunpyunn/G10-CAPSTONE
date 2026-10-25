<?php

namespace Tests\Feature;

use App\Actions\NormalizeResponderCodes;
use App\Http\Requests\RescuerAccountPayloadValidator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\Request;
use Tests\TestCase;

class NormalizeResponderCodesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('roles',function($t){$t->integer('role_id');$t->string('role_key');$t->string('base_role_key');});
        Schema::create('rescue_teams',function($t){$t->integer('team_id');$t->string('team_code');});
        Schema::create('users',function($t){$t->string('user_id');$t->string('username');$t->string('login_id');$t->string('password');$t->integer('role_id');});
        Schema::create('responders',function($t){$t->integer('responder_id');$t->string('user_id');$t->string('username');$t->string('responder_code');$t->string('full_name');$t->integer('team_id');$t->timestamp('updated_at')->nullable();});
        DB::table('roles')->insert([['role_id'=>1,'role_key'=>'tm','base_role_key'=>'rescuer'],['role_id'=>2,'role_key'=>'operations_admin','base_role_key'=>'operations_admin']]);
        DB::table('rescue_teams')->insert([['team_id'=>1,'team_code'=>'DANA'],['team_id'=>2,'team_code'=>'HQCC']]);
        foreach ([1,2,3] as $id) {
            $login=$id===3?'2026035500':'member'.$id;
            $code=$id===3?'2026035500':'BDRRM-DANA-00'.$id;
            DB::table('users')->insert(['user_id'=>'U'.$id,'username'=>$login,'login_id'=>$login,'password'=>'original-hash','role_id'=>$id===3?2:1]);
            DB::table('responders')->insert(['responder_id'=>$id,'user_id'=>'U'.$id,'username'=>$code,'responder_code'=>$code,'full_name'=>'Member '.$id,'team_id'=>$id===3?2:1]);
        }
    }

    public function test_dry_run_and_apply_are_collision_safe_and_keep_logins_passwords_and_admin_id(): void
    {
        // A reserved login must not be allocated to another account.
        DB::table('users')->insert(['user_id'=>'OTHER','username'=>'RDANA-9000','login_id'=>'RDANA-9000','password'=>'other-hash','role_id'=>1]);
        $action=app(NormalizeResponderCodes::class);
        $plan=$action->run();
        $this->assertSame(['RDANA-9001','RDANA-9002'],array_column($plan,'new_code'));
        $this->assertDatabaseHas('responders',['responder_id'=>1,'responder_code'=>'BDRRM-DANA-001']);
        $this->assertSame($plan,$action->run(true));
        $this->assertDatabaseHas('responders',['responder_id'=>1,'responder_code'=>'RDANA-9001','username'=>'BDRRM-DANA-001','user_id'=>'U1']);
        $this->assertDatabaseHas('users',['user_id'=>'U1','username'=>'member1','login_id'=>'member1','password'=>'original-hash']);
        $this->assertDatabaseHas('responders',['responder_id'=>3,'responder_code'=>'2026035500']);
        $this->assertSame([],$action->run(true));
    }

    public function test_an_old_open_edit_form_cannot_restore_the_legacy_code(): void
    {
        $request=Request::create('/','PATCH',['account_id'=>'BDRRM-DANA-001','responder_code'=>'BDRRM-DANA-001',
            'first_name'=>'Member','last_name'=>'One','contact_number'=>'09123456789','title'=>'Team Member','duty_status'=>'available']);
        $payload=app(RescuerAccountPayloadValidator::class)->validate($request,true,(object)[
            'role_key'=>'tm','username'=>'BDRRM-DANA-001','responder_code'=>'RDANA-9000']);
        $this->assertSame('RDANA-9000',$payload['account_id']);
        $this->assertSame('RDANA-9000',$payload['responder_code']);
    }

    public function test_legacy_user_tables_without_a_login_id_column_are_supported(): void
    {
        Schema::table('users',fn($table)=>$table->dropColumn('login_id'));
        $changes=app(NormalizeResponderCodes::class)->run();
        $this->assertSame('RDANA-9000',$changes[0]['new_code']);
    }
}
