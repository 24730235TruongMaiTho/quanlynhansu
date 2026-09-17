<?php

namespace Tests\Unit\Services;

use App\Models\NhanVien;
use App\Services\PersonalDashboardService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class PersonalDashboardServiceTest extends TestCase
{
    /** @var list<string> */
    private array $tables = ['cham_cong', 'nghi_phep', 'loai_phep', 'hop_dong', 'loai_hop_dong', 'luong'];

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-09-17 09:00:00'));

        foreach ($this->tables as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('cham_cong', static function (Blueprint $table): void {
            $table->increments('ma_cc');
            $table->string('ma_nv', 5);
            $table->date('ngay_lam');
            $table->smallInteger('so_gio_lam');
            $table->boolean('vao_muon');
            $table->boolean('ve_som');
        });
        Schema::create('loai_phep', static function (Blueprint $table): void {
            $table->increments('ma_lp');
            $table->string('ten_lp');
        });
        Schema::create('nghi_phep', static function (Blueprint $table): void {
            $table->increments('ma_np');
            $table->string('ma_nv', 5);
            $table->date('tu_ngay');
            $table->date('den_ngay');
            $table->unsignedInteger('ma_lp');
            $table->string('ly_do');
            $table->unsignedTinyInteger('trang_thai_duyet');
        });
        Schema::create('loai_hop_dong', static function (Blueprint $table): void {
            $table->increments('ma_lhd');
            $table->string('ten_lhd');
        });
        Schema::create('hop_dong', static function (Blueprint $table): void {
            $table->increments('ma_hd');
            $table->string('ma_nv', 5);
            $table->unsignedInteger('ma_lhd');
            $table->date('ngay_ky');
            $table->date('ngay_het_han')->nullable();
            $table->decimal('luong_co_ban', 18, 0);
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
    }

    protected function tearDown(): void
    {
        foreach (array_reverse($this->tables) as $table) {
            Schema::dropIfExists($table);
        }
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_widgets_use_authenticated_owner_and_salary_returns_only_latest_period_and_status(): void
    {
        $this->db()->table('cham_cong')->insert([
            ['ma_nv' => '00001', 'ngay_lam' => '2026-09-01', 'so_gio_lam' => 8, 'vao_muon' => 1, 've_som' => 0],
            ['ma_nv' => '00001', 'ngay_lam' => '2026-09-02', 'so_gio_lam' => 7, 'vao_muon' => 0, 've_som' => 1],
            ['ma_nv' => '00002', 'ngay_lam' => '2026-09-03', 'so_gio_lam' => 8, 'vao_muon' => 0, 've_som' => 0],
        ]);
        $this->db()->table('loai_phep')->insert(['ma_lp' => 1, 'ten_lp' => 'Nghỉ phép năm']);
        $this->db()->table('nghi_phep')->insert([
            ['ma_nv' => '00001', 'tu_ngay' => '2026-09-10', 'den_ngay' => '2026-09-10', 'ma_lp' => 1, 'ly_do' => 'Own', 'trang_thai_duyet' => 0],
            ['ma_nv' => '00002', 'tu_ngay' => '2026-09-11', 'den_ngay' => '2026-09-11', 'ma_lp' => 1, 'ly_do' => 'Other', 'trang_thai_duyet' => 0],
        ]);
        $this->db()->table('loai_hop_dong')->insert(['ma_lhd' => 1, 'ten_lhd' => 'Không xác định thời hạn']);
        $this->db()->table('hop_dong')->insert([
            ['ma_nv' => '00001', 'ma_lhd' => 1, 'ngay_ky' => '2024-01-01', 'ngay_het_han' => null, 'luong_co_ban' => 99999999],
            ['ma_nv' => '00002', 'ma_lhd' => 1, 'ngay_ky' => '2026-01-01', 'ngay_het_han' => '2026-09-30', 'luong_co_ban' => 88888888],
        ]);
        $this->db()->table('luong')->insert([
            ['ma_nv' => '00001', 'ky_luong' => '2026-08-01', 'thuong' => 1, 'phat' => 2, 'bao_hiem' => 3, 'thue' => 4],
            ['ma_nv' => '00001', 'ky_luong' => '2026-09-01', 'thuong' => 5, 'phat' => 6, 'bao_hiem' => 7, 'thue' => 8],
            ['ma_nv' => '00002', 'ky_luong' => '2026-12-01', 'thuong' => 9, 'phat' => 9, 'bao_hiem' => 9, 'thue' => 9],
        ]);

        $overview = app(PersonalDashboardService::class)->getOverview($this->actor('00001'));

        self::assertSame('ready', $overview['attendance']['state']);
        self::assertSame(2, $overview['attendance']['data']['so_ngay']);
        self::assertSame(15, $overview['attendance']['data']['tong_gio']);
        self::assertSame(1, $overview['leave']['data']['so_don_cho']);
        self::assertSame(1, $overview['leave']['data']['don_gan_nhat']['ma_np']);
        self::assertSame('Nghỉ phép năm', $overview['leave']['data']['don_gan_nhat']['loai_phep']);
        self::assertSame('Chờ duyệt', $overview['leave']['data']['don_gan_nhat']['trang_thai']);
        self::assertSame('Không xác định thời hạn', $overview['contract']['data']['loai_hop_dong']);
        self::assertNull($overview['contract']['data']['ngay_het_han']);
        self::assertSame('2026-09-01', $overview['salary']['data']['ky_luong']);
        self::assertSame('Đã ghi nhận', $overview['salary']['data']['trang_thai']);
        self::assertArrayNotHasKey('luong_co_ban', $overview['salary']['data']);
        self::assertArrayNotHasKey('thuong', $overview['salary']['data']);
        self::assertArrayNotHasKey('phat', $overview['salary']['data']);
    }

    public function test_one_missing_widget_table_returns_error_without_hiding_other_widgets(): void
    {
        $this->db()->table('luong')->insert([
            'ma_nv' => '00001',
            'ky_luong' => '2026-09-01',
        ]);
        Schema::dropIfExists('cham_cong');

        $overview = app(PersonalDashboardService::class)->getOverview($this->actor('00001'));

        self::assertSame('ready', $overview['profile']['state']);
        self::assertSame('error', $overview['attendance']['state']);
        self::assertSame('empty', $overview['leave']['state']);
        self::assertSame('empty', $overview['contract']['state']);
        self::assertSame('ready', $overview['salary']['state']);
        self::assertArrayNotHasKey('exception', $overview['attendance']);
    }

    public function test_non_canonical_authenticated_identity_is_rejected_before_personal_queries(): void
    {
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);

        app(PersonalDashboardService::class)->getOverview($this->actor('0001'));
    }

    private function actor(string $maNv): NhanVien
    {
        return NhanVien::fromAuthRow((object) [
            'ma_nv' => $maNv,
            'ho_ten' => 'Nguyễn An',
            'email' => 'an@example.test',
            'mat_khau' => 'hidden',
            'ma_vt' => 5,
            'ma_tt' => 1,
            'ten_vt' => 'Nhân viên',
        ]);
    }

    private function db(): \Illuminate\Database\Connection
    {
        return app('db')->connection();
    }
}
