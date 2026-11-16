<?php

namespace Tests\Feature;

use App\Livewire\FleetDashboard;
use App\Models\Booking;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class FleetDashboardTest extends TestCase
{
    public function test_available_bikes_appear_before_reserved_and_taken_bikes(): void
    {
        $reserved = Vehicle::factory()->create(['brand' => 'Ather', 'model' => 'Alpha']);
        $taken = Vehicle::factory()->create(['brand' => 'Ather', 'model' => 'Beta']);
        $availableLast = Vehicle::factory()->create(['brand' => 'TVS', 'model' => 'Zulu']);
        $availableFirst = Vehicle::factory()->create(['brand' => 'Ather', 'model' => 'Delta']);
        Booking::factory()->for($reserved)->create(['booking_status' => 'reserved']);
        Booking::factory()->for($taken)->create(['booking_status' => 'taken']);

        Livewire::test(FleetDashboard::class)->assertViewHas('bikes', function (array $bikes) use ($availableFirst, $availableLast, $reserved, $taken): bool {
            return array_column($bikes, 'id') === [$availableFirst->id, $availableLast->id, $reserved->id, $taken->id]
                && array_keys($bikes) === [0, 1, 2, 3];
        });
    }

    public function test_guests_can_browse_the_fleet_without_private_vehicle_details(): void
    {
        Vehicle::factory()->create([
            'brand' => 'Ather', 'model' => 'Rizta', 'rate_per_unit' => 6000,
            'chassis_number' => 'PRIVATE-CHASSIS', 'damages' => 'PRIVATE-NOTES',
        ]);
        Vehicle::factory()->create(['status' => 'decommissioned', 'model' => 'Retired bike']);

        $this->get('/')->assertSeeLivewire(FleetDashboard::class)
            ->assertSee('Ather')->assertSee('Rizta')->assertSee('₹6,000')
            ->assertSee('Book Now')->assertDontSee('Retired bike')
            ->assertDontSee('PRIVATE-CHASSIS')->assertDontSee('PRIVATE-NOTES');
    }

    #[TestWith(['reserved'])]
    #[TestWith(['taken'])]
    public function test_blocking_bookings_show_the_following_calendar_day(string $status): void
    {
        $this->travelTo('2026-11-16');
        $vehicle = Vehicle::factory()->create();
        Booking::factory()->for($vehicle)->create([
            'booking_status' => $status, 'end_date' => '2026-11-30',
        ]);

        Livewire::test(FleetDashboard::class)
            ->assertSee('Not Available')->assertSee('Next Available:')
            ->assertSee('01 Dec 2026')->assertSee('Visit us to pre-book from 01 Dec')
            ->assertDontSee('Book Now');
    }

    #[TestWith(['returned'])]
    #[TestWith(['cancelled'])]
    public function test_finished_bookings_do_not_block_a_bike(string $status): void
    {
        $vehicle = Vehicle::factory()->create(['status' => 'rented']);
        Booking::factory()->for($vehicle)->create(['booking_status' => $status]);

        Livewire::test(FleetDashboard::class)->assertSee('Book Now')->assertDontSee('Not Available');
    }

    public function test_latest_blocking_end_date_wins_even_with_a_newer_returned_booking(): void
    {
        $this->travelTo('2026-11-16');
        $vehicle = Vehicle::factory()->create();
        Booking::factory()->for($vehicle)->create(['booking_status' => 'reserved', 'end_date' => '2026-12-31']);
        Booking::factory()->for($vehicle)->create(['booking_status' => 'taken', 'end_date' => '2026-11-24']);
        Booking::factory()->for($vehicle)->create(['booking_status' => 'returned', 'end_date' => '2027-02-01']);

        Livewire::test(FleetDashboard::class)->assertSee('01 Jan 2027')->assertDontSee('02 Feb 2027');
    }

    public function test_past_due_blocking_bookings_do_not_promise_a_past_availability_date(): void
    {
        $this->travelTo('2026-12-10');
        Booking::factory()->create(['end_date' => '2026-11-24']);

        Livewire::test(FleetDashboard::class)->assertSee('Return date to be confirmed')
            ->assertDontSee('Visit us to pre-book from 25 Nov');
    }

    public function test_a_bike_expected_back_today_shows_today_without_becoming_bookable(): void
    {
        $this->travelTo('2026-11-25');
        Booking::factory()->create(['end_date' => '2026-11-24']);

        Livewire::test(FleetDashboard::class)->assertSee('Next Available:')
            ->assertSee('25 Nov 2026')->assertDontSee('Book Now');
    }

    public function test_indian_price_grouping_and_escaped_vehicle_names(): void
    {
        Vehicle::factory()->create(['rate_per_unit' => 125000, 'model' => '<script>alert(1)</script>']);

        Livewire::test(FleetDashboard::class)->assertSee('₹1,25,000')
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_fractional_prices_are_not_rounded_to_a_different_amount(): void
    {
        Vehicle::factory()->create(['rate_per_unit' => '6000.50']);

        Livewire::test(FleetDashboard::class)->assertSee('₹6,000.50');
    }

    #[TestWith([12345, 'Odometer: 12,345 km'])]
    #[TestWith([0, 'Odometer: 0 km'])]
    #[TestWith([null, 'Odometer not listed'])]
    public function test_cards_show_the_vehicle_odometer_instead_of_range(?int $reading, string $expected): void
    {
        Vehicle::factory()->create(['odometer_reading' => $reading]);

        Livewire::test(FleetDashboard::class)->assertSee($expected)
            ->assertDontSee('Range not listed');
    }

    public function test_gallery_round_trips_json_and_shows_at_most_six_angles(): void
    {
        $images = array_map(fn (int $angle): string => "vehicles/angle-$angle.jpg", range(1, 7));
        $vehicle = Vehicle::factory()->create(['images' => $images]);

        $this->assertSame($images, $vehicle->fresh()->images);
        Livewire::test(FleetDashboard::class)
            ->assertSee('/storage/vehicles/angle-1.jpg', false)
            ->assertSee('/storage/vehicles/angle-6.jpg', false)
            ->assertDontSee('/storage/vehicles/angle-7.jpg', false);
    }

    public function test_existing_single_photo_is_a_gallery_fallback(): void
    {
        Vehicle::factory()->create(['image' => 'vehicles/original.jpg']);

        Livewire::test(FleetDashboard::class)->assertSee('/storage/vehicles/original.jpg', false);
    }

    public function test_empty_fleet_has_an_honest_empty_state(): void
    {
        $this->get('/')->assertSee('Our next ride is on its way')->assertDontSee('Book Now');
    }

    public function test_fleet_and_bookings_use_two_queries_regardless_of_fleet_size(): void
    {
        Vehicle::factory()->count(8)->create();
        DB::enableQueryLog();
        DB::flushQueryLog();

        Livewire::test(FleetDashboard::class);

        $fleetQueries = collect(DB::getQueryLog())->filter(
            fn (array $query): bool => preg_match('/from ["`](vehicles|bookings)["`]/i', $query['query']) === 1,
        );
        DB::disableQueryLog();
        $this->assertCount(2, $fleetQueries);
    }
}
