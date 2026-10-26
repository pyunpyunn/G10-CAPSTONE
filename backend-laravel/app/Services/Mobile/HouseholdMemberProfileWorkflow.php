<?php

namespace App\Services\Mobile;

use App\Models\User;
use App\Presenters\HouseholdMobilePresenter;
use App\Queries\HouseholdMobileReadQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HouseholdMemberProfileWorkflow
{
    public function __construct(
        private HouseholdMobileSupport $support,
        private HouseholdMobileReadQuery $readQuery,
        private HouseholdMobilePresenter $presenter,
    ) {}

    public function updateMember(Request $request, string $memberId): JsonResponse
    {
        $user = $request->user();
        $ownerId = $this->support->authenticatedMemberId($user);
        abort_unless($ownerId === $memberId, 403, 'You may edit only your own contact number.');
        if (is_string($request->input('contact_number'))) {
            $contact = preg_replace('/[ ()-]/', '', trim($request->input('contact_number')));
            $request->merge(['contact_number' => $contact === '' ? null : $contact]);
        }
        $validated = $request->validate([
            'contact_number' => ['present', 'nullable', 'string', 'max:30', 'regex:/^\+?[0-9]{7,15}$/'],
        ]);
        $unexpected = array_diff(array_keys($request->all()), ['contact_number']);
        if ($unexpected) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'profile' => ['Only your contact number can be edited.'],
            ]);
        }
        DB::transaction(function () use ($request, $user, $memberId, $validated): void {
            DB::table('household_members')->where('member_id', $memberId)
                ->where('household_id', $user->household_id)->update([
                    'contact_number' => $validated['contact_number'], 'updated_at' => now(),
                ]);
            User::query()->whereKey($user->getKey())->update([
                'contact_number' => $validated['contact_number'], 'updated_at' => now(),
            ]);
            $this->support->writeAuditLog($request, 'mobile_update_member_contact', 'household_members', $memberId, $validated);
        });
        $user->refresh();
        return response()->json([
            'message' => 'Contact number saved.',
            'data' => ['user' => $this->presenter->userProfile($user, $this->readQuery->memberForUser($user))],
        ]);
    }
}
