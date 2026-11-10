<?php

namespace App\Policies;

use App\Models\Brand;
use App\Models\User;

class BrandPolicy
{
    public function viewAny(User $user): bool { return (bool) $user->is_active; }
    public function view(User $user, Brand $brand): bool { return $user->hasBrandAccess($brand); }
    public function create(User $user): bool { return $user->isAdmin(); }
    public function update(User $user, Brand $brand): bool { return $user->isAdmin(); }
    public function delete(User $user, Brand $brand): bool { return false; } // Explicitly defer deletion.
}
