<?php

namespace Tests\Feature;

use App\Presenters\RescuerHouseholdStatusPresenter;
use Tests\TestCase;

class RescuerHouseholdStatusPresenterTest extends TestCase
{
    public function test_status_option_and_summary_contracts_keep_the_existing_keys_and_defaults(): void
    {
        $presenter = app(RescuerHouseholdStatusPresenter::class);

        $this->assertSame([
            ['key' => 'safe', 'label' => 'Safe'],
            ['key' => 'evacuated', 'label' => 'Evacuated'],
            ['key' => 'unsafe', 'label' => 'Unsafe'],
        ], $presenter->defaultOptions());

        $options = collect([
            ['status_id' => 4, 'key' => 'unsafe', 'label' => 'Unsafe'],
            ['status_id' => null, 'key' => 'other', 'label' => 'Other'],
        ]);

        $this->assertSame(['rows' => [
            ['key' => 'unsafe', 'label' => 'Unsafe', 'status_id' => 4, 'count' => 2],
            ['key' => 'other', 'label' => 'Other', 'status_id' => null, 'count' => 0],
        ]], $presenter->summaryRows($options, ['unsafe' => 2]));
    }
}


