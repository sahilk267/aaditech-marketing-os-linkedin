<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OauthState extends Model
{
    protected $table = 'oauth_states';
    protected $fillable = ['user_id', 'brand_id', 'platform','external_account_id', 'state_hash', 'expires_at', 'consumed_at'];
    protected function casts(): array { return ['expires_at' => 'datetime', 'consumed_at' => 'datetime']; }
    
}
