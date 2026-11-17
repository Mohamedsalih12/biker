<?php

namespace App\Console\Commands;

use App\Models\Booking;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

#[Signature('bookings:expire-unpaid')]
#[Description('Cancel online reservations whose payment deadline has passed without a payment')]
class ExpireUnpaidBookings extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $deadline = now();
        $cancelled = 0;
        $candidates = Booking::query()
            ->where('booking_status', 'reserved')
            ->where('payment_due_at', '<=', $deadline)
            ->whereDoesntHave('payments', fn (Builder $query): Builder => $query->where('amount_received', '>', 0))
            ->select('id')->lazyById(200);

        foreach ($candidates as $candidate) {
            $wasCancelled = DB::transaction(function () use ($candidate, $deadline): bool {
                $booking = Booking::query()->whereKey($candidate->id)->lockForUpdate()->first();

                if ($booking === null || $booking->booking_status !== 'reserved'
                    || $booking->payment_due_at === null || $booking->payment_due_at->greaterThan($deadline)
                    || $booking->payments()->where('amount_received', '>', 0)->exists()) {
                    return false;
                }

                $booking->booking_status = 'cancelled';

                if (! $booking->save()) {
                    throw new RuntimeException("Could not cancel expired reservation {$booking->booking_code}.");
                }

                activity('booking')->performedOn($booking)->causedByAnonymous()->event('cancelled')
                    ->withProperties([
                        'reason' => 'payment_deadline_exceeded',
                        'payment_due_at' => $booking->payment_due_at->toDateTimeString(),
                    ])->log('Online reservation automatically cancelled after 24 hours without payment.');

                return true;
            }, attempts: 3);

            if ($wasCancelled) {
                $cancelled++;
            }
        }

        $this->info("Cancelled {$cancelled} expired unpaid reservation(s).");

        return self::SUCCESS;
    }
}
