<?php

namespace Tests\Feature;

use App\Livewire\Admin\BookingTable;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BookingTableTest extends TestCase
{
    public function test_select_all_selects_the_visible_page_and_can_clear_selection(): void
    {
        $bookings = Booking::factory()->count(2)->create();

        Livewire::actingAs($this->user)->test(BookingTable::class)
            ->set('checkboxAll', true)->call('selectCheckboxAll')
            ->assertSet('checkboxValues', array_map('strval', $bookings->modelKeys()))
            ->set('checkboxAll', false)->call('selectCheckboxAll')
            ->assertSet('checkboxValues', []);
    }

    public function test_searches_booking_customer_and_vehicle_and_combines_filters(): void
    {
        $booking = Booking::factory()->create(['booking_status' => 'returned', 'payment_status' => 'pending', 'rental_type' => 'weekly']);
        $booking->customer->update(['name' => 'Unique Customer']);
        Booking::factory()->create(['booking_status' => 'returned', 'payment_status' => 'pending', 'rental_type' => 'monthly']);
        $grid = Livewire::actingAs($this->user)->test(BookingTable::class)
            ->set('filters.select.bookings.booking_status', 'returned')->set('filters.select.bookings.rental_type', 'weekly');
        foreach ([$booking->booking_code, 'Unique Customer', $booking->vehicle->registration_number] as $term) {
            $grid->set('search', $term)->assertSee($booking->booking_code);
        }
        $grid->set('filters.select.bookings.booking_status', 'cancelled')->assertDontSee($booking->booking_code);
    }

    public function test_index_shows_unreturned_or_unpaid_bookings(): void
    {
        $reserved = Booking::factory()->create(['booking_status' => 'reserved', 'payment_status' => 'paid']);
        $taken = Booking::factory()->create(['booking_status' => 'taken', 'payment_status' => 'paid']);
        $returnedPaid = Booking::factory()->create(['booking_status' => 'returned', 'payment_status' => 'paid']);
        Payment::factory()->for($returnedPaid)->create(['amount_received' => '2500.00']);
        $returnedPending = Booking::factory()->create(['booking_status' => 'returned', 'payment_status' => 'pending']);
        $returnedOverdue = Booking::factory()->create(['booking_status' => 'returned', 'payment_status' => 'overdue']);
        $cancelled = Booking::factory()->create(['booking_status' => 'cancelled', 'payment_status' => 'paid']);

        Livewire::actingAs($this->user)->test(BookingTable::class)
            ->assertSee($reserved->booking_code)
            ->assertSee($taken->booking_code)
            ->assertSee($cancelled->booking_code)
            ->assertSee($returnedPending->booking_code)
            ->assertSee($returnedOverdue->booking_code)
            ->assertDontSee($returnedPaid->booking_code);
    }

    public function test_grid_sorts_paginates_and_escapes_customer_content(): void
    {
        $customer = User::factory()->create(['name' => '<script>customer</script>', 'aadhaar' => '123456789012']);
        $bookings = Booking::factory()->count(17)->for($customer, 'customer')->sequence(fn ($sequence): array => ['rate_per_unit' => 100 + $sequence->index])->create();
        Livewire::actingAs($this->user)->test(BookingTable::class)
            ->assertSee($customer->name)->assertDontSee($customer->name, false)->assertDontSee('123456789012')
            ->call('sortBy', 'rate_per_unit', 'desc')->assertSee($bookings->last()->booking_code)->assertDontSee($bookings->first()->booking_code)
            ->call('gotoPage', 2)->assertSee($bookings->first()->booking_code)->assertDontSee($bookings->last()->booking_code);
    }

    public function test_guest_and_non_admin_cannot_mount_the_grid(): void
    {
        Livewire::test(BookingTable::class)->assertForbidden();
        foreach (['staff', 'customer'] as $role) {
            Livewire::actingAs(User::factory()->create(['role' => $role]))->test(BookingTable::class)->assertForbidden();
        }
    }

    #[DataProvider('protectedActions')]
    public function test_grid_rechecks_permissions_for_sensitive_actions(string $action): void
    {
        $booking = Booking::factory()->create();
        $grid = Livewire::actingAs($this->user)->test(BookingTable::class)->set('checkboxValues', [$booking->id]);
        $this->user->forceFill(['role' => 'staff'])->save();
        $grid->call($action)->assertForbidden();
        $this->assertModelExists($booking);
    }

    public static function protectedActions(): array
    {
        return ['export' => ['exportToXLS'], 'delete' => ['bulkDelete']];
    }

    public function test_bulk_delete_only_removes_selection_and_logs_the_committed_batch(): void
    {
        $bookings = Booking::factory()->count(2)->create();
        $kept = Booking::factory()->create();
        Livewire::actingAs($this->user)->test(BookingTable::class)->set('checkboxValues', $bookings->modelKeys())
            ->dispatch('bulkDelete')->assertRedirect('/admin/bookings');
        foreach ($bookings as $booking) {
            $this->assertModelMissing($booking);
            $this->assertDatabaseHas('deleted_models', ['model' => Booking::class, 'key' => (string) $booking->id]);
        }
        $this->assertModelExists($kept);
        $this->assertDatabaseHas('activity_log', ['log_name' => 'booking', 'event' => 'bulk_delete']);
    }

    #[DataProvider('invalidSelections')]
    public function test_invalid_selection_does_not_delete_any_bookings(array $selection, string $error): void
    {
        $booking = Booking::factory()->create();
        Livewire::actingAs($this->user)->test(BookingTable::class)->set('checkboxValues', $selection)->call('bulkDelete')->assertHasErrors($error);
        $this->assertModelExists($booking);
        $this->assertDatabaseMissing('activity_log', ['log_name' => 'booking', 'event' => 'bulk_delete']);
    }

    public static function invalidSelections(): array
    {
        return ['empty' => [[], 'ids'], 'missing' => [[999999], 'ids.0'], 'malformed' => [['bad'], 'ids.0'], 'duplicate' => [[1, 1], 'ids.0'], 'too many' => [range(1, 101), 'ids']];
    }

    public function test_foreign_key_failure_rolls_back_the_entire_batch(): void
    {
        Schema::create('booking_delete_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->restrictOnDelete();
        });
        $first = Booking::factory()->create();
        $linked = Booking::factory()->create();
        DB::table('booking_delete_links')->insert(['booking_id' => $linked->id]);
        Livewire::actingAs($this->user)->test(BookingTable::class)->set('checkboxValues', [$first->id, $linked->id])->call('bulkDelete')->assertHasErrors('ids');
        $this->assertModelExists($first);
        $this->assertModelExists($linked);
        $this->assertDatabaseMissing('deleted_models', ['model' => Booking::class, 'key' => (string) $first->id]);
        $this->assertDatabaseMissing('activity_log', ['log_name' => 'booking', 'event' => 'deleted']);
    }

    public function test_cancelled_delete_rolls_back_the_entire_batch(): void
    {
        $first = Booking::factory()->create();
        $cancelled = Booking::factory()->create();
        Booking::deleting(fn (Booking $booking): ?bool => $booking->id === $cancelled->id ? false : null);
        Livewire::actingAs($this->user)->test(BookingTable::class)->set('checkboxValues', [$first->id, $cancelled->id])->call('bulkDelete')->assertHasErrors('ids');
        $this->assertModelExists($first);
        $this->assertModelExists($cancelled);
        $this->assertDatabaseMissing('activity_log', ['log_name' => 'booking', 'event' => 'deleted']);
    }

    public function test_xlsx_exports_filtered_rows_beyond_current_page_and_preserves_text_safely(): void
    {
        $customer = User::factory()->create(['name' => '=HYPERLINK("https://example.com")', 'aadhaar' => '123456789012']);
        Booking::factory()->count(16)->for($customer, 'customer')->create(['booking_status' => 'returned', 'payment_status' => 'pending', 'rental_type' => 'daily', 'handover_notes' => '=SUM(1,2)']);
        $excluded = Booking::factory()->create(['booking_status' => 'returned', 'payment_status' => 'overdue', 'rental_type' => 'weekly']);
        $grid = Livewire::actingAs($this->user)->test(BookingTable::class)->set('search', 'HYPERLINK')
            ->set('filters.select.bookings.booking_status', 'returned')->set('filters.select.bookings.rental_type', 'daily')->call('exportToXLS')->assertFileDownloaded('bookings.xlsx');
        $path = tempnam(sys_get_temp_dir(), 'bookings-xlsx-');
        try {
            file_put_contents($path, base64_decode($grid->effects['download']['content']));
            $workbook = IOFactory::load($path);
            $sheet = $workbook->getActiveSheet();
            $this->assertSame(17, $sheet->getHighestDataRow());
            $this->assertSame('Booking Code', $sheet->getCell('B1')->getFormattedValue());
            $this->assertSame("'".$customer->name, $sheet->getCell('C2')->getFormattedValue());
            $this->assertNotSame(DataType::TYPE_FORMULA, $sheet->getCell('C2')->getDataType());
            $this->assertSame("'=SUM(1,2)", $sheet->getCell('O2')->getFormattedValue());
            $this->assertSame('P', $sheet->getHighestDataColumn());
            $this->assertStringNotContainsString('123456789012', json_encode($sheet->toArray()));
            $this->assertStringNotContainsString($excluded->booking_code, json_encode($sheet->toArray()));
            $workbook->disconnectWorksheets();
        } finally {
            unlink($path);
        }
        $this->assertDatabaseHas('activity_log', ['log_name' => 'booking', 'event' => 'export']);
    }

    public function test_xlsx_only_exports_selected_bookings(): void
    {
        $booking = Booking::factory()->create(['booking_status' => 'returned', 'payment_status' => 'pending']);
        Booking::factory()->create(['booking_status' => 'returned', 'payment_status' => 'pending']);
        $grid = Livewire::actingAs($this->user)->test(BookingTable::class)->set('checkboxValues', [$booking->id])->call('exportToXLS', true)->assertFileDownloaded('bookings.xlsx');
        $path = tempnam(sys_get_temp_dir(), 'bookings-selected-');
        try {
            file_put_contents($path, base64_decode($grid->effects['download']['content']));
            $workbook = IOFactory::load($path);
            $this->assertSame(2, $workbook->getActiveSheet()->getHighestDataRow());
            $this->assertSame($booking->booking_code, $workbook->getActiveSheet()->getCell('B2')->getFormattedValue());
            $workbook->disconnectWorksheets();
        } finally {
            unlink($path);
        }
    }

    public function test_empty_exports_report_validation_errors_without_logging_success(): void
    {
        $grid = Livewire::actingAs($this->user)->test(BookingTable::class);
        $grid->call('exportToXLS')->assertHasErrors('export');
        $grid->call('exportToXLS', true)->assertHasErrors('ids');
        $this->assertDatabaseMissing('activity_log', ['log_name' => 'booking', 'event' => 'export']);
    }
}
