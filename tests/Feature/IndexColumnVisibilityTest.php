<?php

namespace Tests\Feature;

use App\Livewire\Admin\AllBookingTable;
use App\Livewire\Admin\BookingTable;
use App\Livewire\Admin\UserTable;
use App\Livewire\Admin\VehicleTable;
use App\Models\Booking;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Facades\Cookie;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class IndexColumnVisibilityTest extends TestCase
{
    #[DataProvider('indexes')]
    public function test_columns_can_be_hidden_shown_and_remembered_per_user(string $component, string $tableName): void
    {
        $grid = Livewire::actingAs($this->user)->test($component)
            ->assertSeeHtml('data-cy="toggle-columns-'.$tableName.'"')
            ->assertSeeHtml('data-cy="toggle-field-id"');

        $grid->call('toggleColumn', 'id');

        $this->assertTrue(data_get(collect($grid->get('columns'))->firstWhere('field', 'id'), 'hidden'));
        $cookieName = 'pg:user-'.$this->user->id.'-'.$tableName;
        $cookie = Cookie::queued($cookieName);
        $this->assertNotNull($cookie);
        $cookies = [$cookieName => $cookie->getValue()];
        Livewire::withCookies($cookies)->test($component)
            ->assertSet('columns', fn (array $columns): bool => data_get(collect($columns)->firstWhere('field', 'id'), 'hidden') === true)
            ->call('toggleColumn', 'id')
            ->assertSet('columns', fn (array $columns): bool => data_get(collect($columns)->firstWhere('field', 'id'), 'hidden') === false);

        $anotherAdmin = User::factory()->admin()->create();
        Livewire::actingAs($anotherAdmin)->withCookies($cookies)->test($component)
            ->assertSet('columns', fn (array $columns): bool => data_get(collect($columns)->firstWhere('field', 'id'), 'hidden') === false);
    }

    public function test_booking_indexes_remember_columns_independently(): void
    {
        Livewire::actingAs($this->user)->test(BookingTable::class)->call('toggleColumn', 'id');
        $cookieName = 'pg:user-'.$this->user->id.'-booking-table';
        $cookie = Cookie::queued($cookieName);
        $this->assertNotNull($cookie);

        Livewire::withCookies([$cookieName => $cookie->getValue()])->test(AllBookingTable::class)
            ->assertSet('columns', fn (array $columns): bool => data_get(collect($columns)->firstWhere('field', 'id'), 'hidden') === false);
    }

    #[DataProvider('bookingIndexes')]
    public function test_booking_notes_are_hidden_by_default_but_selectable(string $component): void
    {
        $grid = Livewire::actingAs($this->user)->test($component)
            ->assertSeeHtml('data-cy="toggle-field-handover_notes"')
            ->assertSeeHtml('data-cy="toggle-field-return_notes"');

        foreach (['handover_notes', 'return_notes'] as $field) {
            $grid->assertSet('columns', fn (array $columns): bool => data_get(collect($columns)->firstWhere('field', $field), 'hidden') === true)
                ->call('toggleColumn', $field)
                ->assertSet('columns', fn (array $columns): bool => data_get(collect($columns)->firstWhere('field', $field), 'hidden') === false);
        }
    }

    public function test_internal_image_path_is_not_offered_in_the_chooser(): void
    {
        Livewire::actingAs($this->user)->test(VehicleTable::class)
            ->assertSeeHtml('data-cy="toggle-field-image_preview"')
            ->assertDontSeeHtml('data-cy="toggle-field-image"');
    }

    #[DataProvider('indexes')]
    public function test_hiding_columns_does_not_change_excel_export_columns(string $component, string $tableName): void
    {
        $expectedHeaders = match ($component) {
            UserTable::class => ['ID', 'Name', 'Email', 'Phone', 'Role', 'Active Status'],
            VehicleTable::class => ['ID', 'Registration Number', 'Brand', 'Model', 'Chassis Number', 'Battery Serial Number', 'Odometer Reading', 'Status', 'Purchase Date', 'Purchase Cost', 'Insurance Policy Number', 'Insurance Expires At', 'Image Path', 'Damages'],
            default => ['ID', 'Booking Code', 'Customer', 'Vehicle Registration', 'Created By', 'Rental Type', 'Rate per Unit', 'Start Date', 'End Date', 'Actual Return Date', 'Start Odometer', 'End Odometer', 'Booking Status', 'Payment Status', 'Handover Notes', 'Return Notes'],
        };
        if ($component === VehicleTable::class) {
            Vehicle::factory()->create();
        } elseif (in_array($component, [BookingTable::class, AllBookingTable::class], true)) {
            Booking::factory()->create();
        }
        $grid = Livewire::actingAs($this->user)->test($component);
        foreach ($grid->get('columns') as $column) {
            if (! data_get($column, 'hidden') && data_get($column, 'visibleInExport') !== false) {
                $grid->call('toggleColumn', data_get($column, 'field'));
            }
        }

        $grid->call('exportToXLS')->assertFileDownloaded();

        $path = tempnam(sys_get_temp_dir(), 'column-export-');
        try {
            file_put_contents($path, base64_decode($grid->effects['download']['content']));
            $workbook = IOFactory::load($path);
            $this->assertSame($expectedHeaders, $workbook->getActiveSheet()->toArray()[0]);
            $workbook->disconnectWorksheets();
        } finally {
            unlink($path);
        }
    }

    public static function indexes(): array
    {
        return [
            'users' => [UserTable::class, 'user-table'],
            'vehicles' => [VehicleTable::class, 'vehicle-table'],
            'bookings' => [BookingTable::class, 'booking-table'],
            'all bookings' => [AllBookingTable::class, 'all-booking-table'],
        ];
    }

    public static function bookingIndexes(): array
    {
        return [
            'bookings' => [BookingTable::class],
            'all bookings' => [AllBookingTable::class],
        ];
    }
}
