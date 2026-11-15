<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    public function test_admin_can_create_multiple_payments_with_authenticated_creator(): void
    {
        $booking = Booking::factory()->create(['payment_status' => 'pending']);
        $payload = $this->payload($booking);

        $this->actingAsUser()->post('/admin/payments', $payload + ['user_id' => $this->otherUser->id])->assertRedirect('/admin/payments');
        $payment = Payment::query()->sole();
        $this->post('/admin/payments', $payload)->assertRedirect('/admin/payments');

        $this->assertDatabaseCount('payments', 2);
        $this->assertSame($this->user->id, $payment->user_id);
        $this->assertSame('123.45', $payment->amount_received);
        $this->assertSame($payload['payment_date'], $payment->payment_date->format('Y-m-d'));
        $this->assertNull($payment->notes);
        $this->assertTrue($payment->booking->is($booking));
        $this->assertTrue($payment->customer->is($booking->customer));
        $this->assertTrue($payment->vehicle->is($booking->vehicle));
        $this->assertTrue($payment->user->is($this->user));
        $this->assertTrue($booking->payments->contains($payment));
        $this->assertTrue($booking->customer->payments->contains($payment));
        $this->assertTrue($booking->vehicle->payments->contains($payment));
        $this->assertTrue($this->user->createdPayments->contains($payment));
        $this->assertDatabaseHas('activity_log', ['log_name' => 'payment', 'event' => 'created', 'subject_id' => $payment->id]);
        $this->assertSame('pending', $booking->fresh()->payment_status);
    }

    public function test_payment_date_defaults_to_today_in_form_and_model(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 15)->setTime(12, 0));
        $this->actingAsUser()->get('/admin/payments/create')->assertOk()->assertSee('value="2026-11-15"', false);
        $this->assertSame('2026-11-15', (new Payment)->payment_date->format('Y-m-d'));
    }

    public function test_admin_can_view_edit_and_delete_without_changing_creator(): void
    {
        $payment = Payment::factory()->create(['user_id' => $this->user->id]);
        $booking = Booking::factory()->create();
        $this->actingAsUser()->get('/admin/payments')->assertOk()->assertSeeLivewire('admin.payment-table');
        $this->get("/admin/payments/{$payment->id}/edit")->assertOk()->assertSee('Edit Payment');
        $this->put("/admin/payments/{$payment->id}", array_replace($this->payload($booking), [
            'amount_received' => '9.99', 'payment_method' => 'upi', 'notes' => '<script>notes</script>', 'user_id' => $this->otherUser->id,
        ]))->assertRedirect('/admin/payments');
        $payment->refresh();
        $this->assertSame($this->user->id, $payment->user_id);
        $this->assertSame($booking->id, $payment->booking_id);
        $this->assertSame('9.99', $payment->amount_received);
        $this->get("/admin/payments/{$payment->id}")->assertOk()->assertSee('<script>notes</script>')->assertDontSee('<script>notes</script>', false);
        $this->assertDatabaseHas('activity_log', ['log_name' => 'payment', 'event' => 'updated']);
        $this->delete("/admin/payments/{$payment->id}/delete")->assertRedirect('/admin/payments');
        $this->assertModelMissing($payment);
        $this->assertDatabaseHas('deleted_models', ['model' => Payment::class, 'key' => (string) $payment->id]);
        $this->assertDatabaseHas('activity_log', ['log_name' => 'payment', 'event' => 'deleted']);
    }

    public function test_empty_payload_reports_all_required_fields(): void
    {
        $this->actingAsUser()->post('/admin/payments', [])->assertSessionHasErrors([
            'booking_id', 'customer_id', 'vehicle_id', 'payment_date', 'amount_received', 'payment_method',
        ]);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_notes_only_update_is_audited_without_recording_note_contents(): void
    {
        $payment = Payment::factory()->create(['amount_received' => '123.45', 'payment_method' => 'cash']);
        $this->actingAsUser()->put("/admin/payments/{$payment->id}", $this->payload($payment->booking) + ['notes' => 'Private payment context'])
            ->assertRedirect('/admin/payments');
        $activity = Activity::query()->where('log_name', 'payment')->where('event', 'updated')->sole();
        $this->assertSame($payment->id, $activity->subject_id);
        $this->assertStringNotContainsString('Private payment context', $activity->toJson());
        $this->assertSame('Private payment context', $payment->fresh()->notes);
    }

    #[DataProvider('invalidValues')]
    public function test_invalid_payment_is_rejected(string $field, mixed $value): void
    {
        $payload = $this->payload(Booking::factory()->create());
        $this->actingAsUser()->post('/admin/payments', array_replace($payload, [$field => $value]))->assertSessionHasErrors($field);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseMissing('activity_log', ['log_name' => 'payment', 'event' => 'created']);
    }

    public static function invalidValues(): array
    {
        return [
            'missing booking' => ['booking_id', 999999], 'invalid booking' => ['booking_id', []],
            'missing customer' => ['customer_id', 999999], 'missing vehicle' => ['vehicle_id', 999999],
            'invalid date' => ['payment_date', '2026-02-30'], 'invalid format' => ['payment_date', 'tomorrow'],
            'zero' => ['amount_received', 0], 'negative' => ['amount_received', -1],
            'too precise' => ['amount_received', '1.234'], 'too large' => ['amount_received', '10000000000'],
            'invalid method' => ['payment_method', 'card'], 'oversized notes' => ['notes', str_repeat('a', 10001)],
        ];
    }

    public function test_customer_and_vehicle_must_match_booking_on_create_and_update(): void
    {
        $booking = Booking::factory()->create();
        $other = Booking::factory()->create();
        $payment = Payment::factory()->for($booking)->create();
        $payload = array_replace($this->payload($booking), ['customer_id' => $other->customer_id, 'vehicle_id' => $other->vehicle_id]);
        $this->actingAsUser()->post('/admin/payments', $payload)->assertSessionHasErrors(['customer_id', 'vehicle_id']);
        $this->put("/admin/payments/{$payment->id}", $payload)->assertSessionHasErrors(['customer_id', 'vehicle_id']);
        $this->assertSame($booking->customer_id, $payment->fresh()->customer_id);
    }

    public function test_guest_and_non_admin_cannot_access_any_payment_endpoint(): void
    {
        $payment = Payment::factory()->create();
        $this->get('/admin/payments')->assertRedirect('/login');
        foreach (['staff', 'customer'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->withSession(['otp_verified' => true]);
            foreach (['manage-payments', 'viewAny', 'view', 'create', 'update', 'delete', 'export'] as $ability) {
                $this->assertFalse(Gate::forUser($user)->allows($ability, $payment));
            }
            foreach (['/admin/payments', '/admin/payments/create', "/admin/payments/{$payment->id}", "/admin/payments/{$payment->id}/edit"] as $url) {
                $this->get($url)->assertForbidden();
            }
            $this->post('/admin/payments', [])->assertForbidden();
            $this->put("/admin/payments/{$payment->id}", [])->assertForbidden();
            $this->delete("/admin/payments/{$payment->id}/delete")->assertForbidden();
        }
        $this->assertModelExists($payment);
    }

    public function test_payment_prevents_parent_booking_deletion(): void
    {
        $payment = Payment::factory()->create();
        $this->actingAsUser()->delete("/admin/bookings/{$payment->booking_id}/delete")->assertSessionHasErrors('booking');
        $this->assertModelExists($payment->booking);
        $this->assertModelExists($payment);
    }

    public function test_booking_lookup_is_paginated_searchable_and_admin_only(): void
    {
        Booking::factory()->count(22)->create();
        $booking = Booking::factory()->create();
        $this->actingAsUser()->getJson('/admin/payments/bookings')->assertOk()->assertJsonCount(20, 'results')->assertJsonPath('pagination.more', true);
        $this->getJson('/admin/payments/bookings?q='.$booking->booking_code)->assertJsonCount(1, 'results')
            ->assertJsonPath('results.0.id', $booking->id)->assertJsonPath('results.0.customer_id', $booking->customer_id)
            ->assertJsonPath('results.0.vehicle_id', $booking->vehicle_id);
        $this->actingAs($this->otherUser)->getJson('/admin/payments/bookings')->assertForbidden();
    }

    /** @return array<string, mixed> */
    private function payload(Booking $booking): array
    {
        return [
            'booking_id' => $booking->id, 'customer_id' => $booking->customer_id, 'vehicle_id' => $booking->vehicle_id,
            'payment_date' => now()->format('Y-m-d'), 'amount_received' => '123.45', 'payment_method' => 'cash',
        ];
    }
}
