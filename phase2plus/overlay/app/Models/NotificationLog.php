<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationLog extends Model
{
    protected $table = 'notification_logs';
    protected $fillable = ['channel', 'event_type', 'destination_masked', 'status', 'sent_at', 'error'];
    protected function casts(): array { return ['sent_at' => 'datetime']; }
    
}
