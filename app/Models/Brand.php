<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Brand extends Model
{
    protected $fillable = ['name', 'slug', 'website_url', 'description', 'logo_path', 'brand_config', 'is_active'];

    protected function casts(): array
    {
        return ['brand_config' => 'array', 'is_active' => 'boolean'];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if (!$user->is_active) return $query->whereRaw('1 = 0');
        if ($user->isAdmin()) return $query;
        return $query->whereHas('users', fn (Builder $q) => $q->whereKey($user->getKey()));
    }
}
