<?php

namespace Tests\Feature\Livewire\Admin;

use App\Livewire\Admin\UserTable;
use App\Models\User;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class UserTableExportTest extends TestCase
{
    public function test_xlsx_contains_only_public_columns_and_neutralizes_formulas(): void
    {
        $user = User::factory()->create([
            'name' => '=HYPERLINK("https://example.com")',
            'email' => '@dangerous.example',
            'phone' => '+94112345678',
            'role' => 'staff',
            'google2fa_secret' => 'PRIVATE-MFA-SECRET',
        ]);
        $grid = Livewire::actingAs($this->user)->test(UserTable::class)
            ->set('checkboxValues', [$user->id])
            ->call('exportToXLS', true)
            ->assertFileDownloaded('users.xlsx');
        $path = tempnam(sys_get_temp_dir(), 'users-xlsx-');

        try {
            file_put_contents($path, base64_decode($grid->effects['download']['content']));
            $workbook = IOFactory::load($path);
            $sheet = $workbook->getActiveSheet();

            $this->assertSame(['ID', 'Name', 'Email', 'Phone', 'Role', 'Active Status'], $sheet->rangeToArray('A1:F1')[0]);
            $this->assertSame("'".$user->name, $sheet->getCell('B2')->getFormattedValue());
            $this->assertSame("'@dangerous.example", $sheet->getCell('C2')->getFormattedValue());
            $this->assertSame("'+94112345678", $sheet->getCell('D2')->getFormattedValue());
            $this->assertNotSame(DataType::TYPE_FORMULA, $sheet->getCell('B2')->getDataType());
            $this->assertSame('Staff', $sheet->getCell('E2')->getFormattedValue());
            $this->assertSame('Active', $sheet->getCell('F2')->getFormattedValue());
            $this->assertSame('F', $sheet->getHighestDataColumn());
            $this->assertSame(2, $sheet->getHighestDataRow());
            $this->assertTrue($sheet->getStyle('A1')->getFont()->getBold());
            $workbook->disconnectWorksheets();
        } finally {
            unlink($path);
        }
    }

    public function test_export_preserves_numeric_phone_numbers_as_text_and_handles_missing_email(): void
    {
        $user = User::factory()->create(['email' => null, 'phone' => '001234567890123456789', 'active_status' => false]);
        $grid = Livewire::actingAs($this->user)->test(UserTable::class)
            ->set('checkboxValues', [$user->id])
            ->call('exportToXLS', true)
            ->assertFileDownloaded('users.xlsx');
        $path = tempnam(sys_get_temp_dir(), 'users-xlsx-');

        try {
            file_put_contents($path, base64_decode($grid->effects['download']['content']));
            $workbook = IOFactory::load($path);
            $sheet = $workbook->getActiveSheet();

            $this->assertSame('001234567890123456789', $sheet->getCell('D2')->getFormattedValue());
            $this->assertNotSame(DataType::TYPE_NUMERIC, $sheet->getCell('D2')->getDataType());
            $this->assertSame('Inactive', $sheet->getCell('F2')->getFormattedValue());
            $this->assertEmpty($sheet->getCell('C2')->getFormattedValue());
            $workbook->disconnectWorksheets();
        } finally {
            unlink($path);
        }
    }
}
