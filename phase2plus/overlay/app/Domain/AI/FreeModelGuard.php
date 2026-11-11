<?php

namespace App\Domain\AI;

use App\Models\AiModel;
use Illuminate\Validation\ValidationException;

class FreeModelGuard
{
    public static function catalogEntryIsFree(array $m): bool
    {
        $pricing=$m['pricing']??null;
        if (!is_array($pricing) || !array_key_exists('prompt',$pricing) || !array_key_exists('completion',$pricing)) return false;
        foreach (['prompt','completion'] as $key) {
            $value=$pricing[$key];
            if ((!is_string($value)&&!is_int($value)&&!is_float($value)) || !preg_match('/^0+(?:\.0+)?$/D', (string)$value)) return false;
        }
        return is_string($m['id']??null) && strlen($m['id'])<=180;
    }

    public static function assertAllowed(AiModel $model): void
    {
        if ($model->provider!=='openrouter' || !$model->is_enabled || !$model->is_free ||
            $model->input_price===null || $model->output_price===null ||
            (float)$model->input_price!==0.0 || (float)$model->output_price!==0.0 ||
            !$model->last_verified_at || $model->last_verified_at->lt(now()->subDay())) {
            throw ValidationException::withMessages(['model'=>'Paid, unknown, stale or disabled AI model rejected']);
        }
    }

    public function choose(string $capability): ?AiModel
    {
        $candidates=AiModel::query()->where('provider','openrouter')->where('is_enabled',true)->where('is_free',true)
            ->where('input_price',0)->where('output_price',0)->where('last_verified_at','>=',now()->subDay())
            ->orderBy('priority')->get();
        foreach ($candidates as $model) {
            if (in_array($capability, $model->capabilities??[],true)) { self::assertAllowed($model);return $model; }
        }
        return null;
    }
}
