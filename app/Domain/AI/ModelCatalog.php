<?php

namespace App\Domain\AI;

use App\Models\AiModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

/** Server-owned OpenRouter catalog. The worker cannot self-certify a paid model as free. */
class ModelCatalog
{
    public function syncFromProvider(): array
    {
        try {
            $response=Http::timeout(25)->acceptJson()->get('https://openrouter.ai/api/v1/models');
            if (!$response->successful() || !is_array($response->json('data'))) {
                throw new \RuntimeException('Provider catalog invalid');
            }
            $rows=$response->json('data');
            if (count($rows)>5000) throw new \RuntimeException('Provider catalog too large');
        } catch (\Throwable $e) {
            // Do not clear the previous inventory on network failure, but selection uses freshness gate.
            throw ValidationException::withMessages(['catalog'=>'Official provider catalog could not be verified']);
        }
        return $this->sync($rows);
    }

    /** Accept only canonical entries obtained from the provider, not submitted by workers. */
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
