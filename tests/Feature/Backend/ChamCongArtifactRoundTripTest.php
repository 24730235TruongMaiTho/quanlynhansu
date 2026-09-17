<?php

namespace Tests\Feature\Backend;

use App\Services\ChamCongExportService;
use App\Services\ChamCongImportService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

final class ChamCongArtifactRoundTripTest extends TestCase
{
    /** @var array<int, string> */
    private array $generatedFiles = [];

    private string $originalStoragePath;

    private string $isolatedStoragePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalStoragePath = app()->storagePath();
        $this->isolatedStoragePath = sys_get_temp_dir()
            .DIRECTORY_SEPARATOR
            .'quanlynhansu-feedback-v6-'
            .bin2hex(random_bytes(8));
        File::ensureDirectoryExists($this->isolatedStoragePath);
        app()->useStoragePath($this->isolatedStoragePath);

        Schema::create('nhan_vien', static function (Blueprint $table): void {
            $table->string('ma_nv', 5)->primary();
        });
        Schema::create('cham_cong', static function (Blueprint $table): void {
            $table->increments('ma_cc');
            $table->string('ma_nv', 5);
            $table->date('ngay_lam');
            $table->smallInteger('so_gio_lam');
            $table->boolean('vao_muon');
            $table->boolean('ve_som');
        });

        DB::table('nhan_vien')->insert(['ma_nv' => '00001']);
        DB::table('cham_cong')->insert([
            'ma_nv' => '00001',
            'ngay_lam' => '2026-09-03',
            'so_gio_lam' => 8,
            'vao_muon' => 0,
            've_som' => 1,
        ]);
    }

    protected function tearDown(): void
    {
        foreach ($this->generatedFiles as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }

        Schema::dropIfExists('cham_cong');
        Schema::dropIfExists('nhan_vien');

        app()->useStoragePath($this->originalStoragePath);
        File::deleteDirectory($this->isolatedStoragePath);

        parent::tearDown();
    }

    public function test_csv_export_reopens_with_canonical_headers_and_round_trips(): void
    {
        $path = app(ChamCongExportService::class)->exportToCSV(9, 2026);
        $this->generatedFiles[] = $path;

        $handle = fopen($path, 'rb');
        self::assertIsResource($handle);
        $header = fgetcsv($handle);
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);
        $row = fgetcsv($handle);
        fclose($handle);

        self::assertSame(['ma_nv', 'ngay_lam', 'so_gio_lam', 'vao_muon', 've_som'], $header);
        self::assertSame(['00001', '2026-09-03', '8', '0', '1'], $row);

        DB::table('cham_cong')->delete();
        $result = app(ChamCongImportService::class)->import(
            UploadedFile::fake()->createWithContent('export.csv', file_get_contents($path)),
        );

        self::assertTrue($result['success']);
        self::assertSame(1, $result['data']['inserted']);
        self::assertSame('00001', DB::table('cham_cong')->value('ma_nv'));
    }

    public function test_xlsx_export_reopens_with_canonical_headers_and_round_trips(): void
    {
        $path = app(ChamCongExportService::class)->exportToExcel(9, 2026);
        $this->generatedFiles[] = $path;

        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getActiveSheet();
        $header = [];
        $row = [];
        foreach (range('A', 'E') as $column) {
            $header[] = $sheet->getCell($column.'1')->getValue();
            $row[] = $sheet->getCell($column.'2')->getValue();
        }
        $spreadsheet->disconnectWorksheets();

        self::assertSame(['ma_nv', 'ngay_lam', 'so_gio_lam', 'vao_muon', 've_som'], $header);
        self::assertSame('00001', (string) $row[0]);
        self::assertSame('2026-09-03', $row[1]);
        self::assertSame(8, (int) $row[2]);
        self::assertSame(0, (int) $row[3]);
        self::assertSame(1, (int) $row[4]);

        DB::table('cham_cong')->delete();
        $result = app(ChamCongImportService::class)->import(
            new UploadedFile($path, 'export.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
        );

        self::assertTrue($result['success']);
        self::assertSame(1, $result['data']['inserted']);
        self::assertSame('00001', DB::table('cham_cong')->value('ma_nv'));
    }

    public function test_import_templates_use_the_same_canonical_contract(): void
    {
        $importer = app(ChamCongImportService::class);
        $xlsx = $importer->exportTemplate('xlsx');
        $csv = $importer->exportTemplate('csv');
        $this->generatedFiles[] = $xlsx;
        $this->generatedFiles[] = $csv;

        self::assertStringStartsWith($this->isolatedStoragePath, $xlsx);
        self::assertStringStartsWith($this->isolatedStoragePath, $csv);

        $spreadsheet = IOFactory::load($xlsx);
        $sheet = $spreadsheet->getActiveSheet();
        $xlsxHeader = array_map(
            static fn (string $column): mixed => $sheet->getCell($column.'1')->getValue(),
            range('A', 'E'),
        );
        $spreadsheet->disconnectWorksheets();

        $handle = fopen($csv, 'rb');
        self::assertIsResource($handle);
        $csvHeader = fgetcsv($handle);
        fclose($handle);
        $csvHeader[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $csvHeader[0]);

        self::assertSame($xlsxHeader, $csvHeader);
        self::assertSame(['ma_nv', 'ngay_lam', 'so_gio_lam', 'vao_muon', 've_som'], $xlsxHeader);
        self::assertStringContainsString(
            'Số nguyên từ 0 đến 8.',
            $sheet->getComment('C1')->getText()->getPlainText(),
        );
    }

    public function test_import_rejects_fractional_hours_with_row_error_and_no_insert(): void
    {
        DB::table('cham_cong')->delete();

        $result = app(ChamCongImportService::class)->import(
            UploadedFile::fake()->createWithContent(
                'fractional-hours.csv',
                "ma_nv,ngay_lam,so_gio_lam,vao_muon,ve_som\n00001,03/09/2026,7.5,0,0\n",
            ),
        );

        self::assertFalse($result['success']);
        self::assertSame(0, $result['data']['inserted']);
        self::assertSame(0, DB::table('cham_cong')->count());
        self::assertSame(['Số giờ làm phải là số nguyên từ 0 đến 8.'], $result['errors'][2]);
    }

    public function test_unexpected_spreadsheet_failure_returns_generic_message(): void
    {
        $result = app(ChamCongImportService::class)->import(
            UploadedFile::fake()->createWithContent('private-path.xlsx', 'not an xlsx document'),
        );

        self::assertFalse($result['success']);
        self::assertStringContainsString('Không thể', $result['message']);
        self::assertStringNotContainsString('private-path', $result['message']);
        self::assertStringNotContainsString('SQLSTATE', $result['message']);
    }
}
