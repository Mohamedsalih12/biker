<?php

namespace App\Models;

use Database\Factories\VehicleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\DeletedModels\Models\Concerns\KeepsDeletedModels;

/**
 * @property Carbon|null $purchase_date
 * @property Carbon|null $insurance_expires_at
 */
class Vehicle extends Model
{
    /** @use HasFactory<VehicleFactory> */
    use HasFactory, KeepsDeletedModels, LogsActivity;

    public const array STATUSES = ['available', 'rented', 'maintenance', 'decommissioned'];

    /** @var list<string> */
    protected $fillable = [
        'registration_number', 'brand', 'model', 'chassis_number', 'battery_serial_number',
        'odometer_reading', 'status', 'purchase_date', 'purchase_cost',
        'insurance_policy_number', 'insurance_expires_at',
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
}
