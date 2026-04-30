<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteCheck extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'site_name',
        'url',
        'status',
        'response_ms',
        'status_code',
        'checked_at',
    ];

    protected $casts = [
        'checked_at' => 'datetime',
    ];
}
