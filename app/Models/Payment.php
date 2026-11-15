<?php

namespace App\Models;

use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\DeletedModels\Models\Concerns\KeepsDeletedModels;

/**
 * @property Carbon|null $payment_date
 */
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory, KeepsDeletedModels, LogsActivity;

    public const array METHODS = ['cash', 'upi', 'other'];

    /** @var list<string> */
    protected $fillable = ['booking_id', 'payment_date', 'amount_received', 'payment_method', 'customer_id', 'vehicle_id', 'notes'];

    /** @param array<string, mixed> $attributes */
    public function __construct(array $attributes = [])
    {
        parent::__construct(['payment_date' => today()->toDateString(), ...$attributes]);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['payment_date' => 'date', 'amount_received' => 'decimal:2'];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
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
        return LogOptions::defaults()->useLogName('payment')
            ->logOnly(['booking_id', 'payment_date', 'amount_received', 'payment_method', 'customer_id', 'vehicle_id', 'user_id'])
            ->logOnlyDirty()->dontLogIfAttributesChangedOnly(['updated_at'])->logEmptyChanges();
    }
}
