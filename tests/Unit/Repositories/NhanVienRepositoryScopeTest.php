<?php

namespace Tests\Unit\Repositories;

use App\Repositories\NhanVienRepository;
use App\Support\NhanVienProcedureExceptionMapper;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NhanVienRepositoryScopeTest extends TestCase
{
    private NhanVienRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('phong_ban', static function (Blueprint $table): void {
            $table->unsignedInteger('ma_pb')->primary();
            $table->string('ten_pb', 100);
        });
        Schema::create('chuc_vu', static function (Blueprint $table): void {
            $table->unsignedInteger('ma_cv')->primary();
            $table->string('ten_cv', 100);
            $table->decimal('he_so_phu_cap', 5, 2);
        });
        Schema::create('trang_thai_lam_viec', static function (Blueprint $table): void {
            $table->unsignedInteger('ma_tt')->primary();
            $table->string('ten_tt', 100);
        });
        Schema::create('vai_tro', static function (Blueprint $table): void {
            $table->unsignedInteger('ma_vt')->primary();
            $table->string('ten_vt', 100);
        });
        Schema::create('nhan_vien', static function (Blueprint $table): void {
            $table->string('ma_nv', 10)->primary();
            $table->string('ho_ten', 100);
            $table->date('ngay_sinh')->nullable();
            $table->boolean('gioi_tinh')->nullable();
            $table->string('sdt', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->date('ngay_vao_lam')->nullable();
            $table->unsignedInteger('ma_pb');
            $table->unsignedInteger('ma_cv');
            $table->string('dan_toc', 50)->nullable();
            $table->string('cccd', 30)->nullable();
            $table->string('noi_cap_cccd', 150)->nullable();
            $table->string('hoc_van', 100)->nullable();
            $table->unsignedInteger('ma_tt');
            $table->unsignedInteger('ma_vt');
            $table->string('anh_dai_dien', 255)->nullable();
            $table->date('ngay_nghi_viec')->nullable();
            $table->string('dia_chi_cu_the', 255)->nullable();
            $table->string('phuong_xa', 100)->nullable();
            $table->string('quan_huyen', 100)->nullable();
            $table->string('tinh_thanh', 100)->nullable();
        });

        DB::table('phong_ban')->insert([
            ['ma_pb' => 3, 'ten_pb' => 'Phòng 3'],
            ['ma_pb' => 4, 'ten_pb' => 'Phòng 4'],
        ]);
        DB::table('chuc_vu')->insert(['ma_cv' => 1, 'ten_cv' => 'Trưởng phòng', 'he_so_phu_cap' => '1.00']);
        DB::table('trang_thai_lam_viec')->insert(['ma_tt' => 2, 'ten_tt' => 'Đang làm việc']);
        DB::table('vai_tro')->insert(['ma_vt' => 4, 'ten_vt' => 'Trưởng phòng']);
        DB::table('nhan_vien')->insert([
            $this->employee('NV004', 3),
            $this->employee('NV005', 4),
        ]);

        $this->repository = new NhanVienRepository(
            $this->app->make(DatabaseManager::class),
            new NhanVienProcedureExceptionMapper,
        );
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('nhan_vien');
        Schema::dropIfExists('vai_tro');
        Schema::dropIfExists('trang_thai_lam_viec');
        Schema::dropIfExists('chuc_vu');
        Schema::dropIfExists('phong_ban');

        parent::tearDown();
    }

    public function test_paginate_with_department_filter_returns_only_that_department_rows(): void
    {
        $result = $this->repository->paginate([
            'tu_khoa' => null,
            'ma_pb' => 3,
            'ma_cv' => null,
            'ma_tt' => null,
            'page' => 1,
            'so_dong' => 20,
        ]);

        $this->assertSame(1, $result->total());
        $this->assertSame(['NV004'], collect($result->items())->pluck('ma_nv')->all());
        $this->assertSame(3, (int) $result->items()[0]->ma_pb);
    }

    /** @return array<string, mixed> */
    private function employee(string $code, int $department): array
    {
        return [
            'ma_nv' => $code,
            'ho_ten' => $code,
            'ma_pb' => $department,
            'ma_cv' => 1,
            'ma_tt' => 2,
            'ma_vt' => 4,
        ];
    }
}
