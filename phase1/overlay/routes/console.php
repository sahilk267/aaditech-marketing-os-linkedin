<?php

use Illuminate\Support\Facades\Schedule;

// A once-daily database cleanup, when hosting cron is eventually verified.
Schedule::call(function (): void {
    \Illuminate\Support\Facades\DB::table('worker_request_nonces')
        ->where('created_at', '<', now()->subDay())->delete();
})->daily();
