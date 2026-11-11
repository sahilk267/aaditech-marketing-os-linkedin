<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiUsage extends Model
{
    protected $table = 'ai_usage';
    protected $fillable = ['provider', 'model_id', 'task_type', 'request_count', 'input_tokens', 'output_tokens', 'estimated_cost', 'status'];
    protected function casts(): array { return ['estimated_cost' => 'decimal:10']; }
    
}
