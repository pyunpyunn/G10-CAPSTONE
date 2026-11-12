<?php

namespace Tests\Feature;

use App\Http\Requests\RescuerAccountPayloadValidator;
use Illuminate\Http\Request;
use Tests\TestCase;

class TemporaryResponderPasswordValidationTest extends TestCase
{
    public function test_generated_password_is_accepted_for_a_name_with_one_consonant(): void
    {
        $request = Request::create('/api/responders', 'POST', [
            'account_id' => 'BDRRM-TST-1000',
            'responder_code' => 'BDRRM-TST-1000',
            'account_status' => 'active',
            'first_name' => 'V',
            'last_name' => 'Responder',
            'team_name' => 'Test Team',
            'title' => 'Responder',
            'duty_status' => 'available',
            'contact_number' => '555-0100',
            'password' => 'V@2026!',
        ]);

        $validated = app(RescuerAccountPayloadValidator::class)->validate($request);

        $this->assertSame('V@2026!', $validated['password']);
    }
}
