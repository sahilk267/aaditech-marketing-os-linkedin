<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Campaign extends Model
{
    protected $table = 'campaigns';
    protected $fillable = ['brand_id', 'name', 'objective', 'audience', 'content_pillars', 'start_date', 'end_date', 'status'];
    protected function casts(): array { return ['content_pillars' => 'array', 'start_date' => 'date', 'end_date' => 'date']; }
    
}
