<?php

namespace App\Observers;

use App\Models\AuditLog;
use App\Models\Brand;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BrandObserver
{
    public function created(Brand $brand): void { $this->record('brand.created', $brand, array_keys($brand->getAttributes())); }
    public function updated(Brand $brand): void { $this->record('brand.updated', $brand, array_keys($brand->getChanges())); }

    private function record(string $action, Brand $brand, array $fields): void
    {
        // No configuration contents, user input values or secrets in the audit metadata.
        DB::table('audit_logs')->insert([
            'user_id' => Auth::id(),
            'actor_type' => Auth::check() ? 'user' : 'system',
            'action' => $action,
            'resource_type' => 'brand',
            'resource_id' => (string) $brand->getKey(),
            'metadata' => json_encode(['changed_fields' => array_values(array_diff($fields, ['updated_at','created_at']))]),
            'created_at' => now(),
        ]);
    }
}
