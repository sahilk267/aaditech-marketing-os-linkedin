<?php

namespace App\Domain\AI;

use App\Models\AiModel;
use Illuminate\Support\Facades\DB;

class ModelCatalog
{
    /** Accept provider metadata only; false/unknown pricing disables the model. */
    public function sync(array $rows): array
    {
        $enabled=0;$disabled=0;
        DB::transaction(function () use ($rows,&$enabled,&$disabled): void {
            AiModel::query()->where('provider','openrouter')->update(['is_enabled'=>false]);
            foreach ($rows as $m) {
                if (!is_array($m)||!is_string($m['id']??null)||strlen($m['id'])>180) continue;
                $free=FreeModelGuard::catalogEntryIsFree($m);
                AiModel::query()->updateOrCreate(['provider'=>'openrouter','model_id'=>$m['id']], [
                    'is_free'=>$free,'input_price'=>$free?0:null,'output_price'=>$free?0:null,
                    'capabilities'=>['text'], 'context_limit'=>is_int($m['context_length']??null)?$m['context_length']:null,
                    'health_status'=>'unknown','is_enabled'=>$free,'last_verified_at'=>now(),
                ]);
                if ($free) $enabled++;else $disabled++;
            }
        });
        return ['verified_free'=>$enabled,'rejected_or_paid'=>$disabled];
    }
}
