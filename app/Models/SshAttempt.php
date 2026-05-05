<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SshAttempt extends Model
{
    protected $table = 'ssh_attempts';

    public $timestamps = false;

    protected $fillable = [
        'ip_id',
        'username',
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
