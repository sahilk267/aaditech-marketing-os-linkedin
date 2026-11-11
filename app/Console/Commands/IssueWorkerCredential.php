<?php

namespace App\Console\Commands;

use App\Models\WorkerCredential;
use Illuminate\Console\Command;

class IssueWorkerCredential extends Command
{
    protected $signature = 'worker:issue-credential {label} {--capability=* : Approved job capabilities}';
    protected $description = 'Provision a revocable, once-displayed worker API credential';

    public function handle(): int
    {
        $allowed = array_values(array_unique($this->option('capability') ?: ['heartbeat']));
        if (count($allowed) > 24 || array_filter($allowed, fn ($item) => !preg_match('/^[a-z][a-z0-9._-]{0,63}$/', $item))) {
            $this->error('Invalid capability; use up to 24 lower-case capability names.');
            return self::FAILURE;
        }
        $secret = bin2hex(random_bytes(32));
        // Save hash only; do not log the raw credential. CLI displays exactly once.
        $credential = WorkerCredential::create([
            'label'=>$this->argument('label'),
            'token_hash'=>str_repeat('0',64),
            'allowed_capabilities'=>$allowed,
        ]);
        $token='amo_'.$credential->getKey().'.'.$secret;
        $credential->forceFill(['token_hash'=>hash('sha256',$token)])->save();
        $this->warn('Store this credential securely now. It cannot be retrieved later:');
        $this->line($token);
        return self::SUCCESS;
    }
}
