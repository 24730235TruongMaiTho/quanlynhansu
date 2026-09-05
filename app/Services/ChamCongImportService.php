<?php

namespace App\Services;

use DateTime;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;

class ChamCongImportService
{
    private const TEMPLATE_HEADERS = [
        'ma_nv',
        'ngay_lam',
        'so_gio_lam',
        'vao_muon',
        've_som',
    ];

    /**
     * Import chấm công từ CSV / XLSX / XLS.
     */
    public function import(UploadedFile $file): array
    {
        try {
            $extension = strtolower($file->getClientOriginalExtension());

            if (! in_array($extension, ['csv', 'xlsx', 'xls'], true)) {
                return $this->error('File phải là CSV, XLSX hoặc XLS.');
            }

            if ($file->getSize() > 5 * 1024 * 1024) {
                return $this->error('File quá lớn, tối đa 5MB.');
            }

            $rows = $extension === 'csv'
                ? $this->parseCsv($file)
                : $this->parseExcel($file);

            if (empty($rows)) {
                return $this->error('File không chứa dữ liệu chấm công.');
            }

            return $this->validateAndImport($rows);
        } catch (\Throwable $exception) {
            report($exception);

            return $this->error(
                'Lỗi xử lý file: '.$exception->getMessage()
            );
        }
    }

    /**
     * Tạo template để người dùng tải về và nhập dữ liệu.
     *
     * @return string Đường dẫn tuyệt đối tới file tạm.
     */
    public function exportTemplate(string $format = 'xlsx'): string
    {
        $format = strtolower($format);

        if (! in_array($format, ['xlsx', 'csv'], true)) {
            throw new InvalidArgumentException(
                'Template chỉ hỗ trợ xlsx hoặc csv.'
            );
        }

        $directory = storage_path('app/temp/cham-cong');
        File::ensureDirectoryExists($directory);

        $filePath = $directory
            .'/mau_import_cham_cong_'
            .now()->format('Ymd_His')
            .'.'.$format;

        if ($format === 'csv') {
            $this->writeCsvTemplate($filePath);
        } else {
            $this->writeExcelTemplate($filePath);
        }

        return $filePath;
    }

    /**
     */
    /**
     * Template XLSX:
     * - Chỉ 1 sheet ChamCong.
     * - Chỉ có dòng header.
     * - Không có dữ liệu mẫu.
     * - Hướng dẫn format nằm trong comment header + validation.
     */
    private function writeExcelTemplate(string $filePath): void
    {
        $spreadsheet = new Spreadsheet();

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('ChamCong');

        // Chỉ tạo đúng dòng header, không tạo row mẫu.
        $sheet->fromArray(
            self::TEMPLATE_HEADERS,
            null,
            'A1'
        );

        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:E1');

        $sheet->getStyle('A1:E1')
            ->getFont()
            ->setBold(true);

        $sheet->getStyle('A1:E1')
            ->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()
            ->setARGB('FFEFF3F6');

        $sheet->getStyle('A1:E1000')
            ->getAlignment()
            ->setVertical(
                Alignment::VERTICAL_CENTER
            );

        $sheet->getColumnDimension('A')->setWidth(18);
        $sheet->getColumnDimension('B')->setWidth(18);
        $sheet->getColumnDimension('C')->setWidth(16);
        $sheet->getColumnDimension('D')->setWidth(14);
        $sheet->getColumnDimension('E')->setWidth(14);

        /*
         * Hướng dẫn nằm trực tiếp trong comment header.
         */
        $sheet->getComment('A1')
            ->getText()
            ->createTextRun(
                "Mã nhân viên.\n".
                "Bắt buộc.\n".
                "Phải tồn tại trong hệ thống."
            );

        $sheet->getComment('B1')
            ->getText()
            ->createTextRun(
                "Ngày làm.\n".
                "Bắt buộc.\n".
                "Định dạng: dd/mm/yyyy."
            );

        $sheet->getComment('C1')
            ->getText()
            ->createTextRun(
                "Số giờ làm.\n".
                "Bắt buộc.\n".
                "Giá trị từ 0 đến 8."
            );

        $sheet->getComment('D1')
            ->getText()
            ->createTextRun(
                "Vào muộn.\n".
                "Bắt buộc.\n".
                "0 = Không, 1 = Có."
            );

        $sheet->getComment('E1')
            ->getText()
            ->createTextRun(
                "Về sớm.\n".
                "Bắt buộc.\n".
                "0 = Không, 1 = Có."
            );

        /*
         * Format cột ngày.
         */
        $sheet->getStyle('B2:B1000')
            ->getNumberFormat()
            ->setFormatCode('dd/mm/yyyy');

        $dateValidation = new DataValidation();
        $dateValidation->setType(
            DataValidation::TYPE_DATE
        );
        $dateValidation->setErrorStyle(
            DataValidation::STYLE_STOP
        );
        $dateValidation->setAllowBlank(false);
        $dateValidation->setShowInputMessage(true);
        $dateValidation->setShowErrorMessage(true);
        $dateValidation->setErrorTitle(
            'Ngày không hợp lệ'
        );
        $dateValidation->setError(
            'Vui lòng nhập ngày hợp lệ.'
        );
        $dateValidation->setPromptTitle(
            'Định dạng ngày'
        );
        $dateValidation->setPrompt(
            'Nhập ngày theo dd/mm/yyyy.'
        );
        $dateValidation->setOperator(
            DataValidation::OPERATOR_BETWEEN
        );
        $dateValidation->setFormula1(
            'DATE(2000,1,1)'
        );
        $dateValidation->setFormula2(
            'DATE(2100,12,31)'
        );

        /*
         * Validate số giờ: 0 - 24.
         */
        $hoursValidation = new DataValidation();
        $hoursValidation->setType(
            DataValidation::TYPE_DECIMAL
        );
        $hoursValidation->setErrorStyle(
            DataValidation::STYLE_STOP
        );
        $hoursValidation->setAllowBlank(false);
        $hoursValidation->setShowInputMessage(true);
        $hoursValidation->setShowErrorMessage(true);
        $hoursValidation->setErrorTitle(
            'Số giờ không hợp lệ'
        );
        $hoursValidation->setError(
            'Số giờ làm phải từ 0 đến 8.'
        );
        $hoursValidation->setPromptTitle(
            'Số giờ làm'
        );
        $hoursValidation->setPrompt(
            'Nhập số giờ từ 0 đến 8.'
        );
        $hoursValidation->setOperator(
            DataValidation::OPERATOR_BETWEEN
        );
        $hoursValidation->setFormula1('0');
        $hoursValidation->setFormula2('8');

        /*
         * Validate vào muộn / về sớm: 0 hoặc 1.
         */
        $booleanValidation = new DataValidation();
        $booleanValidation->setType(
            DataValidation::TYPE_LIST
        );
        $booleanValidation->setErrorStyle(
            DataValidation::STYLE_STOP
        );
        $booleanValidation->setAllowBlank(false);
        $booleanValidation->setShowDropDown(true);
        $booleanValidation->setShowInputMessage(true);
        $booleanValidation->setShowErrorMessage(true);
        $booleanValidation->setErrorTitle(
            'Giá trị không hợp lệ'
        );
        $booleanValidation->setError(
            'Chỉ được nhập 0 hoặc 1.'
        );
        $booleanValidation->setPromptTitle(
            'Giá trị'
        );
        $booleanValidation->setPrompt(
            '0 = Không, 1 = Có.'
        );
        $booleanValidation->setFormula1(
            '"0,1"'
        );

        /*
         * Chỉ gán validation, KHÔNG set dữ liệu mẫu.
         */
        for ($row = 2; $row <= 1000; $row++) {
            $sheet->getCell("B{$row}")
                ->setDataValidation(
                    clone $dateValidation
                );

            $sheet->getCell("C{$row}")
                ->setDataValidation(
                    clone $hoursValidation
                );

            $sheet->getCell("D{$row}")
                ->setDataValidation(
                    clone $booleanValidation
                );

            $sheet->getCell("E{$row}")
                ->setDataValidation(
                    clone $booleanValidation
                );
        }

        $spreadsheet->setActiveSheetIndex(0);

        (new Xlsx($spreadsheet))
            ->save($filePath);

        $spreadsheet
            ->disconnectWorksheets();
    }

    private function writeCsvTemplate(string $filePath): void
    {
        $handle = fopen($filePath, 'wb');

        if ($handle === false) {
            throw new RuntimeException('Không thể tạo file CSV template.');
        }

        try {
            // UTF-8 BOM cho Excel Windows.
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, self::TEMPLATE_HEADERS);
        } finally {
            fclose($handle);
        }
    }

    private function parseCsv(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'rb');

        if ($handle === false) {
            throw new RuntimeException('Không thể đọc file CSV.');
        }

        try {
            $headerRow = fgetcsv($handle);

            if ($headerRow === false) {
                return [];
            }

            $header = $this->normalizeHeader($headerRow);
            $this->validateTemplateHeader($header);

            $rows = [];
            $rowNumber = 1;

            while (($row = fgetcsv($handle)) !== false) {
                $rowNumber++;

                if ($this->isEmptyRow($row)) {
                    continue;
                }

                $data = $this->combineRow($header, $row);

                if (array_key_exists('ngay_lam', $data)) {
                    $data['ngay_lam'] = $this->normalizeDateString(
                        trim((string) $data['ngay_lam'])
                    );
                }

                $rows[] = [
                    'row_num' => $rowNumber,
                    'data' => $data,
                ];
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }

    /**
     * Đọc Excel thật bằng PhpSpreadsheet.
     * File gốc trước đây dùng fgetcsv() cho .xlsx là không đúng.
     */
    private function parseExcel(UploadedFile $file): array
    {
        $spreadsheet = IOFactory::load($file->getRealPath());

        try {
            $sheet = $spreadsheet->getSheetByName('ChamCong')
                ?? $spreadsheet->getSheet(0);

            $highestRow = $sheet->getHighestDataRow();

            if ($highestRow < 1) {
                return [];
            }

            $header = $this->normalizeHeader([
                $sheet->getCell('A1')->getValue(),
                $sheet->getCell('B1')->getValue(),
                $sheet->getCell('C1')->getValue(),
                $sheet->getCell('D1')->getValue(),
                $sheet->getCell('E1')->getValue(),
            ]);

            $this->validateTemplateHeader($header);

            $rows = [];

            for ($rowNumber = 2; $rowNumber <= $highestRow; $rowNumber++) {
                $rawRow = [
                    $sheet->getCell("A{$rowNumber}")->getValue(),
                    $sheet->getCell("B{$rowNumber}")->getValue(),
                    $sheet->getCell("C{$rowNumber}")->getValue(),
                    $sheet->getCell("D{$rowNumber}")->getValue(),
                    $sheet->getCell("E{$rowNumber}")->getValue(),
                ];

                if ($this->isEmptyRow($rawRow)) {
                    continue;
                }

                $data = $this->combineRow($header, $rawRow);

                /*
                 * Chuẩn hóa ngày về Y-m-d.
                 *
                 * Hỗ trợ:
                 * - Excel serial date
                 * - 2026-08-27
                 * - 8/27/2026 (Excel locale US)
                 * - 08/27/2026
                 */
                $dateCell = $sheet->getCell("B{$rowNumber}");

                $data['ngay_lam'] =
                    $this->normalizeExcelDate(
                        $dateCell
                    );

                $rows[] = [
                    'row_num' => $rowNumber,
                    'data' => $data,
                ];
            }

            return $rows;
        } finally {
            $spreadsheet->disconnectWorksheets();
        }
    }

    private function normalizeHeader(array $header): array
    {
        return array_map(
            static function ($value): string {
                $value = trim((string) $value);
                $value = preg_replace('/^\xEF\xBB\xBF/', '', $value);

                return strtolower($value);
            },
            $header
        );
    }

    private function validateTemplateHeader(array $header): void
    {
        $missing = array_values(
            array_diff(self::TEMPLATE_HEADERS, $header)
        );

        if (! empty($missing)) {
            throw new InvalidArgumentException(
                'File không đúng template. Thiếu cột: '.implode(', ', $missing)
            );
        }
    }

    private function combineRow(array $header, array $row): array
    {
        $data = [];

        foreach ($header as $index => $column) {
            if ($column === '') {
                continue;
            }

            $value = $row[$index] ?? null;

            if (is_string($value)) {
                $value = trim($value);
            }

            $data[$column] = $value;
        }

        return $data;
    }

    private function isEmptyRow(array $row): bool
    {
        return empty(array_filter(
            $row,
            static fn ($value) =>
                $value !== null && trim((string) $value) !== ''
        ));
    }

    private function validateAndImport(array $rows): array
    {
        $errors = [];
        $validRows = [];

        // Load employee một lần, tránh query từng dòng.
        $employeeCodes = collect($rows)
            ->pluck('data.ma_nv')
            ->filter()
            ->map(static fn ($value) => trim((string) $value))
            ->unique()
            ->values();

        $existingEmployees = DB::table('nhan_vien')
            ->whereIn('ma_nv', $employeeCodes)
            ->pluck('ma_nv')
            ->map(static fn ($value) => (string) $value)
            ->flip();

        foreach ($rows as $item) {
            $rowNumber = $item['row_num'];
            $data = $item['data'];
            $rowErrors = [];

            $maNv = trim((string) ($data['ma_nv'] ?? ''));
            $ngayLam = trim((string) ($data['ngay_lam'] ?? ''));
            $soGioLam = $data['so_gio_lam'] ?? null;
            $vaoMuon = $data['vao_muon'] ?? null;
            $veSom = $data['ve_som'] ?? null;

            if ($maNv === '') {
                $rowErrors[] = 'Mã NV không được để trống.';
            } elseif (! $existingEmployees->has($maNv)) {
                $rowErrors[] = "Nhân viên {$maNv} không tồn tại.";
            }

            if ($ngayLam === '') {
                $rowErrors[] = 'Ngày làm không được để trống.';
            } elseif (! $this->isValidDate($ngayLam)) {
                $rowErrors[] = "Ngày làm '{$ngayLam}' không hợp lệ, định dạng dd/mm/yyyy.";
            }

            if (
                ! is_numeric($soGioLam)
                || (float) $soGioLam < 0
                || (float) $soGioLam > 8
            ) {
                $rowErrors[] = 'Số giờ làm phải từ 0 đến 8.';
            }

            if (! $this->isBinaryValue($vaoMuon)) {
                $rowErrors[] = 'Vào muộn phải là 0 hoặc 1.';
            }

            if (! $this->isBinaryValue($veSom)) {
                $rowErrors[] = 'Về sớm phải là 0 hoặc 1.';
            }

            if (! empty($rowErrors)) {
                $errors[$rowNumber] = $rowErrors;
                continue;
            }

            $validRows[] = [
                'row_num' => $rowNumber,
                'ma_nv' => $maNv,
                'ngay_lam' => $ngayLam,
                'so_gio_lam' => (float) $soGioLam,
                'vao_muon' => (int) $vaoMuon,
                've_som' => (int) $veSom,
            ];
        }

        $inserted = 0;
        $duplicates = 0;
        $imported = [];

        DB::transaction(function () use (
            $validRows,
            &$inserted,
            &$duplicates,
            &$imported
        ): void {
            foreach ($validRows as $row) {
                $exists = DB::table('cham_cong')
                    ->where('ma_nv', $row['ma_nv'])
                    ->whereDate('ngay_lam', $row['ngay_lam'])
                    ->exists();

                if ($exists) {
                    $duplicates++;
                    continue;
                }

                DB::table('cham_cong')->insert([
                    'ma_nv' => $row['ma_nv'],
                    'ngay_lam' => $row['ngay_lam'],
                    'so_gio_lam' => $row['so_gio_lam'],
                    'vao_muon' => $row['vao_muon'],
                    've_som' => $row['ve_som'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $inserted++;
                $imported[] = [
                    'row_num' => $row['row_num'],
                    'ma_nv' => $row['ma_nv'],
                    'ngay_lam' => $row['ngay_lam'],
                ];
            }
        });

        $message = "Nhập thành công {$inserted} bản ghi";

        if ($duplicates > 0) {
            $message .= ", bỏ qua {$duplicates} bản ghi trùng";
        }

        if (! empty($errors)) {
            $message .= ', có '.count($errors).' dòng không hợp lệ';
        }

        return [
            'success' => empty($errors),
            'message' => $message,
            'data' => [
                'inserted' => $inserted,
                'duplicates' => $duplicates,
                'invalid_rows' => count($errors),
                'total_rows' => count($rows),
                'rows' => $imported,
            ],
            'errors' => $errors,
        ];
    }

    private function isBinaryValue(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        return in_array((string) $value, ['0', '1'], true);
    }

    /**
     * Chuẩn hóa ngày từ Excel về YYYY-MM-DD.
     */
    private function normalizeExcelDate(
        \PhpOffice\PhpSpreadsheet\Cell\Cell $cell
    ): string {
        $value = $cell->getValue();

        /*
         * Excel date thật được lưu dưới dạng serial number.
         */
        if (
            is_numeric($value)
            && ExcelDate::isDateTime($cell)
        ) {
            return ExcelDate::excelToDateTimeObject(
                (float) $value
            )->format('Y-m-d');
        }

        return $this->normalizeDateString(trim((string) $value));
    }

    private function normalizeDateString(string $value): string
    {
        if ($value === '') {
            return '';
        }

        /*
         * Format chuẩn của hệ thống là ngày hiển thị dd/mm/yyyy. ISO vẫn
         * được nhận để tương thích với các file đã phát hành trước đây.
         */
        $formats = [
            'd/m/Y',
            'Y-m-d',

            /* Excel Windows / locale US có thể trả 8/27/2026. */
            'n/j/Y',
            'm/d/Y',
        ];

        foreach ($formats as $format) {
            $date = DateTime::createFromFormat('!'.$format, $value);

            if ($date !== false && $date->format($format) === $value) {
                return $date->format('Y-m-d');
            }
        }

        /* Không đoán format khác; validateAndImport() sẽ trả lỗi rõ ràng. */
        return $value;
    }

    private function isValidDate(string $date): bool
    {
        $parsed = DateTime::createFromFormat('Y-m-d', $date);

        return $parsed !== false
            && $parsed->format('Y-m-d') === $date;
    }

    private function error(string $message): array
    {
        return [
            'success' => false,
            'message' => $message,
            'data' => [],
            'errors' => [],
        ];
    }
}
