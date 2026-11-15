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
 * @property string|null $damages
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
        'odometer_reading', 'status', 'purchase_date', 'purchase_cost',
        'insurance_policy_number', 'insurance_expires_at', 'image', 'damages',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'odometer_reading' => 'integer',
            'purchase_cost' => 'decimal:2',
            'purchase_date' => 'date',
            'insurance_expires_at' => 'date',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('vehicle')->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
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
        return $this->image ? asset('storage/'.$this->image) : null;
    }
}
