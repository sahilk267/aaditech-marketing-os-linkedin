<?php

namespace Tests\Feature\Phase2Plus;

use App\Models\NotificationLog;
use App\Models\WorkflowEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OperationalOutboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_telegram_config_does_not_consume_alert(): void
    {
        config()->set('aadi.telegram.token',null);
        config()->set('aadi.telegram.chat_id',null);
        $event=$this->event('workflow.dead_letter',['private_record'=>'do-not-send']);
        Http::fake();
        $this->artisan('aadi:outbox')->assertExitCode(0);
        $this->assertDatabaseHas('workflow_events',['id'=>$event->id,'status'=>'pending']);
        $this->assertSame(0,NotificationLog::query()->count());
        Http::assertNothingSent();
    }

    public function test_telegram_sends_allowlisted_redacted_alert_and_marks_processed():void
    {
        config()->set('aadi.telegram.token','test-disposable-token');
        config()->set('aadi.telegram.chat_id','test-disposable-chat');
        $event=$this->event('publication.needs_reconciliation',['private_record'=>'do-not-send']);
        Http::fake(['*'=>Http::response(['ok'=>true],200)]);
        $this->artisan('aadi:outbox')->assertExitCode(0);
        $this->assertDatabaseHas('workflow_events',['id'=>$event->id,'status'=>'processed']);
        $this->assertDatabaseHas('notification_logs',['event_type'=>$event->event_type,'status'=>'sent']);
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => !str_contains($request['text'],'do-not-send')
            && str_contains($request['text'],'publication.needs_reconciliation'));
        $this->artisan('aadi:outbox')->assertExitCode(0);
        Http::assertSentCount(1);
    }

    public function test_transport_failure_leaves_event_for_retry():void
    {
        config()->set('aadi.telegram.token','test-disposable-token');
        config()->set('aadi.telegram.chat_id','test-disposable-chat');
        $event=$this->event('worker.heartbeat_missed');
        Http::fake(['*'=>Http::response(['ok'=>false],429)]);
        $this->artisan('aadi:outbox')->assertExitCode(0);
        $this->assertDatabaseHas('workflow_events',['id'=>$event->id,'status'=>'pending']);
        $this->assertSame(0,NotificationLog::query()->count());
    }

    private function event(string $type,array $payload=[]):WorkflowEvent
    {
        return WorkflowEvent::query()->create(['event_type'=>$type,
            'aggregate_type'=>'test','aggregate_id'=>42,'payload'=>$payload,
            'occurred_at'=>now(),'status'=>'pending']);
    }
}
