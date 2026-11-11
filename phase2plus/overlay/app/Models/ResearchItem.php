<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResearchItem extends Model
{
    protected $table = 'research_items';
    protected $fillable = ['brand_id', 'source_id', 'title', 'summary', 'extracted_facts', 'published_at', 'fetched_at', 'content_hash', 'freshness_status', 'verification_status'];
    protected function casts(): array { return ['extracted_facts' => 'array', 'published_at' => 'datetime', 'fetched_at' => 'datetime']; }
    
}
