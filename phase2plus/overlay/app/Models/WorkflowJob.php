<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkflowJob extends Model
{
    protected $table = 'workflow_jobs';
    protected $fillable = ['brand_id', 'job_type', 'payload', 'status', 'priority', 'scheduled_at', 'started_at', 'completed_at', 'lease_owner', 'lease_expires_at', 'attempt_count', 'max_attempts', 'idempotency_key', 'last_error', 'result'];
    protected function casts(): array { return ['payload' => 'array', 'result' => 'array', 'scheduled_at' => 'datetime', 'started_at' => 'datetime', 'completed_at' => 'datetime', 'lease_expires_at' => 'datetime']; }
    
}
