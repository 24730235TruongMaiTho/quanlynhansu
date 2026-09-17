<?php

namespace Tests\Unit\Repositories;

use App\Exceptions\NhanVienDomainException;
use App\Repositories\NhanVienRepository;
use App\Support\NhanVienProcedureExceptionMapper;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NhanVienRepositoryTest extends TestCase
{
    private NhanVienRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('nhan_vien', static function (Blueprint $table): void {
            $table->string('ma_nv', 10)->primary();
            $table->string('ho_ten', 100)->nullable();
            $table->date('ngay_sinh')->nullable();
            $table->tinyInteger('gioi_tinh')->nullable();
            $table->string('sdt', 20)->nullable();
            $table->string('email', 150)->nullable();
            $table->date('ngay_vao_lam')->nullable();
            $table->string('mat_khau', 255)->nullable();
            $table->unsignedInteger('ma_vt')->nullable();
            $table->unsignedInteger('ma_pb')->nullable();
            $table->unsignedInteger('ma_cv')->nullable();
            $table->string('dan_toc', 50)->nullable();
            $table->string('cccd', 20)->nullable();
            $table->string('noi_cap_cccd', 100)->nullable();
            $table->string('hoc_van', 100)->nullable();
            $table->unsignedInteger('ma_tt');
            $table->string('anh_dai_dien', 255)->nullable();
            $table->string('dia_chi_cu_the', 255)->nullable();
            $table->string('phuong_xa', 100)->nullable();
            $table->string('quan_huyen', 100)->nullable();
            $table->string('tinh_thanh', 100)->nullable();
            $table->date('ngay_nghi_viec')->nullable();
        });
        Schema::create('phong_ban', static function (Blueprint $table): void {
            $table->increments('ma_pb');
            $table->string('ten_pb');
        });
        Schema::create('chuc_vu', static function (Blueprint $table): void {
            $table->increments('ma_cv');
            $table->string('ten_cv');
            $table->decimal('he_so_phu_cap', 5, 2)->default(0);
        });
        Schema::create('trang_thai_lam_viec', static function (Blueprint $table): void {
            $table->increments('ma_tt');
            $table->string('ten_tt');
        });
        Schema::create('vai_tro', static function (Blueprint $table): void {
            $table->increments('ma_vt');
            $table->string('ten_vt');
        });
        Schema::create('cham_cong', static function (Blueprint $table): void {
            $table->increments('ma_cc');
            $table->string('ma_nv', 5);
            $table->date('ngay_lam');
            $table->smallInteger('so_gio_lam');
            $table->boolean('vao_muon');
            $table->boolean('ve_som');
        });

        $this->repository = new NhanVienRepository(
            $this->app->make(DatabaseManager::class),
            new NhanVienProcedureExceptionMapper,
        );
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('nhan_vien');
        Schema::dropIfExists('phong_ban');
        Schema::dropIfExists('chuc_vu');
        Schema::dropIfExists('trang_thai_lam_viec');
        Schema::dropIfExists('vai_tro');
        Schema::dropIfExists('cham_cong');

        parent::tearDown();
    }

    public function test_update_rechecks_terminated_status_under_lock_before_rejecting_transition(): void
    {
        DB::table('nhan_vien')->insert([
            'ma_nv' => '00001',
            'ho_ten' => 'Đã nghỉ',
            'ma_tt' => 4,
            'ngay_nghi_viec' => '2026-08-24',
        ]);

        try {
            $this->repository->update('00001', [
                'ho_ten' => 'Không được ghi',
                'ma_tt' => 2,
            ]);
            $this->fail('Expected a terminated employee status transition to be rejected.');
        } catch (NhanVienDomainException $exception) {
            $this->assertSame('NV_STATUS_TRANSITION_FORBIDDEN', $exception->domainCode);
            $this->assertSame('ma_tt', $exception->field);
        }

        $row = DB::table('nhan_vien')->where('ma_nv', '00001')->first();
        $this->assertSame('Đã nghỉ', $row->ho_ten);
        $this->assertSame(4, (int) $row->ma_tt);
        $this->assertSame('2026-08-24', $row->ngay_nghi_viec);
    }

    public function test_update_rechecks_active_status_under_lock_before_rejecting_termination(): void
    {
        DB::table('nhan_vien')->insert([
            'ma_nv' => '00002',
            'ho_ten' => 'Đang làm',
            'ma_tt' => 2,
            'ngay_nghi_viec' => null,
        ]);

        try {
            $this->repository->update('00002', [
                'ho_ten' => 'Không được ghi',
                'ma_tt' => 4,
            ]);
            $this->fail('Expected an active employee status transition to be rejected.');
        } catch (NhanVienDomainException $exception) {
            $this->assertSame('NV_STATUS_TRANSITION_FORBIDDEN', $exception->domainCode);
            $this->assertSame('ma_tt', $exception->field);
        }

        $row = DB::table('nhan_vien')->where('ma_nv', '00002')->first();
        $this->assertSame('Đang làm', $row->ho_ten);
        $this->assertSame(2, (int) $row->ma_tt);
        $this->assertNull($row->ngay_nghi_viec);
    }

    public function test_update_preserves_each_terminal_status_and_rejects_terminal_crossovers(): void
    {
        foreach ([
            4 => [5, 6],
            5 => [4, 6],
            6 => [4, 5],
        ] as $currentStatus => $otherTerminalStatuses) {
            $maNv = sprintf('0000%d', $currentStatus);
            DB::table('nhan_vien')->insert([
                'ma_nv' => $maNv,
                'ho_ten' => 'Nhân viên terminal '.$currentStatus,
                'ma_tt' => $currentStatus,
                'ngay_nghi_viec' => '2026-08-24',
            ]);

            foreach ($otherTerminalStatuses as $targetStatus) {
                try {
                    $this->repository->update($maNv, ['ma_tt' => $targetStatus]);
                    $this->fail('Không được đổi giữa hai trạng thái terminal.');
                } catch (NhanVienDomainException $exception) {
                    $this->assertSame('NV_STATUS_TRANSITION_FORBIDDEN', $exception->domainCode);
                }
            }

            $this->assertSame($currentStatus, (int) DB::table('nhan_vien')
                ->where('ma_nv', $maNv)->value('ma_tt'));
        }
    }

    public function test_paginate_defaults_to_newest_employee_code_and_supports_both_directions(): void
    {
        DB::table('phong_ban')->insert(['ma_pb' => 1, 'ten_pb' => 'Kỹ thuật']);
        DB::table('chuc_vu')->insert(['ma_cv' => 1, 'ten_cv' => 'Nhân viên', 'he_so_phu_cap' => 0]);
        DB::table('trang_thai_lam_viec')->insert(['ma_tt' => 1, 'ten_tt' => 'Đang làm việc']);
        DB::table('vai_tro')->insert(['ma_vt' => 5, 'ten_vt' => 'Nhân viên']);
        DB::table('nhan_vien')->insert([
            ['ma_nv' => '00001', 'ho_ten' => 'An', 'ma_pb' => 1, 'ma_cv' => 1, 'ma_tt' => 1, 'ma_vt' => 5],
            ['ma_nv' => '00002', 'ho_ten' => 'Bình', 'ma_pb' => 1, 'ma_cv' => 1, 'ma_tt' => 1, 'ma_vt' => 5],
        ]);
        self::assertSame(['00002', '00001'], array_map(
            static fn (object $row): string => (string) $row->ma_nv,
            $this->repository->paginate(['page' => 1, 'so_dong' => 10])->items(),
        ));
        self::assertSame(['00001', '00002'], array_map(
            static fn (object $row): string => (string) $row->ma_nv,
            $this->repository->paginate(['sort' => 'ma_nv', 'direction' => 'asc', 'page' => 1, 'so_dong' => 10])->items(),
        ));
    }

    public function test_attendance_employee_projection_defaults_to_newest_employee_first(): void
    {
        DB::table('phong_ban')->insert(['ma_pb' => 1, 'ten_pb' => 'Kỹ thuật']);
        DB::table('chuc_vu')->insert(['ma_cv' => 1, 'ten_cv' => 'Nhân viên', 'he_so_phu_cap' => 0]);
        DB::table('trang_thai_lam_viec')->insert(['ma_tt' => 1, 'ten_tt' => 'Đang làm việc']);
        DB::table('vai_tro')->insert(['ma_vt' => 5, 'ten_vt' => 'Nhân viên']);
        DB::table('nhan_vien')->insert([
            ['ma_nv' => '00001', 'ho_ten' => 'An', 'ma_pb' => 1, 'ma_cv' => 1, 'ma_tt' => 1, 'ma_vt' => 5],
            ['ma_nv' => '00002', 'ho_ten' => 'Bình', 'ma_pb' => 1, 'ma_cv' => 1, 'ma_tt' => 1, 'ma_vt' => 5],
        ]);

        $items = $this->repository->paginateAttendance([
            'thang' => null,
            'nam' => null,
            'page' => 1,
            'so_dong' => 10,
        ])->items();

        self::assertSame(['00002', '00001'], array_map(
            static fn (object $row): string => (string) $row->ma_nv,
            $items,
        ));
    }

}
