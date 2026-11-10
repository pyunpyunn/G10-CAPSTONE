<?php

namespace App\Support;

final class PasswordPolicy
{
    public static function rules(bool $required = true): array
    {
        return [
            $required ? 'required' : 'nullable',
            'string',
            'min:8',
            'max:128',
            'regex:/[A-Z]/',
            'regex:/[0-9]/',
            'regex:/[^A-Za-z0-9\s]/',
        ];
    }

    public static function messages(): array
    {
        return [
            'password.min' => 'Password must be at least 8 characters.',
            'password.max' => 'Password is too long.',
            'password.regex' => 'Password must contain an uppercase letter, a number, and a symbol.',
        ];
    }
}
