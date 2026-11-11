<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MediaAsset extends Model
{
    protected $table = 'media_assets';
    protected $fillable = ['brand_id', 'content_item_id', 'asset_type', 'storage_path', 'mime_type', 'file_size', 'width', 'height', 'duration_seconds', 'license_source', 'checksum', 'metadata'];
    protected function casts(): array { return ['metadata' => 'array']; }
    
}
