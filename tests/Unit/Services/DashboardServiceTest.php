<?php

namespace Tests\Unit\Services;

use App\Services\DashboardService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class DashboardServiceTest extends TestCase
{
    /** @var list<string> */
    private array $tables = [
        'cham_cong',
        'luong',
        'hop_dong',
        'loai_hop_dong',
        'nhan_vien',
        'chuc_vu',
        'phong_ban',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-08-15 09:00:00'));

        foreach ($this->tables as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('phong_ban', static function (Blueprint $table): void {
            $table->increments('ma_pb');
            $table->string('ten_pb', 100);
        });
        Schema::create('chuc_vu', static function (Blueprint $table): void {
            $table->increments('ma_cv');
            $table->string('ten_cv', 100);
            $table->decimal('he_so_phu_cap', 5, 2);
        });
        Schema::create('nhan_vien', static function (Blueprint $table): void {
            $table->string('ma_nv', 5)->primary();
            $table->string('ho_ten', 50);
            $table->string('hoc_van', 50);
            $table->unsignedInteger('ma_pb');
            $table->unsignedInteger('ma_cv');
            $table->unsignedTinyInteger('ma_tt');
        });
        Schema::create('loai_hop_dong', static function (Blueprint $table): void {
            $table->increments('ma_lhd');
            $table->string('ten_lhd', 255);
        });
        Schema::create('hop_dong', static function (Blueprint $table): void {
            $table->increments('ma_hd');
            $table->string('ma_nv', 5);
            $table->unsignedInteger('ma_lhd');
            $table->date('ngay_ky');
            $table->date('ngay_het_han')->nullable();
            $table->decimal('luong_co_ban', 18, 0);
        });
        Schema::create('cham_cong', static function (Blueprint $table): void {
            $table->increments('ma_cc');
            $table->string('ma_nv', 5);
            $table->date('ngay_lam');
            $table->unsignedSmallInteger('so_gio_lam');
            $table->boolean('vao_muon');
            $table->boolean('ve_som');
        });
        Schema::create('luong', static function (Blueprint $table): void {
            $table->increments('ma_luong');
            $table->string('ma_nv', 5);
            $table->date('ky_luong');
            $table->decimal('thuong', 18, 0)->nullable();
            $table->decimal('phat', 18, 0)->nullable();
            $table->decimal('bao_hiem', 18, 0)->nullable();
            $table->decimal('thue', 18, 0)->nullable();
        });

        $this->seedDashboardRows();
    }

    protected function tearDown(): void
    {
        foreach ($this->tables as $table) {
            Schema::dropIfExists($table);
        }
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_dashboard_reads_canonical_contract_columns_and_date_salary_period(): void
    {
        $service = app(DashboardService::class);

        $education = $service->getEmployeeCountByEducation();
        self::assertCount(1, $education);
        self::assertSame('Đại học', $education[0]->hoc_van);
        self::assertSame(1, (int) $education[0]->total);

        $contracts = $service->getExpiringContracts(365);
        self::assertCount(1, $contracts);
        self::assertSame('Hợp đồng xác định thời hạn', $contracts[0]->ten_loai_hop_dong);
        self::assertSame('2026-01-01', $contracts[0]->ngay_bat_dau);
        self::assertSame('2026-12-31', $contracts[0]->ngay_ket_thuc);
        self::assertSame(138, (int) $contracts[0]->so_ngay_con_lai);

        $salary = $service->getSalaryReport();
        self::assertSame(1, $salary['so_nguoi']);
        self::assertSame(100_000.0, $salary['tong_dieu_chinh']);
        self::assertSame(100_000.0, $salary['dieu_chinh_trung_binh']);
    }

    private function seedDashboardRows(): void
    {
        $this->assertTrue(Schema::hasTable('nhan_vien'));

        $this->db()->table('phong_ban')->insert([
            'ma_pb' => 1,
            'ten_pb' => 'Kỹ thuật',
        ]);
        $this->db()->table('chuc_vu')->insert([
            'ma_cv' => 1,
            'ten_cv' => 'Nhân viên',
            'he_so_phu_cap' => 0,
        ]);
        $this->db()->table('nhan_vien')->insert([
            'ma_nv' => '00001',
            'ho_ten' => 'Nguyễn Văn An',
            'hoc_van' => 'Đại học',
            'ma_pb' => 1,
            'ma_cv' => 1,
            'ma_tt' => 1,
        ]);
        $this->db()->table('nhan_vien')->insert([
            'ma_nv' => '00002',
            'ho_ten' => 'Nhân viên đã nghỉ',
            'hoc_van' => 'Cao đẳng',
            'ma_pb' => 1,
            'ma_cv' => 1,
            'ma_tt' => 4,
        ]);
        $this->db()->table('loai_hop_dong')->insert([
            'ma_lhd' => 1,
            'ten_lhd' => 'Hợp đồng xác định thời hạn',
        ]);
        $this->db()->table('hop_dong')->insert([
            'ma_hd' => 1,
            'ma_nv' => '00001',
            'ma_lhd' => 1,
            'ngay_ky' => '2026-01-01',
            'ngay_het_han' => '2026-12-31',
            'luong_co_ban' => 10_000_000,
        ]);
        $this->db()->table('luong')->insert([
            'ma_luong' => 1,
            'ma_nv' => '00001',
            'ky_luong' => '2026-08-01',
            'thuong' => 1_000_000,
            'phat' => 200_000,
            'bao_hiem' => 300_000,
            'thue' => 400_000,
        ]);
    }

    private function db(): \Illuminate\Database\Connection
    {
        return app('db')->connection();
    }
}
