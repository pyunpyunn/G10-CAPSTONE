<?php

namespace App\Services\Mobile;

use App\Presenters\HouseholdMobilePresenter;
use App\Queries\HouseholdMobileReadQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Support\RequestSchema as Schema;
use Illuminate\Support\Str;

class HouseholdTrustedHouseholdWorkflow
{
    public function __construct(
        private HouseholdMobileSupport $support,
        private HouseholdMobileReadQuery $readQuery,
        private HouseholdMobilePresenter $presenter,
    ) {}

    public function trustedHouseholds(Request $request): JsonResponse
    {
        $householdId = $this->support->householdId($request->user());

        return response()->json([
            'data' => [
                'is_available' => Schema::hasTable('trusted_households'),
                'households' => $householdId ? $this->readQuery->trustedRows($householdId) : [],
            ],
        ]);
    }

    public function lookupTrustedHousehold(Request $request, string $householdId): JsonResponse
    {
        $currentHouseholdId = $this->support->householdId($request->user());
        $trustedHouseholdId = $this->readQuery->resolveTrustedHouseholdId($householdId);

        if ($currentHouseholdId === $trustedHouseholdId) {
            return response()->json(['message' => 'You cannot add your own household as trusted.'], 409);
        }

        $household = $trustedHouseholdId ? $this->readQuery->householdRecord($trustedHouseholdId) : null;

        if (! $household) {
            return response()->json([
                'message' => 'Household account was not found. Enter the household ID like HH-2024035503 or the account ID like 2024035503.',
            ], 404);
        }

        $devices = $this->readQuery->devices($trustedHouseholdId);

        return response()->json([
            'data' => [
                'household_id' => $household->household_id,
                'family_name' => $this->presenter->familyName($household->household_name ?? $household->household_code ?? $household->household_id),
                'household_code' => $household->household_code ?? null,
                'members' => $this->readQuery->members($trustedHouseholdId, $devices, null, $this->readQuery->activeEvent()['event_id'] ?? null)
                    ->map(fn (array $member): array => [
                        'member_id' => $member['member_id'],
                        'name' => $member['name'],
                        'household_relationship' => $member['relationship'] ?? 'Member',
                        'relationship_to_family' => '',
                    ])
                    ->values(),
            ],
        ]);
    }

    public function storeTrustedHousehold(Request $request): JsonResponse
    {
        if (! Schema::hasTable('trusted_households')) {
            return $this->support->missingTableResponse('trusted_households');
        }

        $user = $request->user();
        $householdId = $this->support->householdId($user);

        if (! $householdId) {
            return response()->json(['message' => 'This account is not linked to a household record.'], 403);
        }

        $validated = $request->validate([
            'trusted_household_id' => ['required', 'string', 'max:255'],
            'reason' => ['required', 'string', 'max:500'],
            'member_relationships' => ['nullable', 'array'],
        ], [
            'trusted_household_id.required' => 'Enter the household ID you want to connect with.',
            'reason.required' => 'Give a short reason for this trusted household request.',
        ]);

        $trustedHouseholdId = $this->readQuery->resolveTrustedHouseholdId($validated['trusted_household_id']);

        if ($trustedHouseholdId === $householdId) {
            return response()->json(['message' => 'You cannot add your own household as trusted.'], 409);
        }

        if (! $trustedHouseholdId || ! $this->readQuery->householdRecord($trustedHouseholdId)) {
            return response()->json([
                'message' => 'Household account was not found. Enter the household ID like HH-2024035503 or the account ID like 2024035503.',
            ], 404);
        }

        $existing = DB::table('trusted_households')
            ->where('requesting_household_id', $householdId)
            ->where('trusted_household_id', $trustedHouseholdId)
            ->whereIn('validation_status', ['pending', 'validated'])
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'Trusted household request already exists.',
                'data' => [
                    'connection_id' => $existing->connection_id,
                    'validation_status' => $existing->validation_status,
                ],
            ]);
        }

        $now = now();
        $connectionId = 'TH-' . now()->format('Ymd') . '-' . strtoupper(Str::random(5));

        DB::table('trusted_households')->insert($this->support->filterColumns('trusted_households', [
            'connection_id' => $connectionId,
            'requesting_household_id' => $householdId,
            'trusted_household_id' => $trustedHouseholdId,
            'reason' => $validated['reason'],
            'validation_status' => 'pending',
            'member_relationships' => json_encode($validated['member_relationships'] ?? [], JSON_UNESCAPED_SLASHES),
            'created_by_user_id' => $user?->user_id,
            'created_at' => $now,
            'updated_at' => $now,
        ]));

        $this->support->writeAuditLog($request, 'mobile_trusted_household_request', 'trusted_households', $connectionId, array_merge($validated, [
            'resolved_trusted_household_id' => $trustedHouseholdId,
        ]));

        return response()->json([
            'message' => 'Trusted household request submitted for validation.',
            'data' => [
                'connection_id' => $connectionId,
                'validation_status' => 'pending',
            ],
        ], 201);
    }
}







