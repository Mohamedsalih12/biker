<?php

namespace App\Models;

use Database\Factories\VehicleFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\DeletedModels\Models\Concerns\KeepsDeletedModels;

/**
 * @property Carbon|null $purchase_date
 * @property Carbon|null $insurance_expires_at
 * @property string|null $image
 * @property list<string>|null $images
 * @property string|null $damages
 * @property string|null $rate_per_unit
 * @property-read bool $has_active_maintenance
 * @property-read string $current_status
 */
class Vehicle extends Model
{
    /** @use HasFactory<VehicleFactory> */
    use HasFactory, KeepsDeletedModels, LogsActivity;

    public const array STATUSES = ['available', 'rented', 'maintenance', 'decommissioned'];

    public const array CURRENT_STATUSES = ['available', 'reserved', 'rented', 'maintenance', 'decommissioned'];

    /** @var list<string> */
    protected $fillable = [
        'registration_number', 'brand', 'model', 'chassis_number', 'battery_serial_number',
        'odometer_reading', 'rate_per_unit', 'status', 'purchase_date', 'purchase_cost',
        'insurance_policy_number', 'insurance_expires_at', 'image', 'images', 'damages',
    ];

    protected static function booted(): void
    {
        static::saving(function (Vehicle $vehicle): void {
            if ($vehicle->exists && $vehicle->maintenanceLogs()->where('status', 'in_progress')->exists()) {
                $vehicle->status = 'maintenance';
            }
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'odometer_reading' => 'integer',
            'purchase_cost' => 'decimal:2',
            'rate_per_unit' => 'decimal:2',
            'purchase_date' => 'date',
            'insurance_expires_at' => 'date',
            'images' => 'array',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('vehicle')->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }

    /** @return HasMany<Booking, $this> */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** @return HasMany<MaintenanceLog, $this> */
    public function maintenanceLogs(): HasMany
    {
        return $this->hasMany(MaintenanceLog::class);
    }

    public function isUnderMaintenance(): bool
    {
        return $this->status === 'maintenance'
            || $this->maintenanceLogs()->where('status', 'in_progress')->exists();
    }

    /** @return array{severity: string, message: string, log_id: int}|null */
    public function maintenanceAlert(): ?array
    {
        $today = today();
        $warning = null;
        $danger = null;

        foreach ($this->maintenanceLogs->sortBy('id') as $log) {
            $date = match ($log->status) {
                'scheduled' => $log->delivered_date,
                'in_progress' => $log->expected_completion_date,
                default => null,
            };

            if ($date === null) {
                continue;
            }

            if ($date->lt($today)) {
                $days = (int) $date->diffInDays($today);
                $subject = $log->status === 'scheduled' ? 'Scheduled delivery' : 'Completion';
                $danger ??= [
                    'severity' => 'danger', 'log_id' => $log->id,
                    'message' => $subject.' overdue by '.$days.' '.str('day')->plural($days),
                ];
            } elseif ($log->status === 'scheduled' && $date->lte($today->copy()->addDays(3))) {
                $days = (int) $today->diffInDays($date);
                $warning ??= [
                    'severity' => 'warning', 'log_id' => $log->id,
                    'message' => $days === 0 ? 'Maintenance due today' : 'Maintenance due in '.$days.' '.str('day')->plural($days),
                ];
            }
        }

        return $danger ?? $warning;
    }

    /** @param Builder<static> $query */
    #[Scope]
    protected function withCurrentStatus(Builder $query): void
    {
        $latestBooking = Booking::query()
            ->selectRaw("CASE booking_status WHEN 'reserved' THEN 'reserved' WHEN 'taken' THEN 'rented' WHEN 'returned' THEN 'available' WHEN 'cancelled' THEN 'available' END")
            ->whereColumn('bookings.vehicle_id', 'vehicles.id')
            ->orderByDesc('bookings.id')
            ->limit(1);

        $query->addSelect('vehicles.*')->selectRaw(
            "CASE WHEN vehicles.status IN ('maintenance', 'decommissioned') THEN vehicles.status ELSE COALESCE((".$latestBooking->toSql().'), vehicles.status) END AS current_status',
            $latestBooking->getBindings(),
        );
    }

    public function imageUrl(): ?string
    {
        $path = $this->imagePaths()[0] ?? null;

        return $path !== null ? asset('storage/'.ltrim($path, '/')) : null;
    }

    /** @return list<string> */
    public function imagePaths(): array
    {
        return collect([$this->image, ...($this->images ?? [])])
            ->filter(fn (mixed $path): bool => is_string($path) && trim($path) !== '')
            ->unique()->values()->all();
    }
}
