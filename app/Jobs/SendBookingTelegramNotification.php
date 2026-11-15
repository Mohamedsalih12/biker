<?php

namespace App\Jobs;

use App\Models\Booking;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class SendBookingTelegramNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 20;

    /** @var list<int> */
    public array $backoff = [30, 120];

    public string $text;

    public function __construct(Booking $booking, string $event, string $actorName)
    {
        $booking->loadMissing(['customer:id,name', 'vehicle:id,registration_number']);

        $this->text = implode("\n", [
            "Booking {$event}: {$booking->booking_code}",
            'Customer: '.$booking->customer->name,
            'Vehicle: '.$booking->vehicle->registration_number,
            'Dates: '.$booking->start_date->toDateString().' to '.$booking->end_date->toDateString(),
            'Status: '.$booking->booking_status,
            'By: '.$actorName,
        ]);
    }

    public function handle(): void
    {
        $token = config('services.telegram.bot_token');
        $chatId = config('services.telegram.admin_chat_id');

        if (blank($token) || blank($chatId)) {
            return;
        }

        try {
            $response = Http::connectTimeout(3)->timeout(10)
                ->post("https://api.telegram.org/bot{$token}/sendMessage", [
                    'chat_id' => $chatId,
                    'text' => $this->text,
                ]);
        } catch (ConnectionException) {
            throw new RuntimeException('Telegram notification connection failed.');
        }

        if (! $response->successful() || $response->json('ok') !== true) {
            throw new RuntimeException("Telegram notification delivery failed (HTTP {$response->status()}).");
        }
    }
}
