<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Activity as SpatieActivity;

/**
 * App\Models\ActivityLog
 *
 * @property int $id
 * @property string $log_name
 * @property string $description
 * @property string $event
 * @property string $created_at
 * @property string $causer_type
 * @property int|null $subject_id
 * @property string|null $subject_type
 * @property string|null $country
 * @property Collection|null $properties
 * @property Collection|null $attribute_changes
 * @property-read User|null $user
 */
class ActivityLog extends SpatieActivity
{
    protected $table = 'activity_log';

    public function getTable(): string
    {
        return config('activitylog.table_name', 'activity_log');
    }

    public function getConnectionName(): ?string
    {
        return config('activitylog.database_connection') ?? parent::getConnectionName();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'causer_id', 'id');
    }
}
