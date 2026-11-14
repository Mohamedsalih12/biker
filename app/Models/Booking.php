<?php

namespace App\Models;

use Database\Factories\BookingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use LogicException;
use OverflowException;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\DeletedModels\Models\Concerns\KeepsDeletedModels;

/**
 * @property Carbon|null $start_date
 * @property Carbon|null $end_date
 * @property Carbon|null $actual_return_date
 */
class Booking extends Model
{
    /** @use HasFactory<BookingFactory> */
    use HasFactory, KeepsDeletedModels, LogsActivity;

    public const array RENTAL_TYPES = ['daily', 'weekly', 'monthly'];

    public const array STATUSES = ['reserved', 'taken', 'returned', 'cancelled'];

    public const array PAYMENT_STATUSES = ['pending', 'paid', 'overdue'];

    /** @var list<string> */
    protected $fillable = [
        'customer_id', 'vehicle_id', 'rental_type', 'rate_per_unit', 'start_date', 'end_date',
        'actual_return_date', 'start_odometer', 'end_odometer', 'booking_status', 'payment_status', 'handover_notes', 'return_notes',
    ];

    protected static function booted(): void
    {
        static::creating(function (Booking $booking): void {
            $booking->booking_code ??= static::nextBookingCode();
        });
    }

    public static function nextBookingCode(): string
    {
        return DB::transaction(function (): string {
            $sequence = DB::table('booking_code_sequences')
                ->where('id', 1)
                ->lockForUpdate()
                ->first();

            if ($sequence === null) {
                throw new LogicException('The booking code sequence has not been initialized.');
            }

            $nextCode = (int) $sequence->last_code;

            do {
                $nextCode++;

                if ($nextCode > 99999) {
                    throw new OverflowException('The five-digit booking code sequence is exhausted.');
                }
            } while (DB::table('bookings')->where('booking_code', (string) $nextCode)->exists());

            DB::table('booking_code_sequences')->where('id', 1)->update(['last_code' => $nextCode]);

            return (string) $nextCode;
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'rate_per_unit' => 'decimal:2', 'start_date' => 'date', 'end_date' => 'date',
            'actual_return_date' => 'date', 'start_odometer' => 'integer', 'end_odometer' => 'integer',
        ];
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
        return LogOptions::defaults()->useLogName('booking')
            ->logOnly(['booking_code', 'customer_id', 'vehicle_id', 'user_id', 'rental_type', 'rate_per_unit',
                'start_date', 'end_date', 'actual_return_date', 'start_odometer', 'end_odometer', 'booking_status', 'payment_status'])
            ->logOnlyDirty()->dontLogIfAttributesChangedOnly(['updated_at'])->logEmptyChanges();
    }
}
