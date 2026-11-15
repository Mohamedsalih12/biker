<?php

namespace Tests\Feature;

use App\Jobs\SendBookingTelegramNotification;
use App\Models\Booking;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class BookingTelegramNotificationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['services.telegram.bot_token' => 'test-token', 'services.telegram.admin_chat_id' => '123456']);
    }

    public function test_creation_queues_a_snapshot_only_after_commit(): void
    {
        $vehicle = Vehicle::factory()->create();
        Queue::fake();
        DB::beginTransaction();

        $response = $this->actingAsUser()->post(route('xpress.bookings.store'), $this->payload($vehicle));

        $booking = Booking::query()->sole();
        $response->assertRedirect(route('xpress.bookings.agreement', $booking));
        Queue::assertNothingPushed();
        DB::commit();
        Queue::assertPushed(SendBookingTelegramNotification::class, function ($job) use ($booking, $vehicle): bool {
            return str_contains($job->text, 'Booking created: '.$booking->booking_code)
                && str_contains($job->text, 'Customer: '.$this->otherUser->name)
                && str_contains($job->text, 'Vehicle: '.$vehicle->registration_number)
                && str_contains($job->text, 'Dates: 2026-11-01 to 2026-12-01')
                && str_contains($job->text, 'Status: reserved')
                && str_contains($job->text, 'By: '.$this->user->name);
        });
    }

    public function test_update_notifies_with_the_current_actor_and_new_status(): void
    {
        $booking = Booking::factory()->create(['customer_id' => $this->otherUser->id, 'booking_status' => 'reserved']);
        $actor = User::factory()->admin()->create();
        Queue::fake();

        $this->actingAs($actor)->put(route('xpress.bookings.update', $booking),
            $this->payload($booking->vehicle) + ['booking_status' => 'taken'])
            ->assertRedirect(route('xpress.bookings.agreement', $booking));

        $this->assertSame('taken', $booking->fresh()->booking_status);
        Queue::assertPushed(SendBookingTelegramNotification::class, fn ($job): bool => str_contains($job->text, 'Booking updated: '.$booking->booking_code)
            && str_contains($job->text, 'Status: taken')
            && str_contains($job->text, 'By: '.$actor->name));
    }

    public function test_unchanged_save_does_not_notify_even_when_timestamp_changes(): void
    {
        $booking = Booking::factory()->create(['customer_id' => $this->otherUser->id]);
        $this->travel(1)->minutes();
        Queue::fake();

        $this->actingAsUser()->put(route('xpress.bookings.update', $booking), $this->payload($booking->vehicle))
            ->assertRedirect(route('xpress.bookings.agreement', $booking));

        Queue::assertNothingPushed();
    }

    public function test_rolled_back_creation_does_not_notify(): void
    {
        $vehicle = Vehicle::factory()->create();
        Queue::fake();
        DB::beginTransaction();

        $this->actingAsUser()->post(route('xpress.bookings.store'), $this->payload($vehicle))->assertRedirect();
        DB::rollBack();

        $this->assertDatabaseCount('bookings', 0);
        Queue::assertNothingPushed();
    }

    public function test_failed_booking_transaction_does_not_notify(): void
    {
        $vehicle = Vehicle::factory()->create();
        User::saving(fn (User $user): ?bool => $user->isDirty('notes') ? false : null);
        Queue::fake();

        $this->actingAsUser()->post(route('xpress.bookings.store'),
            $this->payload($vehicle) + ['return_notes' => 'Return note'])->assertSessionHasErrors('return_notes');

        $this->assertDatabaseCount('bookings', 0);
        Queue::assertNothingPushed();
    }

    #[DataProvider('missingCredentials')]
    public function test_unconfigured_telegram_does_not_queue_notifications(string $key): void
    {
        $vehicle = Vehicle::factory()->create();
        config([$key => null]);
        Queue::fake();

        $this->actingAsUser()->post(route('xpress.bookings.store'), $this->payload($vehicle))->assertRedirect();

        $this->assertDatabaseCount('bookings', 1);
        Queue::assertNothingPushed();
    }

    public static function missingCredentials(): array
    {
        return [['services.telegram.bot_token'], ['services.telegram.admin_chat_id']];
    }

    public function test_notes_only_update_notifies_without_disclosing_private_notes(): void
    {
        $booking = Booking::factory()->create(['customer_id' => $this->otherUser->id]);
        Queue::fake();

        $this->actingAsUser()->put(route('xpress.bookings.update', $booking),
            $this->payload($booking->vehicle) + ['handover_notes' => 'Private note content'])->assertRedirect();

        $this->assertSame('Private note content', $booking->fresh()->handover_notes);
        Queue::assertPushed(SendBookingTelegramNotification::class, fn ($job): bool => str_contains($job->text, 'Booking updated: '.$booking->booking_code)
            && ! str_contains($job->text, 'Private note content'));
    }

    public function test_queue_failure_does_not_interrupt_the_booking_save(): void
    {
        $vehicle = Vehicle::factory()->create();
        config(['queue.default' => 'unavailable-queue']);
        Exceptions::fake();

        $this->actingAsUser()->post(route('xpress.bookings.store'), $this->payload($vehicle))->assertRedirect();

        $this->assertDatabaseCount('bookings', 1);
        Exceptions::assertReported(fn (RuntimeException $exception): bool => $exception->getMessage() === 'Booking saved, but the Telegram notification could not be queued.');
    }

    public function test_job_sends_plain_text_to_the_configured_private_chat(): void
    {
        $booking = Booking::factory()->create();
        $job = new SendBookingTelegramNotification($booking, 'created', $this->user->name);
        Http::preventStrayRequests();
        Http::fake(['https://api.telegram.org/bottest-token/sendMessage' => Http::response(['ok' => true])]);

        $job->handle();

        Http::assertSent(fn ($request): bool => $request->method() === 'POST'
            && $request['chat_id'] === '123456'
            && $request['text'] === $job->text
            && ! isset($request['parse_mode']));
    }

    #[DataProvider('failedResponses')]
    public function test_delivery_failure_is_retryable_without_exposing_the_token(int $status): void
    {
        $job = new SendBookingTelegramNotification(Booking::factory()->create(), 'created', $this->user->name);
        Http::preventStrayRequests();
        Http::fake(['https://api.telegram.org/bottest-token/sendMessage' => Http::response(['ok' => false], $status)]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Telegram notification delivery failed (HTTP {$status}).");

        $job->handle();
    }

    public static function failedResponses(): array
    {
        return ['server error' => [503], 'rate limit' => [429], 'API rejection' => [200]];
    }

    public function test_connection_failure_is_sanitized_for_queue_logs(): void
    {
        $job = new SendBookingTelegramNotification(Booking::factory()->create(), 'created', $this->user->name);
        Http::preventStrayRequests();
        Http::fake(['https://api.telegram.org/bottest-token/sendMessage' => Http::failedConnection()]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Telegram notification connection failed.');

        $job->handle();
    }

    /** @return array<string, mixed> */
    private function payload(Vehicle $vehicle): array
    {
        return [
            'customer_id' => $this->otherUser->id, 'vehicle_id' => $vehicle->id,
            'rate_per_unit' => '2500.00', 'start_date' => '2026-11-01', 'end_date' => '2026-12-01',
            'start_odometer' => 100,
        ];
    }
}
