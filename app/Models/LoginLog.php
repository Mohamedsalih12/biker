<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoginLog extends Model
{
    protected $fillable = [
        'user_id',
        'pos_code',
        'country',
        'login_time',
        'logout_time',
        'ip_address',
    ];

    protected $casts = [
        'logout_time' => 'datetime',
        'login_time' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
