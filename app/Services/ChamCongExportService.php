<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use SplFileObject;
use ZipArchive;

class ChamCongExportService
{
    /**
     * Export chấm công từ SP sang Excel format CSV
     *
     * @param int $month Tháng (1-12)
     * @param int $year Năm
     * @return string Path to exported file
     */
    public function exportToCSV(int $month, int $year): string
    {
        $pdo = DB::connection()->getPdo();

        // Query dữ liệu từ chấm công theo tháng, năm
        $statement = $pdo->prepare(
            'SELECT ma_nv, ngay_lam, so_gio_lam, vao_muon, ve_som
             FROM cham_cong
             WHERE MONTH(ngay_lam) = ? AND YEAR(ngay_lam) = ?
             ORDER BY ma_nv, ngay_lam'
        );
        $statement->execute([$month, $year]);
        $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);

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
        fputcsv($file, ['Mã NV', 'Ngày làm', 'Số giờ làm', 'Vào muộn', 'Về sớm'], ',');

        // Dữ liệu
        foreach ($rows as $row) {
            fputcsv($file, [
                $row['ma_nv'],
                $row['ngay_lam'],
                $row['so_gio_lam'],
                $row['vao_muon'] ? 'Có' : 'Không',
                $row['ve_som'] ? 'Có' : 'Không',
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
        $pdo = DB::connection()->getPdo();

        // Query dữ liệu
        $statement = $pdo->prepare(
            'SELECT ma_nv, ngay_lam, so_gio_lam, vao_muon, ve_som
             FROM cham_cong
             WHERE MONTH(ngay_lam) = ? AND YEAR(ngay_lam) = ?
             ORDER BY ma_nv, ngay_lam'
        );
        $statement->execute([$month, $year]);
        $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);

        // Tạo file XML Excel (XLSX format)
        $filename = "cham_cong_{$month}_{$year}.xlsx";
        $filePath = storage_path("exports/{$filename}");

        @mkdir(dirname($filePath), 0755, true);

        // Tạo thư mục tạm
        $tmpDir = sys_get_temp_dir() . '/xlsx_' . uniqid();
        @mkdir($tmpDir, 0755, true);

        // Tạo structure XLSX
        $this->createXlsxStructure($tmpDir, $rows, $month, $year);

        // Zip thành XLSX file
        $zip = new ZipArchive();
        $zip->open($filePath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        // Add files vào zip
        $this->addFilesToZip($zip, $tmpDir);

        $zip->close();

        // Xóa thư mục tạm
        $this->deleteDirectory($tmpDir);

        return $filePath;
    }

    /**
     * Tạo structure XLSX
     */
    private function createXlsxStructure(string $tmpDir, array $rows, int $month, int $year): void
    {
        // [Content_Types].xml
        $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
<Default Extension="xml" ContentType="application/xml"/>
<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
<Override PartName="/xl/theme/theme1.xml" ContentType="application/vnd.openxmlformats-officedocument.theme+xml"/>
<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
</Types>';
        file_put_contents($tmpDir . '/[Content_Types].xml', $contentTypes);

        // _rels/.rels
        @mkdir($tmpDir . '/_rels', 0755, true);
        $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
</Relationships>';
        file_put_contents($tmpDir . '/_rels/.rels', $rels);

        // docProps/core.xml
        @mkdir($tmpDir . '/docProps', 0755, true);
        $core = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/officeDocument/2006/metadata/core-properties">
<dc:creator xmlns:dc="http://purl.org/dc/elements/1.1/">Admin</dc:creator>
<dc:title>Chấm công tháng ' . $month . ' năm ' . $year . '</dc:title>
</cp:coreProperties>';
        file_put_contents($tmpDir . '/docProps/core.xml', $core);

        // xl/workbook.xml
        @mkdir($tmpDir . '/xl', 0755, true);
        $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
<fileVersion appName="xl" lastEdited="4" lowestEdited="4" rupBuild="4505"/>
<workbookPr filterPrivacy="0" defaultTheme="1"/>
<bookViews><workbookView xWindow="480" yWindow="60" windowWidth="25920" windowHeight="17640" tabRatio="500" activeTab="0"/></bookViews>
<sheets><sheet name="Chấm công" sheetId="1" r:id="rId1"/></sheets>
</workbook>';
        file_put_contents($tmpDir . '/xl/workbook.xml', $workbook);

        // xl/_rels/workbook.xml.rels
        @mkdir($tmpDir . '/xl/_rels', 0755, true);
        $wbRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/theme" Target="theme/theme1.xml"/>
<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>';
        file_put_contents($tmpDir . '/xl/_rels/workbook.xml.rels', $wbRels);

        // xl/styles.xml (simplified)
        $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
<fonts count="1"><font><sz val="11"/><color theme="1"/><name val="Calibri"/><family val="2"/></font></fonts>
<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>
<borders count="1"><border><left/><right/><top/><bottom/></border></borders>
<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
<cellXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/></cellXfs>
</styleSheet>';
        file_put_contents($tmpDir . '/xl/styles.xml', $styles);

        // xl/worksheets/sheet1.xml (dữ liệu)
        @mkdir($tmpDir . '/xl/worksheets', 0755, true);
        $this->createWorksheet($tmpDir . '/xl/worksheets/sheet1.xml', $rows);

        // xl/theme/theme1.xml (minimal)
        @mkdir($tmpDir . '/xl/theme', 0755, true);
        $theme = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<a:theme xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" name="Office Theme"/>
';
        file_put_contents($tmpDir . '/xl/theme/theme1.xml', $theme);
    }

    /**
     * Tạo worksheet sheet1.xml với dữ liệu
     */
    private function createWorksheet(string $filePath, array $rows): void
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
<sheetData>';

        // Header row
        $xml .= '<row r="1">
<c r="A1" t="str"><v>Mã NV</v></c>
<c r="B1" t="str"><v>Ngày làm</v></c>
<c r="C1" t="str"><v>Số giờ làm</v></c>
<c r="D1" t="str"><v>Vào muộn</v></c>
<c r="E1" t="str"><v>Về sớm</v></c>
</row>';

        // Data rows
        $rowNum = 2;
        foreach ($rows as $row) {
            $xml .= '<row r="' . $rowNum . '">
<c r="A' . $rowNum . '" t="str"><v>' . htmlspecialchars($row['ma_nv']) . '</v></c>
<c r="B' . $rowNum . '" t="str"><v>' . $row['ngay_lam'] . '</v></c>
<c r="C' . $rowNum . '" t="n"><v>' . $row['so_gio_lam'] . '</v></c>
<c r="D' . $rowNum . '" t="str"><v>' . ($row['vao_muon'] ? 'Có' : 'Không') . '</v></c>
<c r="E' . $rowNum . '" t="str"><v>' . ($row['ve_som'] ? 'Có' : 'Không') . '</v></c>
</row>';
            $rowNum++;
        }

        $xml .= '</sheetData>
</worksheet>';

        file_put_contents($filePath, $xml);
    }

    /**
     * Add files to zip recursively
     */
    private function addFilesToZip(ZipArchive $zip, string $dir, string $basePath = ''): void
    {
        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $filePath = $dir . '/' . $file;
            $zipPath = $basePath . $file;

            if (is_dir($filePath)) {
                $this->addFilesToZip($zip, $filePath, $zipPath . '/');
            } else {
                $zip->addFile($filePath, $zipPath);
            }
        }
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
            ['NV001', '2026-08-01', '8', '0', '0'],
            ['NV002', '2026-08-02', '7.5', '1', '0'],
            ['NV003', '2026-08-03', '8', '0', '1'],
        ];

        foreach ($samples as $sample) {
            fputcsv($file, $sample, ',');
        }

        fclose($file);

        return $filePath;
    }

    /**
     * Xóa thư mục recursively
     */
    private function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $filePath = $dir . '/' . $file;
            if (is_dir($filePath)) {
                $this->deleteDirectory($filePath);
            } else {
                @unlink($filePath);
            }
        }
        @rmdir($dir);
    }
}

