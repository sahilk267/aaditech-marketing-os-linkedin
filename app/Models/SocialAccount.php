<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SocialAccount extends Model
{
    protected $table = 'social_accounts';
    protected $fillable = ['brand_id', 'platform', 'account_name', 'external_account_id', 'access_token_encrypted', 'refresh_token_encrypted', 'scopes', 'token_expires_at', 'connection_status', 'api_access_approved', 'last_health_check_at'];
    protected function casts(): array { return ['scopes' => 'array', 'token_expires_at' => 'datetime', 'last_health_check_at' => 'datetime', 'api_access_approved'=>'boolean']; }
    
    protected $hidden = ['access_token_encrypted','refresh_token_encrypted'];
    public function accessToken(): ?string { return $this->access_token_encrypted ? decrypt($this->access_token_encrypted) : null; }

}
