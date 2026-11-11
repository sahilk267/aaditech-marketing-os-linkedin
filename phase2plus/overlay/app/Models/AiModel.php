<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiModel extends Model
{
    protected $table = 'ai_models';
    protected $fillable = ['provider', 'model_id', 'is_free', 'input_price', 'output_price', 'capabilities', 'context_limit', 'health_status', 'is_enabled', 'priority', 'last_verified_at'];
    protected function casts(): array { return ['capabilities' => 'array', 'last_verified_at' => 'datetime', 'is_free' => 'boolean', 'is_enabled' => 'boolean', 'input_price' => 'decimal:10', 'output_price' => 'decimal:10']; }
    
}
