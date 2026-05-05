<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ThreatIp extends Model
{
    protected $table = 'threat_ips';

    public $timestamps = false;

    protected $fillable = [
        'ip',
        'country',
        'country_code',
        'city',
        'isp',
        'asn',
        'is_proxy',
        'is_vpn',
        'is_tor',
        'total_hits',
        'first_seen',
        'last_seen',
    ];

    protected $casts = [
        'is_proxy' => 'bool',
        'is_vpn' => 'bool',
        'is_tor' => 'bool',
        'first_seen' => 'datetime',
        'last_seen' => 'datetime',
    ];

    public function sshAttempts(): HasMany
    {
        return $this->hasMany(SshAttempt::class, 'ip_id');
    }

    public function nginxHits(): HasMany
    {
        return $this->hasMany(NginxHit::class, 'ip_id');
    }
}
