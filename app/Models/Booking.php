<?php

namespace App\Models;

use Database\Factories\BookingFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
 * @property Carbon|null $payment_due_at
 * @property-read string $current_payment_status
 */
class Booking extends Model
{
    /** @use HasFactory<BookingFactory> */
    use HasFactory, KeepsDeletedModels, LogsActivity;

    public const array RENTAL_TYPES = ['daily', 'weekly', 'monthly'];

    public const array STATUSES = ['reserved', 'taken', 'returned', 'cancelled'];

    public const array PAYMENT_STATUSES = ['pending', 'paid', 'overdue'];

    public const array CURRENT_PAYMENT_STATUSES = ['paid', 'unpaid'];

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
            'payment_due_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** @param Builder<static> $query */
    #[Scope]
    protected function withCurrentPaymentStatus(Builder $query): void
    {
        $start = 'bookings.start_date';
        $end = 'COALESCE(bookings.actual_return_date, bookings.end_date)';

        if ($query->getModel()->getConnection()->getDriverName() === 'sqlite') {
            $days = "CAST(julianday($end) - julianday($start) AS INTEGER)";
            $weeks = "CAST((($days) + 6) / 7 AS INTEGER)";
            $months = "((strftime('%Y', $end) - strftime('%Y', $start)) * 12 + strftime('%m', $end) - strftime('%m', $start))";
            $partialMonth = "strftime('%d', $end) > strftime('%d', $start)";
        } else {
            $days = "DATEDIFF($end, $start)";
            $weeks = "CEIL(($days) / 7.0)";
            $months = "((YEAR($end) - YEAR($start)) * 12 + MONTH($end) - MONTH($start))";
            $partialMonth = "DAY($end) > DAY($start)";
        }

        $units = "CASE bookings.rental_type WHEN 'daily' THEN $days WHEN 'weekly' THEN $weeks ELSE $months + CASE WHEN $partialMonth THEN 1 ELSE 0 END END";
        $charges = static::query()->select('bookings.*')
            ->selectRaw("(CASE WHEN ($units) < 1 THEN 1 ELSE ($units) END) * ROUND(bookings.rate_per_unit * 100) AS rental_charge_cents")
            ->selectSub(Payment::query()->selectRaw('COALESCE(SUM(ROUND(amount_received * 100)), 0)')
                ->whereColumn('payments.booking_id', 'bookings.id'), 'received_cents');

        $query->fromSub($charges, 'bookings')->select('bookings.*')
            ->selectRaw("CASE WHEN received_cents >= rental_charge_cents THEN 'paid' ELSE 'unpaid' END AS current_payment_status");
    }

    /** @return BelongsTo<Vehicle, $this> */
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
                'start_date', 'end_date', 'actual_return_date', 'start_odometer', 'end_odometer', 'booking_status', 'payment_status', 'payment_due_at'])
            ->logOnlyDirty()->dontLogIfAttributesChangedOnly(['updated_at'])->logEmptyChanges();
    }
}
