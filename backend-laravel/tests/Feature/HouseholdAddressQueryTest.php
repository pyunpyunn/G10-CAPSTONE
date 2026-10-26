<?php
namespace Tests\Feature;
use App\Queries\HouseholdAddressQuery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
class HouseholdAddressQueryTest extends TestCase
{
    public function test_address_uses_foreign_keys_instead_of_test_text_and_purok_owns_its_sitio(): void
    {
        Schema::create('households', function ($table) { $table->string('household_id'); $table->integer('address_id'); });
        Schema::create('addresses', function ($table) { $table->integer('address_id'); $table->integer('purok_id')->nullable(); $table->integer('sitio_id')->nullable(); $table->string('full_address'); });
        Schema::create('puroks', function ($table) { $table->integer('purok_id'); $table->integer('sitio_id'); $table->string('purok_name'); });
        Schema::create('sitios', function ($table) { $table->integer('sitio_id'); $table->string('sitio_name'); });
        DB::table('households')->insert(['household_id' => 'HH-1', 'address_id' => 16]);
        DB::table('addresses')->insert(['address_id' => 16, 'purok_id' => 29, 'sitio_id' => 7, 'full_address' => 'Temporary test household']);
        DB::table('puroks')->insert(['purok_id' => 29, 'sitio_id' => 8, 'purok_name' => 'Purok 3']);
        DB::table('sitios')->insert([['sitio_id' => 7, 'sitio_name' => 'Old sitio'], ['sitio_id' => 8, 'sitio_name' => 'Paglaum']]);
        $addresses = app(HouseholdAddressQuery::class)->forHouseholds(['HH-1']);
        $this->assertSame('Purok 3, Paglaum', $addresses->get('HH-1'));
        DB::table('addresses')->update(['purok_id' => null]);
        $this->assertSame('Old sitio', app(HouseholdAddressQuery::class)->forHouseholds(['HH-1'])->get('HH-1'));
    }
}
