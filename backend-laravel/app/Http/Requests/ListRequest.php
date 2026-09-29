<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Shared pagination input for API collection endpoints.
 *
 * Services that may be called without HTTP validation should use the clamp
 * helpers so internal callers cannot request an excessive page size either.
 */
class ListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'page' => $this->query('page', 1),
            'per_page' => $this->query('per_page', 15),
        ]);
    }

    public function rules(): array
    {
        return self::paginationRules();
    }

    public static function paginationRules(): array
    {
        return [
            'page' => ['required', 'integer', 'min:1'],
            'per_page' => ['required', 'integer', 'min:1', 'max:100'],
        ];
    }

    public static function clampPage(mixed $page): int
    {
        return max(1, (int) $page);
    }

    public static function clampPerPage(mixed $perPage, int $default = 15): int
    {
        return min(100, max(1, (int) ($perPage ?? $default)));
    }
}
