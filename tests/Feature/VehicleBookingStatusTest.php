<?php

namespace Tests\Feature;

use App\Livewire\Admin\VehicleTable;
use App\Models\Booking;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class VehicleBookingStatusTest extends TestCase
{
    #[DataProvider('bookingStatuses')]
    public function test_latest_booking_determines_current_status_without_changing_stored_status(string $bookingStatus, string $expected): void
    {
        $vehicle = Vehicle::factory()->create(['status' => 'available']);
        $this->book($vehicle, $bookingStatus);

        $current = Vehicle::query()->withCurrentStatus()->findOrFail($vehicle->id);

        $this->assertSame($expected, $current->current_status);
        $this->assertSame('available', $vehicle->fresh()->status);
    }

    public static function bookingStatuses(): array
    {
        return [
            'reservation' => ['reserved', 'reserved'],
            'taken vehicle' => ['taken', 'rented'],
            'return' => ['returned', 'available'],
            'cancellation' => ['cancelled', 'available'],
        ];
    }

    #[DataProvider('manualStatuses')]
    public function test_vehicle_without_bookings_keeps_its_stored_status(string $status): void
    {
        $vehicle = Vehicle::factory()->create(['status' => $status]);

        $this->assertSame($status, Vehicle::query()->withCurrentStatus()->findOrFail($vehicle->id)->current_status);
    }

    public static function manualStatuses(): array
    {
        return ['available' => ['available'], 'rented' => ['rented'], 'maintenance' => ['maintenance'], 'decommissioned' => ['decommissioned']];
    }

    public function test_maintenance_and_decommissioned_override_booking_status(): void
    {
        foreach (['maintenance', 'decommissioned'] as $status) {
            $vehicle = Vehicle::factory()->create(['status' => $status]);
            foreach (['reserved', 'taken', 'returned', 'cancelled'] as $bookingStatus) {
                $this->book($vehicle, $bookingStatus);

                $this->assertSame($status, Vehicle::query()->withCurrentStatus()->findOrFail($vehicle->id)->current_status);
            }
        }
    }

    public function test_highest_booking_id_wins_even_when_older_booking_is_edited_later(): void
    {
        $vehicle = Vehicle::factory()->create(['status' => 'rented']);
        $older = $this->book($vehicle, 'taken');
        $latest = $this->book($vehicle, 'cancelled');
        $older->update(['booking_status' => 'reserved', 'updated_at' => now()->addDay()]);

        $this->assertSame('available', Vehicle::query()->withCurrentStatus()->findOrFail($vehicle->id)->current_status);
        $latest->update(['booking_status' => 'taken']);
        $this->assertSame('rented', Vehicle::query()->withCurrentStatus()->findOrFail($vehicle->id)->current_status);
        $latest->delete();
        $this->assertSame('reserved', Vehicle::query()->withCurrentStatus()->findOrFail($vehicle->id)->current_status);
    }

    public function test_grid_filter_and_export_use_current_status_not_stored_status(): void
    {
        $reserved = Vehicle::factory()->create(['registration_number' => 'DYNAMIC-RESERVED', 'status' => 'available']);
        $rented = Vehicle::factory()->create(['registration_number' => 'DYNAMIC-RENTED', 'status' => 'available']);
        $returned = Vehicle::factory()->create(['registration_number' => 'DYNAMIC-RETURNED', 'status' => 'rented']);
        $this->book($reserved, 'reserved');
        $this->book($rented, 'taken');
        $this->book($returned, 'returned');
        $grid = Livewire::actingAs($this->user)->test(VehicleTable::class);

        $grid->set('filters.select.status', 'reserved')->assertSee('DYNAMIC-RESERVED')
            ->assertDontSee('DYNAMIC-RENTED')->assertDontSee('DYNAMIC-RETURNED');
        $grid->set('filters.select.status', 'rented')->assertSee('DYNAMIC-RENTED')
            ->assertDontSee('DYNAMIC-RESERVED')->assertDontSee('DYNAMIC-RETURNED')
            ->call('exportToXLS')->assertFileDownloaded('vehicles.xlsx');
        $path = tempnam(sys_get_temp_dir(), 'vehicle-status-');

        try {
            file_put_contents($path, base64_decode($grid->effects['download']['content']));
            $workbook = IOFactory::load($path);
            $sheet = $workbook->getActiveSheet();
            $this->assertSame(2, $sheet->getHighestDataRow());
            $this->assertSame('DYNAMIC-RENTED', $sheet->getCell('B2')->getFormattedValue());
            $this->assertSame('Rented', $sheet->getCell('H2')->getFormattedValue());
            $workbook->disconnectWorksheets();
        } finally {
            unlink($path);
        }

        $grid->set('filters.select.status', 'available')->assertSee('DYNAMIC-RETURNED')
            ->assertDontSee('DYNAMIC-RESERVED')->assertDontSee('DYNAMIC-RENTED');
    }

    public function test_grid_sorts_by_current_status(): void
    {
        $reserved = Vehicle::factory()->create(['registration_number' => 'SORT-RESERVED', 'status' => 'available']);
        $rented = Vehicle::factory()->create(['registration_number' => 'SORT-RENTED', 'status' => 'available']);
        $returned = Vehicle::factory()->create(['registration_number' => 'SORT-AVAILABLE', 'status' => 'rented']);
        $this->book($reserved, 'reserved');
        $this->book($rented, 'taken');
        $this->book($returned, 'returned');

        Livewire::actingAs($this->user)->test(VehicleTable::class)->call('sortBy', 'current_status', 'asc')
            ->assertSeeInOrder(['SORT-AVAILABLE', 'SORT-RENTED', 'SORT-RESERVED']);
    }

    public function test_details_display_current_booking_status(): void
    {
        $vehicle = Vehicle::factory()->create(['status' => 'available']);
        $this->book($vehicle, 'reserved');

        $this->actingAsUser()->get(route('xpress.vehicles.show', $vehicle))->assertSee('Reserved');
    }

    public function test_computing_statuses_does_not_query_per_vehicle(): void
    {
        foreach (Vehicle::factory()->count(3)->create() as $vehicle) {
            $this->book($vehicle, 'reserved');
        }
        DB::enableQueryLog();
        DB::flushQueryLog();

        $statuses = Vehicle::query()->withCurrentStatus()->get()->pluck('current_status')->all();

        $this->assertSame(['reserved', 'reserved', 'reserved'], $statuses);
        $this->assertCount(1, DB::getQueryLog());
        DB::disableQueryLog();
    }

    private function book(Vehicle $vehicle, string $status): Booking
    {
        return Booking::factory()->create([
            'vehicle_id' => $vehicle->id, 'customer_id' => $this->otherUser->id,
            'user_id' => $this->user->id, 'booking_status' => $status,
        ]);
    }
}
