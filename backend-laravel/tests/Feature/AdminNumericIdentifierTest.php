<?php

namespace Tests\Feature;

use App\Http\Requests\RescuerAccountPayloadValidator;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AdminNumericIdentifierTest extends TestCase
{
    private function request(): Request
    {
        return Request::create('/', 'PATCH', ['account_id'=>'2026035500','responder_code'=>'2026035500',
            'first_name'=>'Admin','last_name'=>'Account','contact_number'=>'09123456789',
            'title'=>'Operations/Admin','duty_status'=>'available']);
    }

    public function test_saved_administrator_role_allows_numeric_account_and_responder_identifiers(): void
    {
        foreach (['operations_admin','super_admin'] as $role) {
            $payload=app(RescuerAccountPayloadValidator::class)->validate($this->request(),true,(object)['role_key'=>$role]);
            $this->assertSame('2026035500',$payload['responder_code']);
            $this->assertSame('2026035500',$payload['account_id']);
        }
    }

    public function test_a_submitted_admin_role_does_not_bypass_the_saved_field_account_format(): void
    {
        $request=$this->request(); $request->merge(['role_key'=>'operations_admin']);
        $this->expectException(ValidationException::class);
        app(RescuerAccountPayloadValidator::class)->validate($request,true,(object)['role_key'=>'rescuer']);
    }

    public function test_new_field_accounts_still_require_a_team_identifier(): void
    {
        $request=$this->request(); $request->merge(['team_name'=>'Search and Rescue']);
        $this->expectException(ValidationException::class);
        app(RescuerAccountPayloadValidator::class)->validate($request,false,(object)['role_key'=>'operations_admin']);
    }
}
