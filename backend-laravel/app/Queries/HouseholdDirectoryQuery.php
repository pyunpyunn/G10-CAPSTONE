<?php

namespace App\Queries;

use App\Http\Requests\ListRequest;
use App\Support\RequestSchema as Schema;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class HouseholdDirectoryQuery
{
    private const HOUSEHOLD_COLUMNS = [
        'household_code',
        'household_number',
        'household_name',
        'email',
        'created_by',
        'contact_number',
        'emergency_contact',
        'member_count',
        'created_at',
        'updated_at',
    ];

    private const ADDRESS_COLUMNS = [
        'street_address',
        'street',
        'barangay_id',
        'barangay_name',
        'purok_sitio',
        'house_number',
        'zipcode_id',
        'zip_code',
        'full_address',
        'created_at',
        'updated_at',
    ];

    private const MEMBER_COLUMNS = [
        'first_name',
        'middle_name',
        'last_name',
        'name',
        'birth_date',
        'gender_id',
        'gender',
        'sex',
        'relationship_id',
        'relation',
        'civil_status_id',
        'civil_status',
        'occupation',
        'education_level_id',
        'education_level',
        'religion',
        'special_needs',
        'is_household_head',
        'can_report_for_household',
        'is_graduate',
        'is_pwd',
        'is_senior',
        'is_pregnant',
        'contact_number',
        'created_at',
        'updated_at',
    ];

    public function page(Request $request): array
    {
        $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
        ]);

        $this->assertSchema();

        $query = $this->joinedHouseholds();
        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            $query->where(function (Builder $searchQuery) use ($search): void {
                foreach ([
                    ['households', 'household_id', 'h.household_id'],
                    ['households', 'household_code', 'h.household_code'],
                    ['households', 'household_number', 'h.household_number'],
                    ['households', 'household_name', 'h.household_name'],
                    ['addresses', 'full_address', 'a.full_address'],
                    ['addresses', 'purok_sitio', 'a.purok_sitio'],
                    ['sitios', 'sitio_name', 's.sitio_name'],
                    ['puroks', 'purok_name', 'p.purok_name'],
                ] as [$table, $column, $qualifiedColumn]) {
                    if (Schema::hasColumn($table, $column)) {
                        $searchQuery->orWhere($qualifiedColumn, 'like', "%{$search}%");
                    }
                }
            });
        }

        $paginator = $query
            ->orderBy('h.household_name')
            ->orderBy('h.household_id')
            ->paginate(
                ListRequest::clampPerPage($request->query('per_page'), 25),
                ['*'],
                'page',
                ListRequest::clampPage($request->query('page')),
            );

        $items = collect($paginator->items());
        $householdIds = $items->pluck('household_id')->all();
        $members = $this->membersFor($householdIds)->groupBy('household_id');
        $accounts = $this->accountsFor($householdIds, $members)->groupBy('household_id');

        return [
            'summary' => $this->summary(),
            'households' => [
                'data' => $items->map(function (object $row) use ($members, $accounts): array {
                    $householdMembers = $members->get($row->household_id, collect())->values();
                    $householdAccounts = $accounts->get($row->household_id, collect())->values();

                    return [
                        'household' => [
                            'household_id' => $row->household_id,
                            'address_id' => $row->address_id,
                            'household_code' => $row->household_code,
                            'household_number' => $row->household_number,
                            'household_name' => $row->household_name,
                            'email' => $row->email,
                            'created_by' => $row->created_by,
                            'contact_number' => $row->contact_number,
                            'emergency_contact' => $row->emergency_contact,
                            'member_count' => $row->member_count === null ? null : (int) $row->member_count,
                            'created_at' => $row->created_at,
                            'updated_at' => $row->updated_at,
                        ],
                        'address' => $row->matched_address_id === null ? null : [
                            'address_id' => $row->matched_address_id,
                            'street_address' => $row->address_street_address,
                            'street' => $row->address_street,
                            'barangay_id' => $row->address_barangay_id,
                            'barangay_name' => $row->address_barangay_name,
                            'house_number' => $row->address_house_number,
                            'zipcode_id' => $row->address_zipcode_id,
                            'purok_sitio' => $row->address_purok_sitio,
                            'full_address' => $row->address_full_address,
                            'zip_code' => $row->address_zip_code,
                            'sitio_id' => $row->address_sitio_id,
                            'registered_sitio_id' => $row->registered_sitio_id,
                            'sitio_name' => $row->registered_sitio_name,
                            'purok_id' => $row->address_purok_id,
                            'purok_name' => $row->registered_purok_name,
                            'created_at' => $row->address_created_at,
                            'updated_at' => $row->address_updated_at,
                        ],
                        'geographic_status' => $this->geographicStatus($row),
                        'member_count' => $householdMembers->count(),
                        'account_count' => $householdAccounts->count(),
                        'members' => $householdMembers->all(),
                        'accounts' => $householdAccounts->all(),
                    ];
                })->values(),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                    'from' => $paginator->firstItem(),
                    'to' => $paginator->lastItem(),
                ],
            ],
        ];
    }

    private function assertSchema(): void
    {
        $requiredColumns = [
            'households' => ['household_id', 'address_id', 'member_count'],
            'addresses' => ['address_id', 'sitio_id', 'purok_id'],
            'sitios' => ['sitio_id', 'sitio_name'],
            'puroks' => ['purok_id', 'purok_name', 'sitio_id'],
            'household_members' => ['member_id', 'household_id'],
            'users' => ['user_id', 'household_id', 'member_id'],
        ];

        foreach ($requiredColumns as $table => $columns) {
            if (! Schema::hasTable($table)) {
                throw new RuntimeException("Household data directory requires the {$table} table.");
            }

            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    throw new RuntimeException("Household data directory requires {$table}.{$column}.");
                }
            }
        }
    }

    private function joinedHouseholds(): Builder
    {
        $query = DB::table('households as h')
            ->leftJoin('addresses as a', 'a.address_id', '=', 'h.address_id')
            ->leftJoin('puroks as p', 'p.purok_id', '=', 'a.purok_id')
            ->leftJoin('sitios as s', 's.sitio_id', '=', DB::raw('COALESCE(p.sitio_id, a.sitio_id)'));

        if (Schema::hasColumn('households', 'deleted_at')) {
            $query->whereNull('h.deleted_at');
        }

        $columns = ['h.household_id', 'h.address_id'];
        foreach (self::HOUSEHOLD_COLUMNS as $column) {
            $columns[] = Schema::hasColumn('households', $column)
                ? "h.{$column}"
                : DB::raw("NULL as {$column}");
        }

        foreach (self::ADDRESS_COLUMNS as $column) {
            $columns[] = Schema::hasColumn('addresses', $column)
                ? "a.{$column} as address_{$column}"
                : DB::raw("NULL as address_{$column}");
        }

        $columns[] = 'a.sitio_id as address_sitio_id';
        $columns[] = 'a.address_id as matched_address_id';
        $columns[] = 'a.purok_id as address_purok_id';
        $columns[] = DB::raw('COALESCE(p.sitio_id, a.sitio_id) as registered_sitio_id');
        $columns[] = 's.sitio_id as registered_sitio_reference';
        $columns[] = 's.sitio_name as registered_sitio_name';
        $columns[] = 'p.purok_id as registered_purok_reference';
        $columns[] = 'p.purok_name as registered_purok_name';

        return $query->select($columns);
    }

    private function membersFor(array $householdIds): Collection
    {
        if ($householdIds === []) {
            return collect();
        }

        $columns = ['member_id', 'household_id'];
        foreach (self::MEMBER_COLUMNS as $column) {
            $columns[] = Schema::hasColumn('household_members', $column)
                ? $column
                : DB::raw("NULL as {$column}");
        }

        $members = DB::table('household_members')
            ->whereIn('household_id', $householdIds)
            ->when(Schema::hasColumn('household_members', 'deleted_at'), fn (Builder $query) => $query->whereNull('deleted_at'))
            ->orderBy('household_id')
            ->orderBy('member_id')
            ->get($columns);

        return $members->map(function (object $member): object {
            $name = trim(implode(' ', array_filter([
                $member->first_name ?? null,
                $member->middle_name ?? null,
                $member->last_name ?? null,
            ])));
            $member->display_name = $name !== '' ? $name : ($member->name ?? null);

            return $member;
        });
    }

    private function accountsFor(array $householdIds, Collection $members): Collection
    {
        if ($householdIds === []) {
            return collect();
        }

        $columns = ['user_id', 'household_id', 'member_id'];
        foreach (['username', 'name', 'first_name', 'last_name', 'email', 'role_id', 'contact_number', 'is_active', 'created_at'] as $column) {
            $columns[] = Schema::hasColumn('users', $column)
                ? $column
                : DB::raw("NULL as {$column}");
        }

        $memberIds = $members->flatten(1)
            ->groupBy('household_id')
            ->map(fn (Collection $rows): array => $rows->pluck('member_id')->all());

        return DB::table('users')
            ->whereIn('household_id', $householdIds)
            ->when(Schema::hasColumn('users', 'deleted_at'), fn (Builder $query) => $query->whereNull('deleted_at'))
            ->orderBy('household_id')
            ->orderBy('user_id')
            ->get($columns)
            ->map(function (object $account) use ($memberIds): object {
                $linkedMembers = $memberIds->get($account->household_id, []);
                $account->member_link_status = $account->member_id === null
                    ? 'not_assigned'
                    : (in_array($account->member_id, $linkedMembers, true) ? 'matched' : 'unmatched');

                return $account;
            });
    }

    private function summary(): array
    {
        $households = $this->joinedHouseholds();
        $missingAddress = (clone $households)->whereNull('a.address_id')->count();
        $missingPurok = (clone $households)->whereNull('a.purok_id')->count();
        $missingSitio = (clone $households)->whereRaw('COALESCE(p.sitio_id, a.sitio_id) IS NULL')->count();
        $sitioPurokMismatches = (clone $households)
            ->whereNotNull('a.sitio_id')
            ->whereNotNull('p.sitio_id')
            ->whereColumn('a.sitio_id', '<>', 'p.sitio_id')
            ->count();

        $memberCounts = DB::table('household_members')
            ->select('household_id')
            ->selectRaw('COUNT(*) AS actual_member_count')
            ->when(Schema::hasColumn('household_members', 'deleted_at'), fn (Builder $query) => $query->whereNull('deleted_at'))
            ->groupBy('household_id');
        $memberCountMismatches = DB::table('households as h')
            ->leftJoinSub($memberCounts, 'members', 'members.household_id', '=', 'h.household_id')
            ->when(Schema::hasColumn('households', 'deleted_at'), fn (Builder $query) => $query->whereNull('h.deleted_at'))
            ->whereRaw('COALESCE(h.member_count, 0) <> COALESCE(members.actual_member_count, 0)')
            ->count();

        $accounts = DB::table('users as u')
            ->whereNotNull('u.household_id')
            ->when(Schema::hasColumn('users', 'deleted_at'), fn (Builder $query) => $query->whereNull('u.deleted_at'));
        $unmatchedAccounts = (clone $accounts)
            ->whereNotExists(function (Builder $query): void {
                $query->selectRaw('1')
                    ->from('households as matching_household')
                    ->whereColumn('matching_household.household_id', 'u.household_id')
                    ->when(Schema::hasColumn('households', 'deleted_at'), fn (Builder $nested) => $nested->whereNull('matching_household.deleted_at'));
            })
            ->count();

        return [
            'active_households' => (clone $households)->count(),
            'active_members' => DB::table('household_members')
                ->when(Schema::hasColumn('household_members', 'deleted_at'), fn (Builder $query) => $query->whereNull('deleted_at'))
                ->count(),
            'households_missing_address' => $missingAddress,
            'households_missing_sitio' => $missingSitio,
            'households_missing_purok' => $missingPurok,
            'household_sitio_purok_mismatches' => $sitioPurokMismatches,
            'household_member_count_mismatches' => $memberCountMismatches,
            'household_accounts' => (clone $accounts)->count(),
            'unmatched_household_accounts' => $unmatchedAccounts,
            'accounts_with_member_id' => (clone $accounts)->whereNotNull('u.member_id')->count(),
        ];
    }

    private function geographicStatus(object $row): string
    {
        if ($row->matched_address_id === null) {
            return 'missing_address';
        }

        if ($row->address_purok_id !== null && $row->registered_purok_reference === null) {
            return 'invalid_purok';
        }

        if ($row->address_sitio_id !== null && $row->registered_sitio_id !== null
            && (string) $row->address_sitio_id !== (string) $row->registered_sitio_id) {
            return 'sitio_mismatch';
        }

        if ($row->registered_sitio_id === null) {
            return 'unassigned_sitio';
        }

        if ($row->registered_sitio_reference === null) {
            return 'invalid_sitio';
        }

        return $row->address_purok_id === null ? 'unassigned_purok' : 'assigned';
    }
}
