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

final class NhanVienRepositoryResetPasswordTest extends TestCase
{
    private NhanVienRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('nhan_vien', static function (Blueprint $table): void {
            $table->string('ma_nv', 10)->primary();
            $table->string('mat_khau', 255)->nullable();
            $table->unsignedInteger('ma_tt');
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

    public function test_reset_locks_active_exact_target_and_updates_only_hash(): void
    {
        DB::table('nhan_vien')->insert([
            ['ma_nv' => '00001', 'mat_khau' => 'old-hash', 'ma_tt' => 1],
            ['ma_nv' => '00002', 'mat_khau' => 'keep-hash', 'ma_tt' => 1],
        ]);

        $this->repository->resetPassword('00001', 'new-hash');

        self::assertSame('new-hash', DB::table('nhan_vien')->where('ma_nv', '00001')->value('mat_khau'));
        self::assertSame('keep-hash', DB::table('nhan_vien')->where('ma_nv', '00002')->value('mat_khau'));
    }

    public function test_reset_rejects_missing_or_terminated_target(): void
    {
        DB::table('nhan_vien')->insert(['ma_nv' => '00001', 'mat_khau' => 'old-hash', 'ma_tt' => 4]);

        $this->expectException(NhanVienDomainException::class);
        $this->expectExceptionMessage('Không tìm thấy nhân viên.');

        $this->repository->resetPassword('00001', 'new-hash');
    }
}
