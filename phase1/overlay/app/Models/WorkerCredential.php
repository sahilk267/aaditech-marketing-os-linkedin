<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkerCredential extends Model
{
    protected $fillable = ['label', 'token_hash','allowed_capabilities','worker_node_id','revoked_at','last_used_at'];
    protected $hidden = ['token_hash'];
    protected function casts(): array { return ['allowed_capabilities'=>'array','revoked_at'=>'datetime','last_used_at'=>'datetime']; }
    public function node(): BelongsTo { return $this->belongsTo(WorkerNode::class, 'worker_node_id'); }
}
