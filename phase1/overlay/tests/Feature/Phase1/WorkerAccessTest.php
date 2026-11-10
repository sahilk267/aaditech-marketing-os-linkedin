<?php

namespace Tests\Feature\Phase1;

use App\Models\WorkerCredential;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class WorkerAccessTest extends TestCase
{
    use RefreshDatabase;

    private function credential(): array
    {
        $row=WorkerCredential::create(['label'=>'test','token_hash'=>str_repeat('0',64),
            'allowed_capabilities'=>['heartbeat']]);
        $token='amo_'.$row->id.'.'.str_repeat('a',64);
        $row->update(['token_hash'=>hash('sha256',$token)]);
        return [$row,$token];
    }

    private function headers(string $token, ?string $nonce=null): array
    {
        return ['Authorization'=>'Bearer '.$token,
            'X-Worker-Timestamp'=>(string) time(), 'X-Worker-Nonce'=>$nonce ?? Str::random(32)];
    }

    public function test_invalid_or_revoked_tokens_are_rejected(): void
    {
        [$row,$token]=$this->credential();
        $this->withHeaders($this->headers('wrong'))->postJson('/api/v1/worker/heartbeat')->assertUnauthorized();
        $row->update(['revoked_at'=>now()]);
        $this->withHeaders($this->headers($token))->postJson('/api/v1/worker/heartbeat')->assertUnauthorized();
    }

    public function test_registration_and_heartbeat_and_replay_detection(): void
    {
        [, $token]=$this->credential();
        $h=$this->headers($token, str_repeat('n',24));
        $payload=['node_name'=>'desktop-1','platform'=>'windows','worker_version'=>'0.1.0','capabilities'=>['heartbeat']];
        $this->withHeaders($h)->postJson('/api/v1/worker/register',$payload)->assertOk()->assertJsonPath('status','online');
        $this->withHeaders($h)->postJson('/api/v1/worker/register',$payload)->assertStatus(409);
        $this->withHeaders($this->headers($token))->postJson('/api/v1/worker/heartbeat')->assertOk();
        $this->assertDatabaseHas('worker_nodes',['node_name'=>'desktop-1','status'=>'online']);
    }

    public function test_unapproved_capabilities_cannot_be_registered(): void
    {
        [, $token]=$this->credential();
        $payload=['node_name'=>'desktop-1','platform'=>'windows','worker_version'=>'0.1.0','capabilities'=>['publish']];
        $this->withHeaders($this->headers($token))->postJson('/api/v1/worker/register',$payload)
            ->assertUnprocessable()->assertJsonValidationErrors('capabilities');
    }

    public function test_expired_timestamp_is_rejected(): void
    {
        [, $token]=$this->credential();
        $h=$this->headers($token);$h['X-Worker-Timestamp']=(string)(time()-500);
        $this->withHeaders($h)->postJson('/api/v1/worker/heartbeat')->assertUnauthorized();
    }
}
