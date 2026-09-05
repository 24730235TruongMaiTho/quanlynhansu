<?php

namespace Tests\Unit\Repositories;

use App\Exceptions\PhanQuyenDomainException;
use App\Repositories\PhanQuyenRepository;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class PhanQuyenBulkAssignmentTest extends TestCase
{
    private PhanQuyenRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('vai_tro', static function (Blueprint $table): void {
            $table->unsignedInteger('ma_vt')->primary();
            $table->string('ten_vt');
        });
        Schema::create('nhan_vien', static function (Blueprint $table): void {
            $table->string('ma_nv', 10)->primary();
            $table->string('ho_ten')->nullable();
            $table->string('email')->nullable();
            $table->unsignedInteger('ma_vt');
            $table->unsignedInteger('ma_tt');
        });
        $this->repository = app(PhanQuyenRepository::class);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('nhan_vien');
        Schema::dropIfExists('vai_tro');
        parent::tearDown();
    }

    public function test_bulk_assignment_rolls_back_all_rows_when_any_role_is_invalid(): void
    {
        DB::table('vai_tro')->insert([
            ['ma_vt' => 1, 'ten_vt' => 'Quản trị'],
            ['ma_vt' => 2, 'ten_vt' => 'Nhân sự'],
            ['ma_vt' => 5, 'ten_vt' => 'Nhân viên'],
        ]);
        DB::table('nhan_vien')->insert([
            ['ma_nv' => '00999', 'ma_vt' => 1, 'ma_tt' => 1],
            ['ma_nv' => '00001', 'ma_vt' => 5, 'ma_tt' => 1],
            ['ma_nv' => '00002', 'ma_vt' => 5, 'ma_tt' => 1],
        ]);

        $this->expectException(PhanQuyenDomainException::class);
        $this->expectExceptionMessage('Không tìm thấy vai trò.');

        try {
            $this->repository->assignRoles(['00002' => 2, '00001' => 999], '00999');
        } finally {
            self::assertSame(5, (int) DB::table('nhan_vien')->where('ma_nv', '00001')->value('ma_vt'));
            self::assertSame(5, (int) DB::table('nhan_vien')->where('ma_nv', '00002')->value('ma_vt'));
        }
    }

    public function test_self_unchanged_is_allowed_but_self_changed_is_rejected(): void
    {
        DB::table('vai_tro')->insert([
            ['ma_vt' => 1, 'ten_vt' => 'Quản trị'],
            ['ma_vt' => 2, 'ten_vt' => 'Nhân sự'],
        ]);
        DB::table('nhan_vien')->insert([
            ['ma_nv' => '00999', 'ma_vt' => 1, 'ma_tt' => 1],
        ]);

        $this->repository->assignRoles(['00999' => 1], '00999');
        self::assertSame(1, (int) DB::table('nhan_vien')->where('ma_nv', '00999')->value('ma_vt'));

        $this->expectException(PhanQuyenDomainException::class);
        $this->repository->assignRoles(['00999' => 2], '00999');
    }

    public function test_bulk_assignment_rolls_back_when_any_employee_is_missing(): void
    {
        DB::table('vai_tro')->insert([
            ['ma_vt' => 1, 'ten_vt' => 'Quản trị'],
            ['ma_vt' => 2, 'ten_vt' => 'Nhân sự'],
        ]);
        DB::table('nhan_vien')->insert([
            ['ma_nv' => '00999', 'ma_vt' => 1, 'ma_tt' => 1],
            ['ma_nv' => '00001', 'ma_vt' => 1, 'ma_tt' => 1],
        ]);

        $this->expectException(PhanQuyenDomainException::class);
        $this->expectExceptionMessage('Không tìm thấy tài khoản.');

        try {
            $this->repository->assignRoles(['00001' => 2, '00002' => 2], '00999');
        } finally {
            self::assertSame(1, (int) DB::table('nhan_vien')->where('ma_nv', '00001')->value('ma_vt'));
        }
    }

    public function test_non_super_admin_cannot_change_super_admin_target(): void
    {
        DB::table('vai_tro')->insert([
            ['ma_vt' => 1, 'ten_vt' => 'Quản trị'],
            ['ma_vt' => 2, 'ten_vt' => 'Nhân sự'],
        ]);
        DB::table('nhan_vien')->insert([
            ['ma_nv' => '00999', 'ma_vt' => 2, 'ma_tt' => 1],
            ['ma_nv' => '00001', 'ma_vt' => 1, 'ma_tt' => 1],
        ]);

        $this->expectException(PhanQuyenDomainException::class);
        $this->repository->assignRoles(['00001' => 2], '00999');
    }

    public function test_accounts_returns_length_aware_page_and_preserves_only_allowlisted_filters(): void
    {
        DB::table('vai_tro')->insert([
            ['ma_vt' => 1, 'ten_vt' => 'Quản trị'],
            ['ma_vt' => 2, 'ten_vt' => 'Nhân sự'],
        ]);

        $rows = [];
        for ($number = 1; $number <= 25; $number++) {
            $code = str_pad((string) $number, 5, '0', STR_PAD_LEFT);
            $rows[] = [
                'ma_nv' => $code,
                'ho_ten' => 'Nhân viên '.$number,
                'email' => $code.'@example.test',
                'ma_vt' => 2,
                'ma_tt' => 1,
            ];
        }
        DB::table('nhan_vien')->insert($rows);

        $page = $this->repository->accounts([
            'tu_khoa' => 'Nhân viên',
            'page' => 2,
            'per_page' => 20,
            'unexpected' => 'ignored',
        ]);

        self::assertSame(25, $page->total());
        self::assertSame(2, $page->currentPage());
        self::assertSame(20, $page->perPage());
        self::assertSame(5, $page->count());
        self::assertStringContainsString('tu_khoa='.rawurlencode('Nhân viên'), $page->url(2));
        self::assertStringContainsString('per_page=20', $page->url(2));
        self::assertStringNotContainsString('unexpected=', $page->url(2));
    }
}
