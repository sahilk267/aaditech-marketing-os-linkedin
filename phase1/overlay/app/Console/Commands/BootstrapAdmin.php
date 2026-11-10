<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class BootstrapAdmin extends Command
{
    protected $signature = 'user:bootstrap-admin {email}';
    protected $description = 'Create the first administrator interactively; never takes a command-line password';

    public function handle(): int
    {
        if (User::query()->where('role','admin')->exists()) {
            $this->error('An administrator already exists. Refusing to create another bootstrap administrator.');
            return self::FAILURE;
        }
        $password = $this->secret('New administrator password (min 14 characters)');
        if (!$password || mb_strlen($password) < 14) {
            $this->error('Password too short.');
            return self::FAILURE;
        }
        $name = $this->ask('Display name', 'Owner');
        User::create(['name'=>$name,'email'=>$this->argument('email'), 'password'=>Hash::make($password),
            'role'=>'admin', 'is_active'=>true]);
        $this->info('Administrator created. No public sign-up is enabled.');
        return self::SUCCESS;
    }
}
