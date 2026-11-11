<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContentReview extends Model
{
    protected $table = 'content_reviews';
    protected $fillable = ['content_item_id', 'review_type', 'result', 'findings', 'reviewer_id', 'reviewed_at'];
    protected function casts(): array { return ['findings' => 'array', 'reviewed_at' => 'datetime']; }
    
}
