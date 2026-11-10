<?php

namespace Tests\Feature\Phase1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_dashboard_is_protected(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_disabled_account_cannot_log_in(): void
    {
        $user = User::factory()->create(['role'=>'analyst','is_active'=>false,'password'=>'Test-Secure-Password-123']);
        $this->post('/login', ['email'=>$user->email,'password'=>'Test-Secure-Password-123'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_regenerates_session_and_dashboard_loads(): void
    {
        $user = User::factory()->create(['role'=>'admin','is_active'=>true,'password'=>'Test-Secure-Password-123']);
        $this->post('/login', ['email'=>$user->email,'password'=>'Test-Secure-Password-123'])
            ->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
        $this->get('/dashboard')->assertOk();
    }
}
