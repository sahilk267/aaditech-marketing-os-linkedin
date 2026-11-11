<?php

namespace App\Console\Commands;

use App\Models\NotificationLog;
use App\Models\WorkflowEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class AadiOutbox extends Command
{
    protected $signature='aadi:outbox {--limit=25}';
    protected $description='Deliver bounded Telegram operational alerts from a durable DB outbox';
    public function handle(): int
    {
        $token=(string)config('aadi.telegram.token','');
        $chat=(string)config('aadi.telegram.chat_id','');
        $events=WorkflowEvent::query()->where('status','pending')->orderBy('id')->limit(min(50,(int)$this->option('limit')))->get();
        $notify=['worker.heartbeat_missed','workflow.dead_letter','workflow.failed'];
        foreach ($events as $event) {
            // No confidential job data is included in messages.
            if (!in_array($event->event_type,$notify,true)) {
                $event->status='processed';$event->processed_at=now();$event->save();continue;
            }
            if (!$token||!$chat) continue; // Leave pending for later retry without claim of delivery.
            try {
                $msg='Aadi Marketing OS alert: '. $event->event_type.' #'.$event->aggregate_id;
                $response=Http::timeout(10)->asForm()->post('https://api.telegram.org/bot'.$token.'/sendMessage',
                    ['chat_id'=>$chat,'text'=>$msg]);
                if (!$response->successful()||$response->json('ok')!==true) throw new \RuntimeException('TELEGRAM_REJECTED');
                DB::transaction(function () use($event):void {
                    $event->status='processed';$event->processed_at=now();$event->save();
                    NotificationLog::query()->create(['channel'=>'telegram','event_type'=>$event->event_type,
                        'destination_masked'=>'configured','status'=>'sent','sent_at'=>now()]);
                });
            } catch (\Throwable $e) {
                $this->warn('Telegram not acknowledged for event '.$event->id);
                // Never include credentials or raw transport errors in app logs.
            }
        }
        return self::SUCCESS;
    }
}
