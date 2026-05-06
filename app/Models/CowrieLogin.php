<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CowrieLogin extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'cowrie_session_id',
        'username',
        'password',
        'is_success',
        'timestamp',
    ];

    protected $casts = [
        'is_success' => 'bool',
        'timestamp' => 'datetime',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(CowrieSession::class, 'cowrie_session_id');
    }
}
