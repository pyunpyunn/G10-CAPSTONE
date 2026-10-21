<?php

namespace App\Http\Requests;

class MobileLoginRequest extends LoginRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'device_uuid' => ['required', 'string', 'max:150'],
        ]);
    }
}