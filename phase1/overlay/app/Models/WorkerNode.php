<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkerNode extends Model
{
    protected $fillable = ['node_name','platform','worker_version','capabilities','last_heartbeat_at','status'];
    protected function casts(): array { return ['capabilities'=>'array','last_heartbeat_at'=>'datetime']; }
}
