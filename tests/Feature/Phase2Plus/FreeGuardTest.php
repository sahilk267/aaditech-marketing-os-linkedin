<?php

namespace Tests\Feature\Phase2Plus;

use App\Domain\AI\FreeModelGuard;
use App\Models\AiModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FreeGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_unknown_or_nonzero_prices_are_rejected(): void
    {
        $this->assertFalse(FreeModelGuard::catalogEntryIsFree(['id'=>'paid','pricing'=>['prompt'=>'0.0001','completion'=>'0']]));
        $this->assertFalse(FreeModelGuard::catalogEntryIsFree(['id'=>'unknown']));
        $this->assertTrue(FreeModelGuard::catalogEntryIsFree(['id'=>'model:free','pricing'=>['prompt'=>'0','completion'=>'0.0']]));
    }

    public function test_paid_model_is_never_selected(): void
    {
        AiModel::create(['provider'=>'openrouter','model_id'=>'paid/model','is_free'=>false,'is_enabled'=>true,
            'input_price'=>'0.1','output_price'=>'0.1','capabilities'=>['text'],'last_verified_at'=>now()]);
        $this->assertNull(app(FreeModelGuard::class)->choose('text'));
        AiModel::create(['provider'=>'openrouter','model_id'=>'free/model','is_free'=>true,'is_enabled'=>true,
            'input_price'=>'0','output_price'=>'0','capabilities'=>['text'],'last_verified_at'=>now()]);
        $this->assertSame('free/model',app(FreeModelGuard::class)->choose('text')->model_id);
    }
}
