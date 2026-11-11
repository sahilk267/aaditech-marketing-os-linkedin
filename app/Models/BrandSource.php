<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BrandSource extends Model
{
    protected $table = 'brand_sources';
    protected $fillable = ['brand_id', 'source_url', 'canonical_hash', 'source_type', 'trust_tier', 'allowed', 'robots_checked_at', 'last_fetched_at', 'last_status'];
    protected function casts(): array { return ['allowed' => 'boolean', 'robots_checked_at' => 'datetime', 'last_fetched_at' => 'datetime']; }
    
}
