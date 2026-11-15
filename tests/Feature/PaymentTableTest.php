<?php

namespace Tests\Feature;

use App\Livewire\Admin\PaymentTable;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PaymentTableTest extends TestCase
{
    public function test_search_filter_sort_and_pagination(): void
    {
        $payment = Payment::factory()->create(['payment_method' => 'upi', 'notes' => 'Unique receipt', 'payment_date' => '2026-11-15']);
        Payment::factory()->create(['payment_method' => 'cash', 'notes' => 'Excluded receipt']);
        $grid = Livewire::actingAs($this->user)->test(PaymentTable::class)->set('filters.select.payments.payment_method', 'upi');
        foreach ([$payment->booking->booking_code, $payment->customer->name, $payment->vehicle->registration_number, 'Unique receipt'] as $term) {
            $grid->set('search', $term)->assertSee('Unique receipt')->assertDontSee('Excluded receipt');
        }
        $grid->set('filters.select.payments.payment_method', 'other')->assertDontSee('Unique receipt');
        $payments = Payment::factory()->count(17)->sequence(fn ($sequence): array => ['amount_received' => 100 + $sequence->index])->create();
        Livewire::actingAs($this->user)->test(PaymentTable::class)->call('sortBy', 'payments.amount_received', 'asc')
            ->assertSee($payments->first()->booking->booking_code)->assertDontSee($payments->last()->booking->booking_code)
            ->call('gotoPage', 2)->assertSee($payments->last()->booking->booking_code);
    }

    public function test_grid_escapes_notes_and_customer_names(): void
    {
        $payment = Payment::factory()->create(['notes' => '<script>receipt</script>']);
        $payment->customer->update(['name' => '<script>customer</script>']);
        Livewire::actingAs($this->user)->test(PaymentTable::class)
            ->assertSee('<script>receipt</script>')->assertDontSee('<script>receipt</script>', false)
            ->assertSee('<script>customer</script>')->assertDontSee('<script>customer</script>', false);
    }

    public function test_payment_date_filter_works_without_an_additional_javascript_plugin(): void
    {
        Payment::factory()->create(['payment_date' => '2026-11-15', 'notes' => 'Matching date']);
        Payment::factory()->create(['payment_date' => '2026-11-14', 'notes' => 'Other date']);
        Livewire::actingAs($this->user)->test(PaymentTable::class)
            ->assertSee('placeholder="YYYY-MM-DD"', false)
            ->set('filters.input_text.payments.payment_date', '2026-11-15')
            ->assertSee('Matching date')->assertDontSee('Other date');
    }

    public function test_guest_and_non_admin_cannot_mount_grid(): void
    {
        Livewire::test(PaymentTable::class)->assertForbidden();
        Livewire::actingAs($this->otherUser)->test(PaymentTable::class)->assertForbidden();
    }

    #[DataProvider('sensitiveActions')]
    public function test_sensitive_actions_recheck_authorization(string $action): void
    {
        $payment = Payment::factory()->create();
        $grid = Livewire::actingAs($this->user)->test(PaymentTable::class)->set('checkboxValues', [$payment->id]);
        $this->user->forceFill(['role' => 'staff'])->save();
        $grid->call($action)->assertForbidden();
        $this->assertModelExists($payment);
    }

    public static function sensitiveActions(): array
    {
        return [['bulkDelete'], ['exportToXLS'], ['prepareToExport']];
    }

    public function test_select_all_and_bulk_delete_only_remove_selected_records(): void
    {
        $payments = Payment::factory()->count(2)->create();
        $grid = Livewire::actingAs($this->user)->test(PaymentTable::class)->set('checkboxAll', true)->call('selectCheckboxAll')
            ->assertSet('checkboxValues', array_map('strval', $payments->modelKeys()));
        $kept = Payment::factory()->create();
        $grid->dispatch('bulkDelete')->assertRedirect('/admin/payments');
        foreach ($payments as $payment) {
            $this->assertModelMissing($payment);
            $this->assertDatabaseHas('deleted_models', ['model' => Payment::class, 'key' => (string) $payment->id]);
        }
        $this->assertModelExists($kept);
        $this->assertDatabaseHas('activity_log', ['log_name' => 'payment', 'event' => 'bulk_delete']);
    }

    #[DataProvider('invalidSelections')]
    public function test_invalid_selection_cannot_delete_payments(array $ids, string $error): void
    {
        $payment = Payment::factory()->create();
        Livewire::actingAs($this->user)->test(PaymentTable::class)->set('checkboxValues', $ids)->call('bulkDelete')->assertHasErrors($error);
        $this->assertModelExists($payment);
        $this->assertDatabaseMissing('activity_log', ['log_name' => 'payment', 'event' => 'bulk_delete']);
    }

    public static function invalidSelections(): array
    {
        return [[[], 'ids'], [[999999], 'ids.0'], [['bad'], 'ids.0'], [[1, 1], 'ids.0'], [range(1, 101), 'ids']];
    }

    public function test_failed_delete_rolls_back_records_archives_and_logs(): void
    {
        $first = Payment::factory()->create();
        $second = Payment::factory()->create();
        Payment::deleting(fn (Payment $payment): ?bool => $payment->id === $second->id ? false : null);
        Livewire::actingAs($this->user)->test(PaymentTable::class)->set('checkboxValues', [$first->id, $second->id])->call('bulkDelete')->assertHasErrors('ids');
        $this->assertModelExists($first);
        $this->assertModelExists($second);
        $this->assertDatabaseMissing('deleted_models', ['model' => Payment::class]);
        $this->assertDatabaseMissing('activity_log', ['log_name' => 'payment', 'event' => 'deleted']);
    }

    public function test_filtered_export_includes_all_pages_and_neutralizes_formulas(): void
    {
        $customer = User::factory()->create(['name' => '=SUM(1,2)']);
        $booking = Booking::factory()->for($customer, 'customer')->create();
        Payment::factory()->count(16)->for($booking)->create(['payment_method' => 'upi', 'notes' => '=SUM(2,3)']);
        Payment::factory()->create(['payment_method' => 'cash', 'notes' => 'EXCLUDED']);
        $grid = Livewire::actingAs($this->user)->test(PaymentTable::class)->set('filters.select.payments.payment_method', 'upi')
            ->set('search', 'SUM')->call('exportToXLS')->assertFileDownloaded('payments.xlsx');
        $path = tempnam(sys_get_temp_dir(), 'payments-');
        try {
            file_put_contents($path, base64_decode($grid->effects['download']['content']));
            $workbook = IOFactory::load($path);
            $sheet = $workbook->getActiveSheet();
            $this->assertSame(17, $sheet->getHighestDataRow());
            $this->assertSame('Booking Code', $sheet->getCell('B1')->getFormattedValue());
            $this->assertSame("'=SUM(1,2)", $sheet->getCell('F2')->getFormattedValue());
            $this->assertSame("'=SUM(2,3)", $sheet->getCell('I2')->getFormattedValue());
            $this->assertNotSame(DataType::TYPE_FORMULA, $sheet->getCell('I2')->getDataType());
            $this->assertStringNotContainsString('EXCLUDED', json_encode($sheet->toArray()));
            $workbook->disconnectWorksheets();
        } finally {
            unlink($path);
        }
        $this->assertDatabaseHas('activity_log', ['log_name' => 'payment', 'event' => 'export']);
    }

    public function test_selected_export_only_contains_selected_payment(): void
    {
        $payment = Payment::factory()->create();
        Payment::factory()->create();
        $grid = Livewire::actingAs($this->user)->test(PaymentTable::class)->set('checkboxValues', [$payment->id]);
        $rows = $grid->instance()->prepareToExport(true);
        $this->assertSame([$payment->id], $rows->pluck('id')->all());
    }

    public function test_empty_export_and_selection_report_errors_without_success_log(): void
    {
        $grid = Livewire::actingAs($this->user)->test(PaymentTable::class);
        $grid->call('exportToXLS')->assertHasErrors('export');
        $grid->call('exportToXLS', true)->assertHasErrors('ids');
        $this->assertDatabaseMissing('activity_log', ['log_name' => 'payment', 'event' => 'export']);
    }
}
