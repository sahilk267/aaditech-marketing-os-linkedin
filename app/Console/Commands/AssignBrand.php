<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AssignBrand extends Command
{
    protected $signature = 'user:assign-brand {email} {brand_slug}';
    protected $description = 'Grant a pre-existing user explicit access to one brand';

    public function handle(): int
    {
        $user = User::query()->where('email', $this->argument('email'))->first();
        $brand = Brand::query()->where('slug', $this->argument('brand_slug'))->first();
        if (!$user || !$brand || !$user->is_active) {
            $this->error('Active user or brand not found.');
            return self::FAILURE;
        }
        DB::transaction(function () use ($user, $brand): void {
            $before = $user->brands()->whereKey($brand->getKey())->exists();
            $user->brands()->syncWithoutDetaching([$brand->getKey()]);
            if (!$before) DB::table('audit_logs')->insert([
                'user_id'=>null,'actor_type'=>'cli','action'=>'brand.access_granted',
                'resource_type'=>'brand','resource_id'=>(string)$brand->getKey(),
                'metadata'=>json_encode(['user_id'=>$user->getKey()]),'created_at'=>now(),
            ]);
        });
        $this->info('Brand access granted.');
        return self::SUCCESS;
    }
}
