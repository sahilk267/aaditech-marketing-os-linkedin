<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContentItem extends Model
{
    protected $table = 'content_items';
    protected $fillable = ['brand_id', 'campaign_id', 'title', 'topic', 'content_type', 'language', 'hook', 'body', 'cta', 'hashtags', 'source_refs', 'claim_evidence', 'platform_variants', 'status', 'quality_score', 'content_hash', 'ai_provider', 'ai_model', 'prompt_version', 'risk_level', 'approved_by', 'approved_at'];
    protected function casts(): array { return ['hashtags' => 'array', 'source_refs' => 'array', 'claim_evidence' => 'array', 'platform_variants' => 'array', 'approved_at' => 'datetime']; }
    
    public function brand(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(Brand::class); }

}
