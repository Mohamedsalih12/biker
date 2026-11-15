<?php

namespace Tests\Feature;

use App\Livewire\Admin\AllBookingTable;
use App\Livewire\Admin\BookingTable;
use App\Livewire\Admin\PaymentTable;
use App\Livewire\Admin\UserTable;
use App\Livewire\Admin\VehicleTable;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Vehicle;
use Dom\HTMLDocument;
use Dom\XPath;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class IndexNumberFormattingTest extends TestCase
{
    #[DataProvider('bookingTables')]
    public function test_booking_indexes_format_rates_and_odometers_without_changing_export_values(string $component): void
    {
        Booking::factory()->create(['rate_per_unit' => '12345.67', 'start_odometer' => 23456, 'end_odometer' => null]);

        $grid = Livewire::actingAs($this->user)->test($component)
            ->assertSee('12,346')->assertSee('23,456')->assertDontSee('12,345.67');

        $this->assertNumberColumnsAligned($grid->html(), ['bookings.rate_per_unit', 'bookings.start_odometer', 'bookings.end_odometer']);
        $row = $grid->instance()->prepareToExport()->first();
        $this->assertSame('12345.67', $row->rate_per_unit);
        $this->assertSame(23456, $row->start_odometer);
        $this->assertNull($row->end_odometer);
    }

    public static function bookingTables(): array
    {
        return ['bookings' => [BookingTable::class], 'all bookings' => [AllBookingTable::class]];
    }

    public function test_vehicle_index_formats_odometer_cost_and_earnings(): void
    {
        $vehicle = Vehicle::factory()->create(['odometer_reading' => 34567, 'purchase_cost' => '45678.90']);
        Payment::factory()->create(['vehicle_id' => $vehicle->id, 'amount_received' => '12345.67']);

        $grid = Livewire::actingAs($this->user)->test(VehicleTable::class)
            ->assertSee('34,567')->assertSee('45,679')->assertSee('12,346');

        $this->assertNumberColumnsAligned($grid->html(), ['odometer_reading', 'purchase_cost', 'total_amount_earned']);
        $row = $grid->instance()->prepareToExport()->first();
        $this->assertSame('45678.90', $row->purchase_cost);
        $this->assertSame(34567, $row->odometer_reading);
    }

    public function test_payment_and_user_indexes_format_amounts_and_earnings(): void
    {
        $payment = Payment::factory()->create(['amount_received' => '12345.67']);

        $grid = Livewire::actingAs($this->user)->test(PaymentTable::class)->assertSee('12,346');
        $users = Livewire::actingAs($this->user)->test(UserTable::class)
            ->set('search', $payment->customer->email)->assertSee('12,346');

        $this->assertNumberColumnsAligned($grid->html(), ['payments.amount_received']);
        $this->assertNumberColumnsAligned($users->html(), ['total_amount_earned']);
        $this->assertSame('12345.67', $grid->instance()->prepareToExport()->first()->amount_received);
    }

    /** @param list<string> $fields */
    private function assertNumberColumnsAligned(string $html, array $fields): void
    {
        $xpath = new XPath(HTMLDocument::createFromString($html, LIBXML_NOERROR));

        foreach ($fields as $field) {
            foreach (['th', 'td'] as $tag) {
                $cells = $xpath->query("//*[local-name() = '$tag' and @data-column = '$field']");
                $this->assertGreaterThan(0, $cells->length);
                foreach ($cells as $cell) {
                    $this->assertContains('text-end', explode(' ', $cell->getAttribute('class')));
                }
            }
        }
    }
}
