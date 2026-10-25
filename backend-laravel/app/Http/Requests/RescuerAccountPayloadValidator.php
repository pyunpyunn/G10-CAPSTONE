<?php

namespace App\Http\Requests;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RescuerAccountPayloadValidator
{
    public function validate(Request $request, bool $isUpdate = false, ?object $existing = null): array
    {
        if (in_array($request->input('duty_status'), ['standby', 'stand-by'], true)) {
            $request->merge(['duty_status' => 'available']);
        }

        $messages = [
            'account_id.regex' => 'Field account ID must use the team acronym and a team sequence, for example SRR-1000.',
            'responder_code.regex' => 'Responder code must use the team acronym and a team sequence, for example SRR-1000.',
            'first_name.required' => 'First name is required.',
            'last_name.required' => 'Last name is required.',
            'team_name.required' => 'Team is required before creating a rescuer account.',
            'contact_number.required' => 'Mobile number is required.',
            'title.required' => 'Responder role is required.',
            'duty_status.required' => 'Duty status is required.',
            'password.min' => 'Temporary password must have at least 6 characters.',
        ];

        // The saved account determines this exception, never the role submitted
        // by a caller. Existing administrators may retain numeric identifiers.
        $numericAdminId = $isUpdate && in_array(strtolower((string) ($existing->role_key ?? '')),
            ['operations_admin', 'super_admin', 'superadmin', 'admin'], true);
        $validated = $request->validate($this->rules($isUpdate, $numericAdminId), $messages);
        return $validated;
    }
    public function rules(bool $isUpdate = false, bool $numericAdminId = false): array
    {
        $identifier = $isUpdate && $numericAdminId
            ? 'regex:/^(?:[A-Z0-9]{2,8}-[0-9]{4,}|[0-9]{10})$/i'
            : 'regex:/^[A-Z0-9]{2,8}-[0-9]{4,}$/i';
        return [
            'account_id' => ['nullable', 'string', 'max:30', $identifier],
            'responder_code' => ['nullable', 'string', 'max:80', $identifier],
            'account_status' => ['nullable', Rule::in(array_keys(config('rescuers.account_statuses')))],
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
            'team_id' => ['nullable', 'integer', 'exists:rescue_teams,team_id'],
            'team_name' => [$isUpdate ? 'nullable' : 'required', 'string', 'max:100'],
            'team_code' => ['nullable', 'string', 'max:20'],
            'team_type' => ['nullable', 'string', 'max:80'],
            'role_id' => ['nullable', 'integer', 'exists:roles,role_id'],
            'title' => ['required', 'string', 'max:100'],
            'duty_status' => ['required', Rule::in(array_keys(config('rescuers.duty_statuses')))],
            'skills' => ['nullable', 'string', 'max:1000'],
            'training_notes' => ['nullable', 'string', 'max:1500'],
            'certification_reference' => ['nullable', 'string', 'max:150'],
            'equipment_notes' => ['nullable', 'string', 'max:1500'],
        ];

    }

    public function formConstraints(bool $isUpdate = false): array
    {
        return app(\App\Support\FormInputConstraints::class)->fromRules($this->rules($isUpdate));
    }

}
