<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\AuditLog
 *
 * @property int $id
 * @property int $user_id
 * @property string $action
 * @property-read User|null $user
 */
class AuditLog extends Model
{
    protected $fillable = [
        'user_id', 'action', 'auditable_type', 'auditable_id',
        'route', 'method', 'ip', 'user_agent', 'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
