<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NginxHit extends Model
{
    protected $table = 'nginx_hits';

    public $timestamps = false;

    protected $fillable = [
        'ip_id',
        'path',
        'method',
        'status_code',
        'user_agent',
        'scan_type',
        'timestamp',
    ];

    protected $casts = [
        'timestamp' => 'datetime',
    ];

    public function threatIp(): BelongsTo
    {
        return $this->belongsTo(ThreatIp::class, 'ip_id');
    }
}
