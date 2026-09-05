<?php

namespace Tests\Feature\Backend;

use App\Services\ChamCongImportService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class ChamCongImportDateContractTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('nhan_vien', static function (Blueprint $table): void {
            $table->string('ma_nv', 5)->primary();
        });
        Schema::create('cham_cong', static function (Blueprint $table): void {
            $table->increments('ma_cc');
            $table->string('ma_nv', 5);
            $table->date('ngay_lam');
            $table->decimal('so_gio_lam', 5, 2);
            $table->boolean('vao_muon');
            $table->boolean('ve_som');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('cham_cong');
        Schema::dropIfExists('nhan_vien');

        parent::tearDown();
    }

    public function test_excel_template_source_promotes_strict_vietnamese_display_date(): void
    {
        $source = file_get_contents(app_path('Services/ChamCongImportService.php'));

        self::assertIsString($source);
        self::assertStringContainsString('Định dạng: dd/mm/yyyy.', $source);
        self::assertStringContainsString("setFormatCode('dd/mm/yyyy')", $source);
        self::assertStringContainsString('Nhập ngày theo dd/mm/yyyy.', $source);
        self::assertStringNotContainsString('Định dạng: YYYY-MM-DD.', $source);
        self::assertStringNotContainsString('Nhập ngày theo YYYY-MM-DD.', $source);

        $controller = file_get_contents(app_path('Http/Controllers/Backend/ChamCongController.php'));

        self::assertIsString($controller);
        self::assertStringContainsString('00001,01/08/2026,8,0,0', $controller);
        self::assertStringNotContainsString('NV001,2026-08-01,8,0,0', $controller);
    }

    public function test_csv_display_date_is_normalized_to_iso_before_insert(): void
    {
        DB::table('nhan_vien')->insert(['ma_nv' => '00001']);

        $file = UploadedFile::fake()->createWithContent(
            'cham-cong.csv',
            "ma_nv,ngay_lam,so_gio_lam,vao_muon,ve_som\n00001,03/09/2026,8,0,0\n",
        );

        $result = app(ChamCongImportService::class)->import($file);

        self::assertTrue($result['success']);
        self::assertSame('2026-09-03', DB::table('cham_cong')->value('ngay_lam'));
        self::assertSame('2026-09-03', $result['data']['rows'][0]['ngay_lam']);
    }
}
