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
            'ma_nv' => 'NV001',
            'ho_ten' => 'Đã nghỉ',
            'ma_tt' => 4,
            'ngay_nghi_viec' => '2026-08-24',
        ]);

        try {
            $this->repository->update('NV001', [
                'ho_ten' => 'Không được ghi',
                'ma_tt' => 2,
            ]);
            $this->fail('Expected a terminated employee status transition to be rejected.');
        } catch (NhanVienDomainException $exception) {
            $this->assertSame('NV_STATUS_TRANSITION_FORBIDDEN', $exception->domainCode);
            $this->assertSame('ma_tt', $exception->field);
        }

        $row = DB::table('nhan_vien')->where('ma_nv', 'NV001')->first();
        $this->assertSame('Đã nghỉ', $row->ho_ten);
        $this->assertSame(4, (int) $row->ma_tt);
        $this->assertSame('2026-08-24', $row->ngay_nghi_viec);
    }

    public function test_update_rechecks_active_status_under_lock_before_rejecting_termination(): void
    {
        DB::table('nhan_vien')->insert([
            'ma_nv' => 'NV002',
            'ho_ten' => 'Đang làm',
            'ma_tt' => 2,
            'ngay_nghi_viec' => null,
        ]);

        try {
            $this->repository->update('NV002', [
                'ho_ten' => 'Không được ghi',
                'ma_tt' => 4,
            ]);
            $this->fail('Expected an active employee status transition to be rejected.');
        } catch (NhanVienDomainException $exception) {
            $this->assertSame('NV_STATUS_TRANSITION_FORBIDDEN', $exception->domainCode);
            $this->assertSame('ma_tt', $exception->field);
        }

        $row = DB::table('nhan_vien')->where('ma_nv', 'NV002')->first();
        $this->assertSame('Đang làm', $row->ho_ten);
        $this->assertSame(2, (int) $row->ma_tt);
        $this->assertNull($row->ngay_nghi_viec);
    }

    public function test_auth_projection_hydrates_department_without_exposing_password(): void
    {
        DB::table('nhan_vien')->insert([
            'ma_nv' => 'NV004',
            'ho_ten' => 'Trưởng phòng',
            'email' => 'truong.phong@example.test',
            'mat_khau' => 'bcrypt-secret',
            'ma_vt' => 4,
            'ma_pb' => 3,
            'ma_tt' => 2,
        ]);

        $employee = $this->repository->findAccountByIdentifier('NV004');

        $this->assertNotNull($employee);
        $this->assertSame(3, (int) $employee->ma_pb);
        $this->assertArrayNotHasKey('mat_khau', $employee->toArray());
        $this->assertSame('bcrypt-secret', $employee->getAuthPassword());
    }
}
