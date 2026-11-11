<?php

namespace Tests\Support;

use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use RuntimeException;

trait CreatesExcelUploads
{
    /**
     * @param  array<int, array<int, int|float|string|null>>  $rows
     */
    protected function makeExcelUpload(string $filename, array $rows): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'excel-import-');
        if ($path === false) {
            throw new RuntimeException('Unable to create a temporary Excel upload.');
        }

        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray($rows);
        (new XlsxWriter($spreadsheet))->save($path);

        return new UploadedFile(
            $path,
            $filename,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
    }
}
