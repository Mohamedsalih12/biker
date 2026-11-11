<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * Guards against spreadsheet formula injection.
 *
 * Excel, LibreOffice and Google Sheets evaluate a cell whose value begins with
 * =, +, -, @, a tab, a carriage return or a space. A value stored through the
 * web forms or imported from a file that carries such a prefix is therefore
 * still live when it is written back out to a workbook, and it detonates on the
 * machine of whoever opens that workbook next.
 *
 * Neutralising on the way out rather than on the way in is deliberate. Leading
 * =, +, - and @ are all legitimate in real data (a phone number written
 * "+966…", a reference "=INV-2026-4"), so prefixing on write would corrupt the
 * database and leak a stray apostrophe into every screen that renders the field.
 * The export boundary is the single choke point every consumer passes through,
 * so guarding it there protects the workbook without touching the data.
 */
final class Spreadsheet
{
    /**
     * Force a value to be interpreted as text rather than as a formula.
     */
    public static function neutralize(mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        if (preg_match('/^[=\-+@\t\r ]/', $value) === 1) {
            return "'".$value;
        }

        return $value;
    }

    /**
     * Neutralize every cell of one sheet row.
     *
     * Accepts an object as well as an array because DB::select() hands back
     * stdClass rows.
     *
     * @param  array<array-key, mixed>|object  $row
     * @return array<array-key, mixed>
     */
    public static function neutralizeRow(array|object $row): array
    {
        return array_map([self::class, 'neutralize'], (array) $row);
    }

    /**
     * Neutralize every cell of every row in a sheet.
     *
     * Returns a Collection because Maatwebsite's FromCollection contract calls
     * ->all() on whatever collection() hands back.
     *
     * @param  iterable<array-key, array<array-key, mixed>|object>  $rows
     * @return Collection<int, array<array-key, mixed>>
     */
    public static function neutralizeRows(iterable $rows): Collection
    {
        $neutralized = [];

        foreach ($rows as $key => $row) {
            $neutralized[$key] = self::neutralizeRow($row);
        }

        return new Collection($neutralized);
    }

    /**
     * Clean a value read from an uploaded spreadsheet.
     *
     * Strips markup as well as neutralizing, because the source file is not
     * trusted the way a value typed into a form is.
     */
    public static function fromUpload(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        return self::neutralize(strip_tags($value));
    }
}
