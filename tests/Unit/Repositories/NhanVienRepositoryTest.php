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
            $table->string('email', 150)->nullable();
            $table->string('mat_khau', 255)->nullable();
            $table->unsignedInteger('ma_vt')->nullable();
            $table->unsignedInteger('ma_pb')->nullable();
            $table->unsignedInteger('ma_tt');
            $table->date('ngay_nghi_viec')->nullable();
        });

        $this->repository = new NhanVienRepository(
            $this->app->make(DatabaseManager::class),
            new NhanVienProcedureExceptionMapper,
        );
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('nhan_vien');

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

}
