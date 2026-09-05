<?php

namespace Tests\Unit\Repositories;

use App\Repositories\NhanVienRepository;
use App\Support\NhanVienProcedureExceptionMapper;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class NhanVienRepositoryAuthLookupTest extends TestCase
{
    private NhanVienRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('nhan_vien');
        Schema::dropIfExists('vai_tro');

        Schema::create('vai_tro', static function (Blueprint $table): void {
            $table->increments('ma_vt');
            $table->string('ten_vt', 100);
        });
        Schema::create('nhan_vien', static function (Blueprint $table): void {
            $table->string('ma_nv', 10)->primary();
            $table->string('ho_ten', 100);
            $table->string('email', 255)->nullable();
            $table->string('mat_khau', 255);
            $table->unsignedInteger('ma_vt');
            $table->unsignedInteger('ma_pb')->nullable();
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
        Schema::dropIfExists('vai_tro');

        parent::tearDown();
    }

    public function test_account_lookup_hydrates_role_name_for_authenticated_topbar(): void
    {
        DB::table('vai_tro')->insert(['ma_vt' => 42, 'ten_vt' => 'Quản trị hệ thống']);
        DB::table('nhan_vien')->insert([
            'ma_nv' => '00001',
            'ho_ten' => 'Nguyễn An',
            'email' => 'an@example.test',
            'mat_khau' => 'hash',
            'ma_vt' => 42,
            'ma_pb' => null,
            'ma_tt' => 1,
        ]);

        $account = $this->repository->findAccountByIdentifier('00001');

        self::assertNotNull($account);
        self::assertSame(42, (int) $account->ma_vt);
        self::assertSame('Quản trị hệ thống', $account->ten_vt);
    }
}
