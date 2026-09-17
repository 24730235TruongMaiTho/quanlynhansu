<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ChamCongExportService
{
    private const EXPORT_HEADERS = [
        'ma_nv',
        'ngay_lam',
        'so_gio_lam',
        'vao_muon',
        've_som',
    ];

    /**
     * Export chấm công từ SP sang Excel format CSV
     *
     * @param int $month Tháng (1-12)
     * @param int $year Năm
     * @return string Path to exported file
     */
    public function exportToCSV(int $month, int $year): string
    {
        $rows = $this->rowsForPeriod($month, $year);

        // Tạo file CSV
        $filename = "cham_cong_{$month}_{$year}.csv";
        $filePath = storage_path("exports/{$filename}");

        // Tạo thư mục nếu chưa tồn tại
        @mkdir(dirname($filePath), 0755, true);

        // Ghi CSV
        $file = fopen($filePath, 'w');

        // BOM for UTF-8
        fwrite($file, chr(0xEF).chr(0xBB).chr(0xBF));

        // Header
        fputcsv($file, self::EXPORT_HEADERS, ',');

        // Dữ liệu
        foreach ($rows as $row) {
            fputcsv($file, [
                $row['ma_nv'],
                $row['ngay_lam'],
                $row['so_gio_lam'],
                (int)$row['vao_muon'],
                (int)$row['ve_som'],
            ], ',');
        }

        fclose($file);

        return $filePath;
    }

    /**
     * Export chấm công sang Excel (XLSX) format
     *
     * @param int $month Tháng
     * @param int $year Năm
     * @return string Path to exported file
     */
    public function exportToExcel(int $month, int $year): string
    {
        $rows = $this->rowsForPeriod($month, $year);

        // Tạo file Excel bằng PhpSpreadsheet
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Chấm công');

        // Header row
        $sheet->fromArray(self::EXPORT_HEADERS, null, 'A1');

        // Style header
        $sheet->getStyle('A1:E1')
            ->getFont()
            ->setBold(true);

        $sheet->getStyle('A1:E1')
            ->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()
            ->setARGB('FFEFF3F6');

        // Set column widths
        $sheet->getColumnDimension('A')->setWidth(18);
        $sheet->getColumnDimension('B')->setWidth(18);
        $sheet->getColumnDimension('C')->setWidth(16);
        $sheet->getColumnDimension('D')->setWidth(14);
        $sheet->getColumnDimension('E')->setWidth(14);

        // Data rows
        $rowNum = 2;
        foreach ($rows as $row) {
            // Employee codes are identifiers; keep leading zeroes on export.
            $sheet->setCellValueExplicit("A{$rowNum}", (string) $row['ma_nv'], DataType::TYPE_STRING);
            $sheet->setCellValue("B{$rowNum}", $row['ngay_lam']);
            $sheet->setCellValue("C{$rowNum}", (float)$row['so_gio_lam']);
            $sheet->setCellValue("D{$rowNum}", (int)$row['vao_muon']);
            $sheet->setCellValue("E{$rowNum}", (int)$row['ve_som']);
            $rowNum++;
        }

        // Tạo file
        $filename = "cham_cong_{$month}_{$year}.xlsx";
        $filePath = storage_path("exports/{$filename}");

        @mkdir(dirname($filePath), 0755, true);

        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        $spreadsheet->disconnectWorksheets();

        return $filePath;
    }


    /**
     * Export template for import
     * CSV file with headers and sample data
     *
     * @return string Path to template file
     */
    public function exportTemplate(): string
    {
        $filename = "cham_cong_template.csv";
        $filePath = storage_path("exports/{$filename}");

        @mkdir(dirname($filePath), 0755, true);

        $file = fopen($filePath, 'w');

        // BOM for UTF-8
        fwrite($file, chr(0xEF).chr(0xBB).chr(0xBF));

        // Header row with descriptions
        fputcsv($file, [
            'ma_nv',
            'ngay_lam',
            'so_gio_lam',
            'vao_muon',
            've_som'
        ], ',');

        // Sample rows
        $samples = [
            ['00001', '2026-08-01', '8', '0', '0'],
            ['00002', '2026-08-02', '8', '1', '0'],
            ['00003', '2026-08-03', '8', '0', '1'],
        ];

        foreach ($samples as $sample) {
            fputcsv($file, $sample, ',');
        }

        fclose($file);

        return $filePath;
    }

    /**
     * Read the active attendance columns using a portable half-open date
     * range. This keeps CSV and XLSX exports identical on SQLite and MariaDB.
     *
     * @return array<int, array<string, mixed>>
     */
    private function rowsForPeriod(int $month, int $year): array
    {
        if ($month < 1 || $month > 12) {
            throw new \InvalidArgumentException('Tháng không hợp lệ.');
        }

        $start = sprintf('%04d-%02d-01', $year, $month);
        $nextMonth = $month === 12
            ? sprintf('%04d-01-01', $year + 1)
            : sprintf('%04d-%02d-01', $year, $month + 1);

        return DB::table('cham_cong')
            ->where('ngay_lam', '>=', $start)
            ->where('ngay_lam', '<', $nextMonth)
            ->orderBy('ma_nv')
            ->orderBy('ngay_lam')
            ->get(['ma_nv', 'ngay_lam', 'so_gio_lam', 'vao_muon', 've_som'])
            ->map(static fn ($row): array => [
                'ma_nv' => (string) $row->ma_nv,
                'ngay_lam' => (string) $row->ngay_lam,
                'so_gio_lam' => $row->so_gio_lam,
                'vao_muon' => (int) $row->vao_muon,
                've_som' => (int) $row->ve_som,
            ])
            ->all();
    }
}
