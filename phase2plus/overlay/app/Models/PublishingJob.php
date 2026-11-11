<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PublishingJob extends Model
{
    protected $table = 'publishing_jobs';
    protected $fillable = ['brand_id', 'content_item_id', 'social_account_id', 'platform', 'scheduled_at', 'status', 'idempotency_key', 'attempt_count', 'platform_post_id', 'permalink', 'published_at', 'last_error', 'request_metadata', 'response_metadata'];
    protected function casts(): array { return ['scheduled_at' => 'datetime', 'published_at' => 'datetime', 'request_metadata' => 'array', 'response_metadata' => 'array']; }
    public function contentItem(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(ContentItem::class); }
    public function socialAccount(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(SocialAccount::class); }
}
