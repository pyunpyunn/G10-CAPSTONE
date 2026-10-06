<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Presenters\NotificationPresenter;
use App\Services\Web\NotificationSavedView;
use Illuminate\Support\LazyCollection;
use Tests\TestCase;

class NotificationPresentationTest extends TestCase
{
    public function test_feed_notice_contract_keeps_priority_date_read_tone_and_action_url(): void
    {
        $feed=app(NotificationPresenter::class)->feed([
            'outgoing'=>collect([Notification::unguarded(fn () => new Notification(['notif_id'=>4,'urgency_level_id'=>4,'status'=>'sent','channel'=>'sms','message'=>'Alert','created_at'=>now()]))]),
            'household'=>collect(),'dispatch'=>collect(),'requests'=>collect(),'weather'=>collect(),'broadcasts'=>collect(),'audit'=>collect(),
        ]);
        $this->assertSame('notif-4',$feed[0]['id']);
        $this->assertSame('Critical',$feed[0]['priority']);
        $this->assertSame('/broadcast',$feed[0]['action_url']);
        $this->assertSame('red',$feed[0]['tone']);
        $this->assertTrue($feed[0]['read']);
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



