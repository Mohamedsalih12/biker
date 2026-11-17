<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\Vehicle;
use Tests\TestCase;

class HomeDashboardTest extends TestCase
{
    public function test_admin_sees_dashboard_with_live_figures(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 17)->setTime(12, 0));

        $rented = Vehicle::factory()->create();
        $taken = Booking::factory()->create([
            'vehicle_id' => $rented->id,
            'booking_status' => 'taken',
            'payment_status' => 'overdue',
        ]);
        Vehicle::factory()->create();

        Payment::factory()->create(['booking_id' => $taken->id, 'amount_received' => 25980]);

        $response = $this->actingAsUser()->get('/home');

        $response->assertOk();
        $response->assertSee('1 / 2', false);
        $response->assertSee('25,980');
        $response->assertSee(e($taken->booking_code), false);
        $response->assertSee(e($rented->registration_number), false);
        $response->assertSee('Overdue');
    }

    public function test_customer_role_is_forbidden(): void
    {
        $this->actingAs($this->otherUser)->get('/home')->assertForbidden();
    }

    public function test_dashboard_renders_empty_state(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 17)->setTime(12, 0));

        Vehicle::factory()->create();

        $response = $this->actingAsUser()->get('/home');

        $response->assertOk();
        $response->assertSee('0 / 1', false);
        $response->assertSee('No bookings recorded yet.');
    }
}
