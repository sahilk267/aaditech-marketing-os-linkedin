<?php

namespace App\Console\Commands;

use App\Models\NotificationLog;
use App\Models\WorkflowEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/** At-least-once operational notifications. Safe to run concurrently; no secrets in messages. */
class AadiOutbox extends Command
{
    protected $signature='aadi:outbox {--limit=25}';
    protected $description='Deliver bounded operational Telegram alerts from a durable DB outbox';

    public const ALERT_EVENTS = [
        'worker.heartbeat_missed', 'worker.online', 'workflow.dead_letter',
        'workflow.failed', 'workflow.backlog_high', 'publication.succeeded',
        'publication.needs_reconciliation', 'publication.manual_ready', 'oauth.expiring', 'oauth.revoked',
        'provider.quota_exhausted', 'ai.fallback', 'ai.unavailable',
        'content.high_risk_held', 'research.stale', 'report.daily_ready',
    ];

    public function handle(): int
    {
        $token=(string)config('aadi.telegram.token','');
        $chat=(string)config('aadi.telegram.chat_id','');
        $limit=min(50,max(1,(int)$this->option('limit')));
        $events=WorkflowEvent::query()->where('status','pending')->orderBy('id')->limit($limit)->get();
        foreach ($events as $event) {
            $notify=in_array($event->event_type,self::ALERT_EVENTS,true);
            if ($notify && (!$token || !$chat)) continue; // No accidental silent success.

            // Atomic claim avoids duplicate delivery when cron and the desktop tick overlap.
            $claimed=WorkflowEvent::query()->whereKey($event->id)
                ->where('status','pending')->update(['status'=>'sending']);
            if ($claimed !== 1) continue;
            if (!$notify) {
                WorkflowEvent::query()->whereKey($event->id)->where('status','sending')
                    ->update(['status'=>'processed','processed_at'=>now()]);
                continue;
            }
            try {
                // No user/source/lead details, tokens, external URLs or arbitrary payload text.
                $message='Aadi Marketing OS alert: '.$event->event_type.' #'.$event->aggregate_id;
                $response=Http::timeout(10)->asForm()->post('https://api.telegram.org/bot'.$token.'/sendMessage',
                    ['chat_id'=>$chat,'text'=>$message]);
                if (!$response->successful() || $response->json('ok')!==true) {
                    throw new \RuntimeException('TELEGRAM_REJECTED');
                }
                DB::transaction(function () use($event):void {
                    WorkflowEvent::query()->whereKey($event->id)->where('status','sending')
                        ->update(['status'=>'processed','processed_at'=>now()]);
                    NotificationLog::query()->create(['channel'=>'telegram','event_type'=>$event->event_type,
                        'destination_masked'=>'configured','status'=>'sent','sent_at'=>now()]);
                });
            } catch (\Throwable $e) {
                WorkflowEvent::query()->whereKey($event->id)->where('status','sending')
                    ->update(['status'=>'pending']);
                $this->warn('Telegram not acknowledged for event #'.$event->id);
                // Never log the token-bearing endpoint or arbitrary provider error.
            }
        }
        return self::SUCCESS;
    }
}
