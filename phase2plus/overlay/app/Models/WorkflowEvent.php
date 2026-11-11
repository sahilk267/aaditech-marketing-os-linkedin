<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkflowEvent extends Model
{
    protected $table = 'workflow_events';
    protected $fillable = ['event_type', 'aggregate_type', 'aggregate_id', 'payload', 'occurred_at', 'processed_at', 'status'];
    protected function casts(): array { return ['payload' => 'array', 'occurred_at' => 'datetime', 'processed_at' => 'datetime']; }
    
}
