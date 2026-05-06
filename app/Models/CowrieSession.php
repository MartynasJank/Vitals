<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CowrieSession extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'ip_id',
        'session',
        'started_at',
        'ended_at',
        'duration_seconds',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'duration_seconds' => 'float',
    ];

    public function ip(): BelongsTo
    {
        return $this->belongsTo(ThreatIp::class, 'ip_id');
    }

    public function login(): HasOne
    {
        return $this->hasOne(CowrieLogin::class, 'cowrie_session_id');
    }

    public function commands(): HasMany
    {
        return $this->hasMany(CowrieCommand::class, 'cowrie_session_id');
    }

    public function downloads(): HasMany
    {
        return $this->hasMany(CowrieDownload::class, 'cowrie_session_id');
    }
}
