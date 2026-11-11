<?php
namespace Tests\Feature\Phase1;

use App\Models\Brand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeploymentReadinessTest extends TestCase
{
    use RefreshDatabase;
    public function test_database_seeder_is_idempotent():void
    {
        $this->artisan('db:seed',['--class'=>'Database\\Seeders\\BrandSeeder'])->assertExitCode(0);
        $this->artisan('db:seed',['--class'=>'Database\\Seeders\\BrandSeeder'])->assertExitCode(0);
        $this->assertSame(4,Brand::query()->count());
    }
    public function test_worker_endpoints_have_authentication_middleware():void
    {
        $this->postJson('/api/v1/worker/jobs/claim')->assertUnauthorized();
        $this->postJson('/api/v1/worker/tick')->assertUnauthorized();
    }
}
