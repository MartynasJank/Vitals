<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResourceSnapshot extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'cpu_percent',
        'ram_used_mb',
        'ram_total_mb',
        'disk_used_gb',
        'disk_total_gb',
        'recorded_at',
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
    ];
}
