<?php

namespace App\Http\Requests;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RescuerAccountPayloadValidator
{
    public function validate(Request $request, bool $isUpdate = false): array
    {
        $rules = [
            'account_id' => ['nullable', 'string', 'max:30', 'regex:/^BDRRM-[A-Z0-9]{2,8}-[0-9]{3}$/i'],
            'responder_code' => ['nullable', 'string', 'max:80', 'regex:/^BDRRM-[A-Z0-9]{2,8}-[0-9]{3}$/i'],
            'account_status' => ['nullable', Rule::in(['active', 'reserve', 'disabled'])],
            'first_name' => ['required', 'string', 'max:100'],
            'middle_initial' => ['nullable', 'string', 'max:5'],
            'last_name' => ['required', 'string', 'max:100'],
            'full_name' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'password' => [$isUpdate ? 'nullable' : 'nullable', 'string', 'min:6', 'max:100'],
            'contact_number' => ['required', 'string', 'max:20'],
            'emergency_contact_name' => ['nullable', 'string', 'max:150'],
            'emergency_contact_number' => ['nullable', 'string', 'max:30'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', 'string', 'max:20'],
            'blood_type' => ['nullable', 'string', 'max:10'],
            'address' => ['nullable', 'string', 'max:1000'],
            'team_id' => ['nullable', 'integer'],
            'team_name' => [$isUpdate ? 'nullable' : 'required', 'string', 'max:100'],
            'team_code' => ['nullable', 'string', 'max:20'],
            'team_type' => ['nullable', 'string', 'max:80'],
            'title' => ['required', 'string', 'max:100'],
            'duty_status' => ['required', Rule::in(['on_duty', 'standby', 'reserve', 'off_duty', 'unavailable', 'dispatched', 'on_scene', 'disabled'])],
            'skills' => ['nullable', 'string', 'max:1000'],
            'training_notes' => ['nullable', 'string', 'max:1500'],
            'certification_reference' => ['nullable', 'string', 'max:150'],
            'equipment_notes' => ['nullable', 'string', 'max:1500'],
        ];

        $messages = [
            'account_id.regex' => 'Rescuer Account ID must follow the DB format BDRRM-SAR-001.',
            'responder_code.regex' => 'Responder code must follow the DB format BDRRM-SAR-001.',
            'first_name.required' => 'First name is required.',
            'last_name.required' => 'Last name is required.',
            'team_name.required' => 'Team is required before creating a rescuer account.',
            'contact_number.required' => 'Mobile number is required.',
            'title.required' => 'Responder role is required.',
            'duty_status.required' => 'Duty status is required.',
            'password.min' => 'Temporary password must have at least 6 characters.',
        ];

        $validated = $request->validate($rules, $messages);
        return $validated;
    }
}
