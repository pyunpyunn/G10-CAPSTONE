<?php

namespace App\Services\Mobile;

use App\Presenters\HouseholdMobilePresenter;
use App\Queries\HouseholdMobileReadQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use App\Support\RequestSchema as Schema;
use Illuminate\Support\Str;

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
        $householdId = $this->support->householdId($user);

        if (! $householdId) {
            return response()->json(['message' => 'This account is not linked to a household record.'], 403);
        }

        if (! Schema::hasTable('household_members')) {
            return $this->support->missingTableResponse('household_members');
        }

        $memberKeyColumn = $this->readQuery->memberKeyColumn();

        if (! $memberKeyColumn) {
            return response()->json(['message' => 'The household member ID column is not available.'], 503);
        }

        $member = DB::table('household_members')
            ->where('household_id', $householdId)
            ->where($memberKeyColumn, $memberId)
            ->when(Schema::hasColumn('household_members', 'deleted_at'), fn ($query) => $query->whereNull('deleted_at'))
            ->first();

        if (! $member) {
            return response()->json(['message' => 'Household member was not found.'], 404);
        }

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:80'],
            'middle_name' => ['nullable', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'relationship' => ['required', 'string', 'max:80'],
            'gender' => ['nullable', 'string', 'max:30'],
            'age' => ['nullable', 'integer', 'min:0', 'max:120'],
            'birth_date' => ['nullable', 'date', 'before_or_equal:today'],
            'special_needs' => ['nullable', 'string', 'max:500'],
        ], [
            'first_name.required' => 'Enter the member first name.',
            'last_name.required' => 'Enter the member last name.',
            'relationship.required' => 'Enter the member relationship.',
        ]);

        $now = now();
        $fullName = trim(collect([
            $validated['first_name'],
            $validated['middle_name'] ?? '',
            $validated['last_name'],
        ])->filter()->implode(' '));

        $age = $validated['age'] ?? null;

        if (! $age && ! empty($validated['birth_date'])) {
            $age = Carbon::parse($validated['birth_date'])->age;
        }

        DB::table('household_members')
            ->where('household_id', $householdId)
            ->where($memberKeyColumn, $memberId)
            ->update($this->support->filterColumns('household_members', [
                'first_name' => $validated['first_name'],
                'middle_name' => $validated['middle_name'] ?? null,
                'last_name' => $validated['last_name'],
                'name' => $fullName,
                'relation' => $validated['relationship'],
                'relationship_id' => $this->relationshipId($validated['relationship']),
                'gender' => $validated['gender'] ?? null,
                'sex' => $this->sexFromGender($validated['gender'] ?? null),
                'gender_id' => $this->genderId($validated['gender'] ?? null),
                'age' => $age,
                'birth_date' => $validated['birth_date'] ?? null,
                'special_needs' => $validated['special_needs'] ?? null,
                'updated_at' => $now,
            ]));

        $this->support->writeAuditLog($request, 'mobile_update_household_member', 'household_members', $memberId, $validated);

        $devices = $this->readQuery->devices($householdId);
        $members = $this->readQuery->members($householdId, $devices, $user);
        $updatedMember = $members->first(fn (array $item): bool => (string) $item['member_id'] === (string) $memberId);

        return response()->json([
            'message' => 'Household member information saved.',
            'data' => [
                'member' => $updatedMember,
                'members' => $members->values(),
            ],
        ]);
    }

    private function relationshipId(?string $relationship): ?int
    {
        if (! $relationship || ! Schema::hasTable('relationships')) {
            return null;
        }

        $value = trim($relationship);
        $key = Str::of($value)->lower()->replace(['/', '-'], ' ')->snake()->toString();
        $keys = collect([$key]);

        if (in_array($key, ['son', 'daughter', 'son_daughter'], true)) {
            $keys->push('child');
        }

        if (in_array($key, ['father', 'mother', 'father_mother'], true)) {
            $keys->push('parent');
        }

        if (in_array($key, ['brother', 'sister', 'brother_sister'], true)) {
            $keys->push('sibling');
        }

        if (Str::contains($key, 'relative')) {
            $keys->push('other');
        }

        $id = DB::table('relationships')
            ->whereRaw('LOWER(relationship_label) = ?', [Str::lower($value)])
            ->orWhereIn('relationship_key', $keys->unique()->values()->all())
            ->orderBy('relationship_id')
            ->value('relationship_id');

        return $id ? (int) $id : null;
    }

    private function genderId(?string $gender): ?int
    {
        if (! $gender || ! Schema::hasTable('genders')) {
            return null;
        }

        $value = trim($gender);
        $key = Str::of($value)->lower()->replace(['/', '-'], ' ')->snake()->toString();

        $id = DB::table('genders')
            ->whereRaw('LOWER(gender_label) = ?', [Str::lower($value)])
            ->orWhere('gender_key', $key)
            ->value('gender_id');

        return $id ? (int) $id : null;
    }

    private function sexFromGender(?string $gender): ?string
    {
        $value = Str::lower(trim((string) $gender));

        if ($value === '') {
            return null;
        }

        if (Str::startsWith($value, 'm')) {
            return 'M';
        }

        if (Str::startsWith($value, 'f')) {
            return 'F';
        }

        return 'O';
    }
}







