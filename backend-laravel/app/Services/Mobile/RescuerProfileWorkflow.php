<?php

namespace App\Services\Mobile;

use App\Queries\RescuerAssignmentQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Support\RequestSchema as Schema;
use Illuminate\Validation\ValidationException;

class RescuerProfileWorkflow
{
    public function __construct(private \App\Services\Mobile\RescuerMobileSupport $support, private RescuerAssignmentQuery $assignmentQuery) {}

    public function profile(Request $request): array
    {
        $user = $request->user()?->load('role');
        $responder = $this->support->responderForUser($user);

        return [
            'user' => $user,
            'responder' => $responder,
            'active_assignment' => $this->assignmentQuery->active($responder?->responder_id),
        ];
    }

    public function updateProfile(Request $request): JsonResponse|array
    {
        $user = $request->user()?->load('role');
        $responder = $this->support->responderForUser($user);

        if (! $user || ! $responder) {
            return response()->json([
                'message' => 'Rescuer profile was not found for this account.',
            ], 404);
        }

        $validated = $request->validate([
            'username' => ['required', 'string', 'min:3', 'max:40', 'regex:/^[A-Za-z0-9._-]+$/'],
            'first_name' => ['required', 'string', 'max:100'],
            'middle_initial' => ['nullable', 'string', 'max:5'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'contact_number' => ['required', 'string', 'max:20'],
            'emergency_contact_name' => ['nullable', 'string', 'max:150'],
            'emergency_contact_number' => ['nullable', 'string', 'max:30'],
            'blood_type' => ['nullable', 'string', 'max:10'],
            'address' => ['nullable', 'string', 'max:1000'],
            'skills' => ['nullable', 'string', 'max:1000'],
        ], [
            'username.required' => 'Username is required.',
            'username.min' => 'Username must have at least 3 characters.',
            'username.regex' => 'Username can only use letters, numbers, dot, underscore, or dash.',
            'first_name.required' => 'First name is required.',
            'last_name.required' => 'Last name is required.',
            'contact_number.required' => 'Mobile number is required.',
        ]);

        $username = $this->normalizeUsername($validated['username']);
        $this->ensureUniqueMobileUsername($username, $user->user_id);

        $now = now();
        $fullName = $this->fullNameFromProfile($validated);

        DB::transaction(function () use ($request, $user, $responder, $validated, $username, $fullName, $now): void {
            DB::table('users')
                ->where('user_id', $user->user_id)
                ->update($this->support->filterColumns('users', [
                    'first_name' => trim($validated['first_name']),
                    'last_name' => trim($validated['last_name']),
                    'name' => $fullName,
                    'username' => $username,
                    'email' => $validated['email'] ?? null,
                    'contact_number' => $validated['contact_number'],
                    'updated_at' => $now,
                ]));

            DB::table('responders')
                ->where('responder_id', $responder->responder_id)
                ->update($this->support->filterColumns('responders', [
                    'full_name' => $fullName,
                    'contact_number' => $validated['contact_number'],
                    'emergency_contact_name' => $validated['emergency_contact_name'] ?? null,
                    'emergency_contact_number' => $validated['emergency_contact_number'] ?? null,
                    'blood_type' => $validated['blood_type'] ?? null,
                    'address' => $validated['address'] ?? null,
                    'skills' => $validated['skills'] ?? null,
                    'updated_at' => $now,
                ]));

            $this->support->writeAuditLog($request, 'mobile_update_profile', 'responders', (string) $responder->responder_id, [
                'username' => $username,
                'full_name' => $fullName,
            ]);
        });

        $freshUser = $request->user()?->fresh('role');
        $freshResponder = $this->support->responderForUser($freshUser);

        return [
            'message' => 'Profile updated.',
            'data' => [
                'user' => $freshUser,
                'responder' => $freshResponder,
                'active_assignment' => $this->assignmentQuery->active($freshResponder?->responder_id),
            ],
        ];
    }

    private function normalizeUsername(string $value): string
    {
        return strtolower(trim($value));
    }

    private function ensureUniqueMobileUsername(string $username, string $currentUserId): void
    {
        if (preg_match('/^BDRRM-[A-Z0-9]{2,8}-[0-9]{3}$/i', $username) === 1) {
            throw ValidationException::withMessages([
                'username' => ['Use a personal username, not the BDRRM account ID.'],
            ]);
        }

        $existingUser = DB::table('users')
            ->whereRaw('LOWER(username) = ?', [$username])
            ->where('user_id', '<>', $currentUserId)
            ->exists();

        $existingResponderLogin = Schema::hasTable('responders')
            && DB::table('responders')
                ->where(function ($query) use ($username): void {
                    $query->whereRaw('LOWER(username) = ?', [$username])
                        ->orWhereRaw('LOWER(responder_code) = ?', [$username]);
                })
                ->exists();

        if ($existingUser || $existingResponderLogin) {
            throw ValidationException::withMessages([
                'username' => ['This username is already used. Choose another username.'],
            ]);
        }
    }

    private function fullNameFromProfile(array $validated): string
    {
        $middle = $this->formatMiddleInitial($validated['middle_initial'] ?? '');

        return trim(collect([
            trim((string) $validated['first_name']),
            $middle,
            trim((string) $validated['last_name']),
        ])->filter()->join(' '));
    }

    private function formatMiddleInitial(?string $value): string
    {
        $middle = strtoupper(trim((string) $value));
        $middle = str_replace('.', '', $middle);

        return $middle === '' ? '' : substr($middle, 0, 1).'.';
    }
}







