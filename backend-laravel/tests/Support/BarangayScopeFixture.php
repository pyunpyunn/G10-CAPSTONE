<?php

namespace Tests\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Registered geography for contract tests; household eligibility remains enforced. */
class BarangayScopeFixture
{
    public static function seed(array $householdIds = []): void
    {
        $definitions = [
            'provinces' => function (Blueprint $t): void {
                $t->integer('province_id')->primary();
                $t->string('province_name');
            },
            'cities' => function (Blueprint $t): void {
                $t->integer('city_id')->primary();
                $t->integer('province_id');
                $t->string('city_name');
            },
            'barangays' => function (Blueprint $t): void {
                $t->integer('barangay_id')->primary();
                $t->integer('city_id');
                $t->string('barangay_code');
                $t->string('barangay_name');
            },
            'sitios' => function (Blueprint $t): void {
                $t->integer('sitio_id')->primary();
                $t->integer('barangay_id');
                $t->string('sitio_name');
            },
            'puroks' => function (Blueprint $t): void {
                $t->integer('purok_id')->primary();
                $t->integer('sitio_id')->nullable();
                $t->string('purok_name');
            },
            'addresses' => function (Blueprint $t): void {
                $t->integer('address_id')->primary();
                $t->integer('purok_id')->nullable();
                $t->string('purok_sitio')->nullable();
            },
            'households' => function (Blueprint $t): void {
                $t->string('household_id')->primary();
                $t->integer('address_id')->nullable();
                $t->string('household_name')->nullable();
                $t->string('household_code')->nullable();
                $t->softDeletes();
            },
        ];
        foreach ($definitions as $table => $definition) {
            if (! Schema::hasTable($table)) {
                Schema::create($table, $definition);
            }
        }
        if (! Schema::hasColumn('puroks', 'sitio_id')) {
            Schema::table('puroks', fn (Blueprint $t) => $t->integer('sitio_id')->nullable());
        }
        if (! Schema::hasColumn('households', 'address_id')) {
            Schema::table('households', fn (Blueprint $t) => $t->integer('address_id')->nullable());
        }
        DB::table('provinces')->insertOrIgnore(['province_id' => 1, 'province_name' => 'Cebu']);
        DB::table('cities')->insertOrIgnore(['city_id' => 1, 'province_id' => 1, 'city_name' => 'Cebu City']);
        DB::table('barangays')->insertOrIgnore(['barangay_id' => 1, 'city_id' => 1, 'barangay_code' => '0730600049', 'barangay_name' => 'Mambaling']);
        DB::table('sitios')->insertOrIgnore(['sitio_id' => 1, 'barangay_id' => 1, 'sitio_name' => 'Registered test sitio']);
        DB::table('puroks')->insertOrIgnore(['purok_id' => 1, 'sitio_id' => 1, 'purok_name' => 'Registered test purok']);
        DB::table('puroks')->whereNull('sitio_id')->update(['sitio_id' => 1]);
        foreach ($householdIds as $id) {
            DB::table('households')->insertOrIgnore(['household_id' => $id]);
        }
        foreach (DB::table('households')->whereNull('address_id')->pluck('household_id') as $id) {
            $addressId = ((int) DB::table('addresses')->max('address_id')) + 1;
            DB::table('addresses')->insert(['address_id' => $addressId, 'purok_id' => 1]);
            DB::table('households')->where('household_id', $id)->update(['address_id' => $addressId]);
        }
    }
}
