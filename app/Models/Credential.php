<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Credential extends Model
{
    protected $connection = 'mysql_threat';

    protected $table = 'credentials';

    public $timestamps = false;

    protected $fillable = [
        'username',
        'password',
        'hit_count',
        'first_seen',
        'last_seen',
    ];

    protected $casts = [
        'first_seen' => 'datetime',
        'last_seen' => 'datetime',
    ];
}
