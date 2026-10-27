<?php

namespace Tests\Feature;

use App\Queries\DispatchStatusQuery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Tests\TestCase;

class DispatchStatusCatalogTest extends TestCase
{
    public function test_catalog_contains_unused_statuses_and_hides_the_legacy_alias(): void
    {
        $query = app(DispatchStatusQuery::class);
        $this->assertSame(8, DB::table('dispatch_statuses')->count());
        $this->assertCount(7, $query->options());
        $this->assertSame('DSP-001', $query->options()[0]['status_code']);
        $this->assertSame(8, DB::table('dispatch_statuses')->distinct()->count('status_code'));
        $this->assertContains('returning', array_column($query->options(), 'key'));
        $this->assertNotContains('onscene', array_column($query->options(), 'key'));
        $this->assertNotContains('completed', $query->keys(true));
        $this->assertContains('completed', $query->keys(false));
    }

    public function test_database_changes_drive_options_labels_and_allowed_values(): void
    {
        DB::table('dispatch_statuses')->insert([
            'status_key' => 'awaiting_support', 'status_code' => 'DSP-009', 'label' => 'Awaiting support',
            'is_open' => true, 'is_active' => true, 'allow_create' => true,
            'allow_mobile' => false, 'is_alias' => false, 'sort_order' => 20,
        ]);
        $query = app(DispatchStatusQuery::class);
        $this->assertContains('awaiting_support', array_column($query->options(), 'key'));
        $this->assertSame('Awaiting support', app(\App\Presenters\RescueDispatchPresenter::class)->status('awaiting_support')['label']);
        $createRule = Rule::exists('dispatch_statuses', 'status_key')->where('is_active', true)->where('allow_create', true);
        $mobileRule = Rule::exists('dispatch_statuses', 'status_key')->where('is_active', true)->where('allow_mobile', true);
        $this->assertTrue(Validator::make(['status' => 'awaiting_support'], ['status' => $createRule])->passes());
        $this->assertFalse(Validator::make(['status' => 'awaiting_support'], ['status' => $mobileRule])->passes());
        DB::table('dispatch_statuses')->where('status_key', 'awaiting_support')->update(['is_active' => false]);
        $this->assertNotContains('awaiting_support', array_column($query->options(), 'key'));
        $this->assertFalse(Validator::make(['status' => 'awaiting_support'], ['status' => $createRule])->passes());
        $this->assertFalse(Validator::make(['status' => 'invented_status'], ['status' => $createRule])->passes());
    }
}
