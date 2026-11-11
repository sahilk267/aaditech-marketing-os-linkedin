<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnalyticsSnapshot extends Model
{
    protected $table = 'analytics_snapshots';
    protected $fillable = ['publishing_job_id', 'platform', 'metric_name', 'metric_value', 'metric_definition', 'captured_at', 'source'];
    protected function casts(): array { return ['captured_at' => 'datetime', 'metric_value' => 'decimal:6']; }
    
}
