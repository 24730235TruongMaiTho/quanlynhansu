<?php

namespace Tests\Unit\Services;

use App\Services\NghiPhepService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class NghiPhepManagerApprovalTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('phong_ban', static function (Blueprint $table): void {
            $table->unsignedInteger('ma_pb')->primary();
            $table->string('ten_pb');
        });
        Schema::create('chuc_vu', static function (Blueprint $table): void {
            $table->unsignedInteger('ma_cv')->primary();
            $table->string('ten_cv');
        });
        Schema::create('nhan_vien', static function (Blueprint $table): void {
            $table->string('ma_nv')->primary();
            $table->string('ho_ten');
            $table->unsignedInteger('ma_pb');
            $table->unsignedInteger('ma_cv');
            $table->unsignedTinyInteger('ma_tt');
        });
        Schema::create('loai_phep', static function (Blueprint $table): void {
            $table->unsignedInteger('ma_lp')->primary();
            $table->string('ten_lp');
        });
        Schema::create('nghi_phep', static function (Blueprint $table): void {
            $table->increments('ma_np');
            $table->string('ma_nv');
            $table->unsignedInteger('ma_lp');
            $table->date('tu_ngay');
            $table->date('den_ngay');
            $table->string('ly_do');
            $table->unsignedTinyInteger('trang_thai_duyet');
        });

        DB::table('phong_ban')->insert([
            ['ma_pb' => 1, 'ten_pb' => 'Kinh doanh'],
            ['ma_pb' => 2, 'ten_pb' => 'Kỹ thuật'],
        ]);
        DB::table('chuc_vu')->insert(['ma_cv' => 1, 'ten_cv' => 'Nhân viên']);
        DB::table('loai_phep')->insert(['ma_lp' => 1, 'ten_lp' => 'Phép năm']);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('nghi_phep');
        Schema::dropIfExists('loai_phep');
        Schema::dropIfExists('nhan_vien');
        Schema::dropIfExists('chuc_vu');
        Schema::dropIfExists('phong_ban');

        parent::tearDown();
    }

    public function test_approval_locks_and_updates_only_pending_leave_companywide(): void
    {
        $this->insertEmployee('00001', 1);
        $this->insertLeave('00001', 0);

        $result = app(NghiPhepService::class)->duyet(1, 1);

        self::assertTrue($result['success']);
        self::assertSame(1, DB::table('nghi_phep')->where('ma_np', 1)->value('trang_thai_duyet'));
    }

    public function test_cross_department_leave_is_eligible_for_an_actor_with_approve_permission(): void
    {
        $this->insertEmployee('00001', 1);
        $this->insertLeave('00001', 0);

        $result = app(NghiPhepService::class)->duyet(1, 1);

        self::assertTrue($result['success']);
        self::assertSame(1, DB::table('nghi_phep')->where('ma_np', 1)->value('trang_thai_duyet'));
    }

    public function test_processed_leave_returns_conflict_without_second_mutation(): void
    {
        $this->insertEmployee('00001', 2);
        $this->insertLeave('00001', 1);

        $result = app(NghiPhepService::class)->duyet(1, 2);

        self::assertFalse($result['success']);
        self::assertSame('NGHI_PHEP_ALREADY_PROCESSED', $result['code']);
        self::assertSame(1, DB::table('nghi_phep')->where('ma_np', 1)->value('trang_thai_duyet'));
    }

    public function test_generic_update_cannot_reopen_a_processed_leave(): void
    {
        $this->insertEmployee('00001', 2);
        $this->insertLeave('00001', 1);

        $result = app(NghiPhepService::class)->update(1, [
            'ma_nv' => '00001',
            'tu_ngay' => '2026-09-02',
            'den_ngay' => '2026-09-03',
            'ma_lp' => 1,
            'ly_do' => 'Đổi lý do',
            'trang_thai_duyet' => 0,
        ]);

        self::assertTrue($result['success']);
        self::assertSame(1, DB::table('nghi_phep')->where('ma_np', 1)->value('trang_thai_duyet'));
        self::assertSame('Đổi lý do', DB::table('nghi_phep')->where('ma_np', 1)->value('ly_do'));
    }

    public function test_approval_list_uses_sqlite_date_expression_without_department_scope(): void
    {
        $this->insertEmployee('00001', 2);
        $this->insertEmployee('00002', 1);
        $this->insertLeave('00001', 0);
        $this->insertLeave('00002', 0);

        $paginator = app(NghiPhepService::class)->getApprovalList([
            'tab' => 'pending',
            'page' => 1,
            'per_page' => 10,
        ]);

        self::assertSame(2, $paginator->total());
        self::assertCount(2, $paginator->items());
        self::assertSame(3, (int) $paginator->items()[0]->so_ngay);
    }

    public function test_pending_count_matches_companywide_pending_total(): void
    {
        for ($index = 1; $index <= 11; $index++) {
            $maNv = sprintf('%05d', $index);
            $this->insertEmployee($maNv, 2);
            $this->insertLeave($maNv, 0);
        }

        $this->insertEmployee('00020', 1);
        $this->insertLeave('00020', 0);
        $this->insertEmployee('00021', 2);
        $this->insertLeave('00021', 1);

        $service = app(NghiPhepService::class);
        $pendingCount = $service->countPendingLeave();
        $result = $service->getAll([
            'tab' => 'pending',
            'page' => 1,
            'per_page' => 10,
        ]);

        self::assertTrue($result['success']);
        self::assertSame(12, $pendingCount);
        self::assertSame($pendingCount, $result['counts']['pending']);
        self::assertSame($pendingCount, $result['data']['total']);
        self::assertSame(2, $result['data']['last_page']);
        self::assertCount(10, $result['data']['data']);
    }

    private function insertEmployee(string $maNv, int $maPb): void
    {
        DB::table('nhan_vien')->insert([
            'ma_nv' => $maNv,
            'ho_ten' => 'Nguyễn An',
            'ma_pb' => $maPb,
            'ma_cv' => 1,
            'ma_tt' => 1,
        ]);
    }

    private function insertLeave(string $maNv, int $status): void
    {
        DB::table('nghi_phep')->insert([
            'ma_nv' => $maNv,
            'ma_lp' => 1,
            'tu_ngay' => '2026-09-01',
            'den_ngay' => '2026-09-03',
            'ly_do' => 'Nghỉ phép',
            'trang_thai_duyet' => $status,
        ]);
    }
}
