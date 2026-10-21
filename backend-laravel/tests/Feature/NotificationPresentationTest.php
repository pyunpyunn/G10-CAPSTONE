<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Presenters\NotificationPresenter;
use App\Services\Web\NotificationSavedView;
use Illuminate\Support\LazyCollection;
use Tests\TestCase;

class NotificationPresentationTest extends TestCase
{
    private function sources(array $extra = []): array
    {
        return array_merge(['advisories'=>collect(),'dispatch'=>collect(),'requests'=>collect(),'broadcasts'=>collect(),'audit'=>collect()], $extra);
    }

    public function test_undated_dispatch_logs_never_turn_into_current_notifications(): void
    {
        $audit = \App\Models\AuditLog::unguarded(fn () => new \App\Models\AuditLog([
            'audit_log_id'=>293,'module'=>'rescue_dispatch','action'=>'create_dispatch',
            'reference_table'=>'responder_assignments','reference_id'=>'1','created_at'=>null,
        ]));
        $sources = $this->sources(['audit'=>collect([$audit])]);
        $this->assertCount(0, app(NotificationPresenter::class)->feed($sources));
        $this->travel(20)->seconds();
        $this->assertCount(0, app(NotificationPresenter::class)->feed($sources));
    }

    public function test_assignment_rows_do_not_generate_assignment_push_notices(): void
    {
        $assignment = new \App\Models\ResponderAssignment(['assignment_id'=>1,'status'=>'dispatched','assigned_at'=>now(),'updated_at'=>now()]);
        $this->assertCount(0, app(NotificationPresenter::class)->feed($this->sources(['dispatch'=>collect([$assignment])])));
    }

    public function test_real_dispatch_actions_have_stable_ids_and_the_right_category(): void
    {
        $audits = collect([
            \App\Models\AuditLog::unguarded(fn () => new \App\Models\AuditLog(['audit_log_id'=>1,'module'=>'rescue_dispatch','action'=>'create_dispatch','reference_table'=>'responder_assignments','reference_id'=>'7','new_values'=>['status'=>'dispatched'],'created_at'=>now()])),
            \App\Models\AuditLog::unguarded(fn () => new \App\Models\AuditLog(['audit_log_id'=>2,'module'=>'rescuer_mobile','action'=>'mobile_update_assignment','reference_table'=>'responder_assignments','reference_id'=>'7','new_values'=>['status'=>'en_route','previous_status'=>'accepted'],'created_at'=>now()])),
        ]);
        $presenter = app(NotificationPresenter::class);
        $feed = $presenter->feed($this->sources(['audit'=>$audits]));
        $this->assertSame('Dispatch log', $feed->firstWhere('id','audit-1')['type']);
        $this->assertSame('Dispatch status', $feed->firstWhere('id','audit-2')['type']);
        $this->travel(20)->seconds();
        $this->assertSame($feed->all(), $presenter->feed($this->sources(['audit'=>$audits]))->all());
    }

    public function test_popover_contains_the_entire_feed_while_the_page_is_paginated(): void
    {
        $rows = collect(range(1, 8))->map(fn ($id) => (object)['id'=>$id,'source'=>'PAGASA','title'=>'Official advisory '.$id,'body'=>'Synchronized','source_url'=>'https://publicalert.pagasa.dost.gov.ph/feeds/','created_at'=>now()]);
        $presenter = app(NotificationPresenter::class);
        $items = $presenter->feed($this->sources(['advisories'=>$rows]));
        $data = $presenter->workspace(['items'=>$items,'filtered'=>$items,'status'=>'all','page'=>1]);
        $this->assertCount(8, $data['preview']);
        $this->assertCount(5, $data['notifications']['data']);
        $this->assertSame(8, $data['notifications']['total']);
    }

    public function test_new_dispatch_audit_writes_preserve_the_actual_event_time(): void
    {
        \Illuminate\Support\Facades\Schema::create('audit_logs', function (\Illuminate\Database\Schema\Blueprint $table) {
            $table->id('audit_log_id');
            foreach (['module','action','reference_table','reference_id'] as $field) $table->string($field);
            $table->timestamp('created_at')->nullable();
        });
        $at = now()->startOfSecond();
        $audit = \App\Models\AuditLog::query()->create(['module'=>'rescue_dispatch','action'=>'create_dispatch',
            'reference_table'=>'responder_assignments','reference_id'=>'7','created_at'=>$at]);
        $audit->refresh();
        $this->assertNotNull($audit->created_at);
        $feed = app(NotificationPresenter::class)->feed($this->sources(['audit'=>collect([$audit])]));
        $this->assertCount(1, $feed);
        $this->assertSame($at->timestamp, $feed[0]['sort_time']);
    }

    public function test_undated_legacy_clear_all_does_not_hide_new_events(): void
    {
        $view = app(NotificationSavedView::class);
        $state = $view->state([(object)['action'=>'clear_all','new_values'=>[],'created_at'=>null]]);
        $this->assertCount(1, $view->apply(collect([['id'=>'new-event','sort_time'=>now()->timestamp,'read'=>false]]), $state));
    }

    public function test_feed_notice_contract_keeps_priority_date_read_tone_and_action_url(): void
    {
        $feed=app(NotificationPresenter::class)->feed([
            'advisories'=>collect([(object)['id'=>4,'source'=>'PAGASA','title'=>'Official warning','body'=>'Alert','source_url'=>'https://publicalert.pagasa.dost.gov.ph/feeds/','created_at'=>now()]]),
            'household'=>collect(),'dispatch'=>collect(),'requests'=>collect(),'weather'=>collect(),'broadcasts'=>collect(),'audit'=>collect(),
        ]);
        $this->assertSame('advisory-4',$feed[0]['id']);
        $this->assertSame('High',$feed[0]['priority']);
        $this->assertSame('/weather',$feed[0]['action_url']);
        $this->assertSame('amber',$feed[0]['tone']);
        $this->assertFalse($feed[0]['read']);
    }

    public function test_saved_view_applies_read_and_hide_ids_without_mutating_sources(): void
    {
        $view=app(NotificationSavedView::class);
        $items=collect([
            ['id'=>'a','sort_time'=>100,'read'=>false],
            ['id'=>'b','sort_time'=>200,'read'=>false],
        ]);
        $state=$view->state(collect([(object)['action'=>'mark_read','new_values'=>'{"selected_ids":["a"]}','created_at'=>now()],
            (object)['action'=>'delete_selected','new_values'=>'{"selected_ids":["b"]}','created_at'=>now()]]));
        $visible=$view->apply($items,$state);
        $this->assertCount(1,$visible);
        $this->assertSame('a',$visible[0]['id']);
        $this->assertTrue($visible[0]['read']);
        $this->assertFalse($items[0]['read']);
    }

    public function test_normal_weather_and_processed_requests_are_excluded(): void
    {
        $savedAt = now();
        $feed = app(NotificationPresenter::class)->feed([
            'outgoing' => collect(), 'household' => collect(), 'dispatch' => collect(),
            'requests' => collect([\App\Models\ResourceRequest::unguarded(fn () => new \App\Models\ResourceRequest([
                'request_id' => 7, 'request_source' => 'external', 'item_name' => 'Water',
                'validation_status' => 'approved', 'created_at' => $savedAt->copy()->subMinute(),
            ]))]),
            'weather' => collect([\App\Models\WeatherLog::unguarded(fn () => new \App\Models\WeatherLog([
                'weather_log_id' => 8, 'condition_name' => 'Clear', 'source_name' => 'Weather API',
                'observed_at' => $savedAt->copy()->subHours(3), 'created_at' => $savedAt,
            ]))]),
            'broadcasts' => collect(), 'audit' => collect(),
        ]);
        $this->assertCount(0, $feed);
    }

    public function test_saved_view_only_retains_ids_in_the_current_feed_while_scanning_history(): void
    {
        $rows = LazyCollection::make(function () {
            yield (object) ['action' => 'mark_read', 'new_values' => ['selected_ids' => ['old', 'visible']], 'created_at' => '2026-01-01 00:00:00'];
            yield (object) ['action' => 'delete_selected', 'new_values' => ['selected_ids' => ['old-hidden']], 'created_at' => '2026-01-02 00:00:00'];
        });

        $state = app(NotificationSavedView::class)->state($rows, ['visible']);

        $this->assertSame(['visible'], $state['read_ids']);
        $this->assertSame([], $state['hidden_ids']);
    }
}



