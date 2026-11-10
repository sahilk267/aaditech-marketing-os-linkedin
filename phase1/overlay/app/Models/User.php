<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'role', 'is_active'];
    protected $hidden = ['password', 'remember_token'];
    protected function casts(): array
    {
        return ['email_verified_at' => 'datetime', 'password' => 'hashed', 'is_active' => 'boolean', 'last_login_at' => 'datetime'];
    }

    public function brands(): BelongsToMany
    {
        return $this->belongsToMany(Brand::class)->withTimestamps();
    }

    public function isAdmin(): bool
    {
        return $this->is_active && $this->role === 'admin';
    }

    public function hasBrandAccess(Brand|int $brand): bool
    {
        if (!$this->is_active) return false;
        if ($this->isAdmin()) return true;
        $id = $brand instanceof Brand ? $brand->getKey() : $brand;
        return $this->brands()->whereKey($id)->exists();
    }
}
