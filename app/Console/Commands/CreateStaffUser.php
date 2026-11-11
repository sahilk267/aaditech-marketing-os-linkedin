<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateStaffUser extends Command
{
    protected $signature = 'user:create-staff {email} {--role=analyst}';
    protected $description = 'Interactively create editor, analyst or operator account; owner/admin uses separate controlled provisioning';

    public function handle(): int
    {
        $role = $this->option('role');
        if (!in_array($role, ['editor','analyst','operator'], true)) {
            $this->error('Role must be editor, analyst or operator.');
            return self::FAILURE;
        }
        if (User::query()->where('email', $this->argument('email'))->exists()) {
            $this->error('Account already exists.');
            return self::FAILURE;
        }
        $password = $this->secret('Initial password (min 14 characters)');
        if (!$password || mb_strlen($password) < 14) {
            $this->error('Password too short.');
            return self::FAILURE;
        }
        $name = $this->ask('Display name', 'Staff member');
        User::create(['name'=>$name,'email'=>$this->argument('email'),
            'password'=>Hash::make($password),'role'=>$role,'is_active'=>true]);
        $this->info('Staff account created. Assign brands separately before access is granted.');
        return self::SUCCESS;
    }
}
