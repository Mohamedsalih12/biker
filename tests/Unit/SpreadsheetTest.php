<?php

namespace Tests\Unit;

use App\Support\Spreadsheet;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SpreadsheetTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function formulaProvider(): array
    {
        return [
            'equals' => ['=1+1', "'=1+1"],
            'plus' => ['+966512345678', "'+966512345678"],
            'minus' => ['-5', "'-5"],
            'at sign' => ['@SUM(A1)', "'@SUM(A1)"],
            'tab prefix' => ["\t=1+1", "'\t=1+1"],
            'carriage return prefix' => ["\r=1+1", "'\r=1+1"],
            'space prefix' => [' =1+1', "' =1+1"],
            'hyperlink' => [
                '=HYPERLINK("http://evil.tld","click")',
                '\'=HYPERLINK("http://evil.tld","click")',
            ],
        ];
    }

    #[DataProvider('formulaProvider')]
    public function test_it_prefixes_known_formula_triggers(string $input, string $expected): void
    {
        $this->assertSame($expected, Spreadsheet::neutralize($input));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function safeValueProvider(): array
    {
        return [
            'plain text' => ['Warehouse Central'],
            'equals mid string' => ['a=b'],
            'plus mid string' => ['1+1'],
            'at mid string' => ['user@example.com'],
            'url' => ['https://example.com/a?b=1'],
            'leading digit' => ['2026-01-15'],
        ];
    }

    #[DataProvider('safeValueProvider')]
    public function test_it_leaves_safe_values_untouched(string $input): void
    {
        $this->assertSame($input, Spreadsheet::neutralize($input));
    }

    public function test_it_passes_through_non_strings_and_empty_strings(): void
    {
        $this->assertNull(Spreadsheet::neutralize(null));
        $this->assertSame(123, Spreadsheet::neutralize(123));
        $this->assertSame(1.5, Spreadsheet::neutralize(1.5));
        $this->assertTrue(Spreadsheet::neutralize(true));
        $this->assertSame('', Spreadsheet::neutralize(''));
    }

    public function test_it_leaves_an_already_neutralized_value_alone(): void
    {
        // A second pass must not stack apostrophes, otherwise a value that has
        // been through an export twice would be visibly corrupted.
        $this->assertSame("'=1+1", Spreadsheet::neutralize("'=1+1"));
    }

    public function test_it_neutralizes_every_cell_of_every_row(): void
    {
        $rows = [
            ['=1+1', 'Safe', '@SUM(A1)'],
            ['ok', '+1', 'a=b'],
        ];

        $this->assertSame([
            ["'=1+1", 'Safe', "'@SUM(A1)"],
            ['ok', "'+1", 'a=b'],
        ], Spreadsheet::neutralizeRows($rows)->all());
    }

    public function test_it_returns_a_collection_because_from_collection_requires_it(): void
    {
        $this->assertInstanceOf(Collection::class, Spreadsheet::neutralizeRows([]));
    }

    public function test_it_preserves_row_keys(): void
    {
        $rows = ['a' => ['=1'], 'b' => ['safe']];

        $this->assertSame(['a', 'b'], Spreadsheet::neutralizeRows($rows)->keys()->all());
    }

    public function test_it_handles_object_rows_from_a_raw_query(): void
    {
        $row = (object) ['region_name' => '=1+1', 'pos_code' => 'P1'];

        $this->assertSame(
            ['region_name' => "'=1+1", 'pos_code' => 'P1'],
            Spreadsheet::neutralizeRow($row)
        );
    }

    public function test_from_upload_also_strips_markup(): void
    {
        $this->assertSame('<b>bold</b>', Spreadsheet::neutralize('<b>bold</b>'));
        $this->assertSame('bold', Spreadsheet::fromUpload('<b>bold</b>'));
    }

    public function test_from_upload_neutralizes_after_stripping(): void
    {
        $this->assertSame("'=1+1", Spreadsheet::fromUpload('=1+1'));
        $this->assertSame("'=1+1", Spreadsheet::fromUpload('<i>=1+1</i>'));
    }

    public function test_from_upload_passes_through_non_strings(): void
    {
        $this->assertNull(Spreadsheet::fromUpload(null));
        $this->assertSame(7, Spreadsheet::fromUpload(7));
    }
}
