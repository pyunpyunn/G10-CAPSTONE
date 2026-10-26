<?php

namespace Tests\Feature;

use App\Queries\HouseholdDirectoryQuery;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HouseholdDirectoryQueryTest extends TestCase
{
    public function test_directory_joins_registered_household_area_members_and_accounts_without_filtering_unassigned_households(): void
    {
        Schema::create('households', function (Blueprint $table): void {
            $table->string('household_id')->primary();
            $table->string('household_code')->nullable();
            $table->string('household_name')->nullable();
            $table->string('email')->nullable();
            $table->string('created_by')->nullable();
            $table->string('contact_number')->nullable();
            $table->string('emergency_contact')->nullable();
            $table->unsignedInteger('member_count')->default(0);
            $table->unsignedInteger('address_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('addresses', function (Blueprint $table): void {
            $table->unsignedInteger('address_id')->primary();
            $table->unsignedInteger('sitio_id')->nullable();
            $table->unsignedInteger('purok_id')->nullable();
            $table->string('full_address')->nullable();
            $table->string('purok_sitio')->nullable();
        });
        Schema::create('sitios', function (Blueprint $table): void {
            $table->unsignedInteger('sitio_id')->primary();
            $table->string('sitio_name');
        });
        Schema::create('puroks', function (Blueprint $table): void {
            $table->unsignedInteger('purok_id')->primary();
            $table->unsignedInteger('sitio_id');
            $table->string('purok_name');
        });
        Schema::create('household_members', function (Blueprint $table): void {
            $table->string('member_id')->primary();
            $table->string('household_id');
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('relation')->nullable();
            $table->softDeletes();
        });
        Schema::create('users', function (Blueprint $table): void {
            $table->string('user_id')->primary();
            $table->string('household_id')->nullable();
            $table->string('member_id')->nullable();
            $table->string('username')->nullable();
            $table->string('name')->nullable();
            $table->softDeletes();
        });

        DB::table('sitios')->insert([
            ['sitio_id' => 7, 'sitio_name' => 'Old Sitio'],
            ['sitio_id' => 8, 'sitio_name' => 'Sitio Uno'],
        ]);
        DB::table('puroks')->insert(['purok_id' => 29, 'sitio_id' => 8, 'purok_name' => 'Purok 3']);
        DB::table('addresses')->insert([
            ['address_id' => 16, 'sitio_id' => 7, 'purok_id' => 29, 'full_address' => 'House 16', 'purok_sitio' => null],
            ['address_id' => 17, 'sitio_id' => 8, 'purok_id' => null, 'full_address' => 'House 17', 'purok_sitio' => 'Legacy Purok Label'],
        ]);
        DB::table('households')->insert([
            ['household_id' => 'HH-2026-1003', 'household_code' => '1003', 'household_name' => 'Household One', 'member_count' => 1, 'address_id' => 16],
            ['household_id' => 'HH-2026-1004', 'household_code' => '1004', 'household_name' => 'Household Two', 'member_count' => 0, 'address_id' => 17],
        ]);
        DB::table('household_members')->insert([
            'member_id' => 'MEM-1',
            'household_id' => 'HH-2026-1003',
            'first_name' => 'Member',
            'last_name' => 'One',
            'relation' => 'Head',
        ]);
        DB::table('users')->insert([
            ['user_id' => 'USR-1', 'household_id' => 'HH-2026-1003', 'member_id' => 'MEM-1', 'username' => 'account-one'],
            ['user_id' => 'USR-2', 'household_id' => 'HH-2026-1003', 'member_id' => 'WRONG-MEMBER', 'username' => 'account-two'],
            ['user_id' => 'USR-ORPHAN', 'household_id' => 'HH-MISSING', 'member_id' => null, 'username' => 'account-orphan'],
        ]);

        $result = app(HouseholdDirectoryQuery::class)->page(Request::create('/api/v1/households/directory', 'GET'));

        $this->assertSame(2, $result['summary']['active_households']);
        $this->assertSame(1, $result['summary']['households_missing_purok']);
        $this->assertSame(1, $result['summary']['unmatched_household_accounts']);
        $this->assertSame(2, $result['summary']['accounts_with_member_id']);
        $first = collect($result['households']['data'])->firstWhere('household.household_id', 'HH-2026-1003');
        $this->assertSame(7, $first['address']['sitio_id']);
        $this->assertSame(8, $first['address']['registered_sitio_id']);
        $this->assertSame('Sitio Uno', $first['address']['sitio_name']);
        $this->assertSame(29, $first['address']['purok_id']);
        $this->assertSame('Purok 3', $first['address']['purok_name']);
        $this->assertSame('sitio_mismatch', $first['geographic_status']);
        $this->assertSame(1, $result['summary']['household_sitio_purok_mismatches']);
        $this->assertSame('Member One', $first['members'][0]->display_name);
        $this->assertSame('Head', $first['members'][0]->relation);
        $this->assertSame(['matched', 'unmatched'], collect($first['accounts'])->pluck('member_link_status')->all());
        $second = collect($result['households']['data'])->firstWhere('household.household_id', 'HH-2026-1004');
        $this->assertSame('unassigned_purok', $second['geographic_status']);
        $this->assertNull($second['address']['purok_id']);
        $this->assertSame(2, $result['households']['meta']['total']);

        $search = app(HouseholdDirectoryQuery::class)->page(
            Request::create('/api/v1/households/directory', 'GET', ['search' => 'Purok 3']),
        );
        $this->assertSame(1, $search['households']['meta']['total']);
        $this->assertSame('HH-2026-1003', $search['households']['data'][0]['household']['household_id']);
    }
}
