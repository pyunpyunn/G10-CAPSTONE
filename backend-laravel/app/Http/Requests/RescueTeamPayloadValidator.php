<?php

namespace App\Http\Requests;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RescueTeamPayloadValidator
{
    public function rules(): array
    {
        return [
            'team_code' => ['required', 'string', 'max:8', 'regex:/^[A-Z0-9]+$/i'],
            'team_name' => ['required', 'string', 'max:100'],
            'team_type' => ['required', 'string', 'max:80'],
            'duty_status' => ['required', Rule::in(config('rescuers.team_duty_statuses'))],
            'assigned_purok_id' => ['nullable', 'integer', 'exists:addresses,address_id'],
            'leader_responder_id' => ['nullable', 'integer', 'exists:responders,responder_id'],
            'member_ids' => ['nullable', 'array'],
            'member_ids.*' => ['integer', 'exists:responders,responder_id'],
        ];
    }

    public function validate(Request $request): array
    {
        return $request->validate($this->rules(), [
            'team_code.required' => 'Team code is required.',
            'team_code.regex' => 'Team code must use letters and numbers only.',
            'team_name.required' => 'Team name is required.',
            'team_type.required' => 'Team type is required.',
            'duty_status.required' => 'Team duty status is required.',
            'duty_status.in' => 'Select a valid team duty status.',
            'leader_responder_id.exists' => 'Selected team leader does not exist.',
            'member_ids.*.exists' => 'One selected member does not exist.',
        ]);
    }

    public function formConstraints(): array
    {
        return app(\App\Support\FormInputConstraints::class)->fromRules($this->rules());
    }
}
