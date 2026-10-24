<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Responder;
use Illuminate\Http\JsonResponse;

class PersonnelProfileController extends Controller
{
    public function show(int $responderId): JsonResponse
    {
        $person = Responder::query()->with(['user.role', 'team'])
            ->whereNull('deleted_at')
            ->whereHas('user', fn ($query) => $query->whereNull('deleted_at'))
            ->findOrFail($responderId);

        // A directory profile deliberately excludes account controls, credentials,
        // home address, medical details and emergency contacts.
        return response()->json(['data' => ['profile' => [
            'responder_id' => $person->responder_id,
            'full_name' => $person->full_name ?: $person->user?->full_name,
            'title' => $person->title,
            'role_name' => $person->user?->role?->role_name,
            'team_name' => $person->team?->team_name,
            'team_type' => $person->team?->team_type,
            'contact_number' => $person->contact_number,
            'email' => $person->user?->email,
            'duty_status' => $person->duty_status,
            'is_active' => (bool) $person->user?->is_active,
            'skills' => $person->skills,
        ]]]);
    }
}
