<?php

namespace App\Models;

use Database\Factories\MaintenanceLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\DeletedModels\Models\Concerns\KeepsDeletedModels;

/**
 * @property Carbon|null $delivered_date
 * @property Carbon|null $expected_completion_date
 */
class MaintenanceLog extends Model
{
    /** @use HasFactory<MaintenanceLogFactory> */
    use HasFactory, KeepsDeletedModels, LogsActivity;

    public const array VENDORS = ['vendor_1', 'vendor_2', 'vendor_3', 'vendor_4'];

    public const array STATUSES = ['scheduled', 'in_progress', 'completed'];

    /** @var list<string> */
    protected $fillable = ['vehicle_id', 'service_description', 'delivered_date', 'expected_completion_date', 'cost', 'vendor_name', 'status'];

    /** @param array<string, mixed> $attributes */
    public function __construct(array $attributes = [])
    {
        parent::__construct(['delivered_date' => today()->toDateString(), 'status' => 'in_progress', ...$attributes]);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['delivered_date' => 'date', 'expected_completion_date' => 'date', 'cost' => 'decimal:2', 'odometer_reading' => 'integer'];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('maintenance_log')->logOnly([
            'vehicle_id', 'delivered_date', 'expected_completion_date', 'cost', 'odometer_reading', 'vendor_name', 'status', 'user_id',
        ])->logOnlyDirty()->dontLogIfAttributesChangedOnly(['updated_at'])->logEmptyChanges();
    }
}
