<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LogEntry extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'source',
        'message',
        'level',
        'captured_at',
    ];

    protected $casts = [
        'captured_at' => 'datetime',
    ];
}
