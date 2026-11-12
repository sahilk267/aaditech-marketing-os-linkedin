<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** A credential-safe readiness report. A missing optional connector does not imply a working integration. */
class AadiDoctor extends Command
{
    protected $signature='aadi:doctor {--json : Return machine-readable health indicators}';
    protected $description='Inspect deployed system readiness without revealing credentials';
    public function handle():int
    {
        $checks=[
            'php_version'=>PHP_VERSION_ID>=80300,
            'php_extensions'=>count(array_filter(['pdo_mysql','mbstring','curl','dom','xml','openssl','fileinfo'],fn($e)=>!extension_loaded($e)))===0,
            'app_key'=>is_string(config('app.key'))&&strlen((string)config('app.key'))>=40,
            'production_https'=>app()->environment('production') ? str_starts_with((string)config('app.url'),'https://') : true,
            'storage_writable'=>is_writable(storage_path())&&is_writable(base_path('bootstrap/cache')),
            'strict_free_ai'=>false, // computed from persisted model prices below
            'oauth_linkedin_configured'=>(bool)config('aadi.linkedin.client_id')&&(bool)config('aadi.linkedin.client_secret')&&(bool)config('aadi.linkedin.version'),
            'oauth_facebook_configured'=>(bool)config('aadi.meta.app_id')&&(bool)config('aadi.meta.app_secret')&&(bool)config('aadi.meta.redirect_uri')&&(bool)config('aadi.meta.graph_version'),
            'telegram_configured'=>(bool)config('aadi.telegram.token')&&(bool)config('aadi.telegram.chat_id'),
        ];
        try {
            DB::connection()->select('SELECT 1');
            $checks['database_connected']=true;
            $checks['strict_free_ai']=!Schema::hasTable('ai_models') || !DB::table('ai_models')
                ->where('provider','openrouter')->where('is_enabled',true)
                ->where(function ($q):void {
                    $q->where('is_free',false)->orWhereNull('input_price')->orWhereNull('output_price')
                      ->orWhere('input_price','!=',0)->orWhere('output_price','!=',0);
                })->exists();
            $checks['database_migrated']=Schema::hasTable('brands')&&Schema::hasTable('workflow_jobs')&&Schema::hasTable('users');
            $checks['brands_seeded']=$checks['database_migrated']&&DB::table('brands')->count()>=4;
            $checks['admin_present']=$checks['database_migrated']&&DB::table('users')->where('role','admin')->exists();
            $checks['worker_recent']=$checks['database_migrated']&&Schema::hasTable('worker_nodes')&&
                DB::table('worker_nodes')->where('last_heartbeat_at','>=',now()->subMinutes(5))->exists();
        } catch (\Throwable $e) {
            foreach (['database_connected','database_migrated','brands_seeded','admin_present','worker_recent'] as $key) $checks[$key]=false;
        }
        $required=['php_version','php_extensions','app_key','production_https','storage_writable','database_connected','database_migrated','brands_seeded','admin_present','strict_free_ai'];
        $ready=count(array_filter($required,fn($k)=>empty($checks[$k])))===0;
        if ($this->option('json')) $this->line(json_encode(['core_ready'=>$ready,'checks'=>$checks],JSON_PRETTY_PRINT));
        else {
            foreach ($checks as $key=>$passed) $this->line(($passed?'PASS':'NEEDS_SETUP').' '.$key);
            $this->line($ready?'CORE_READY: true':'CORE_READY: false');
        }
        return $ready ? self::SUCCESS : self::FAILURE;
    }
}
