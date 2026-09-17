<?php

namespace Tests\Unit\Repositories;

use App\Repositories\HopDongRepository;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class HopDongRepositoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('nhan_vien', static function (Blueprint $table): void {
            $table->string('ma_nv', 5)->primary();
            $table->string('ho_ten');
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
            $table->unsignedBigInteger('luong_co_ban');
        });

        \DB::table('nhan_vien')->insert([
            ['ma_nv' => '00001', 'ho_ten' => 'An'],
            ['ma_nv' => '00002', 'ho_ten' => 'Bình'],
        ]);
        \DB::table('loai_hop_dong')->insert(['ma_lhd' => 1, 'ten_lhd' => 'Không thời hạn']);
        \DB::table('hop_dong')->insert([
            ['ma_hd' => 1, 'ma_nv' => '00001', 'ma_lhd' => 1, 'ngay_ky' => '2024-01-01', 'ngay_het_han' => null, 'luong_co_ban' => 10],
            ['ma_hd' => 2, 'ma_nv' => '00002', 'ma_lhd' => 1, 'ngay_ky' => '2025-01-01', 'ngay_het_han' => null, 'luong_co_ban' => 20],
        ]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('hop_dong');
        Schema::dropIfExists('loai_hop_dong');
        Schema::dropIfExists('nhan_vien');

        parent::tearDown();
    }

    public function test_newest_contract_is_default_and_allowlisted_directions_are_supported(): void
    {
        $repository = app(HopDongRepository::class);

        self::assertSame([2, 1], $repository->paginate([], 10, 30)->pluck('ma_hd')->all());
        self::assertSame([1, 2], $repository->paginate(['sort' => 'ma_hd', 'direction' => 'asc'], 10, 30)->pluck('ma_hd')->all());
        self::assertSame([1, 2], $repository->paginate(['sort' => 'ngay_ky', 'direction' => 'asc'], 10, 30)->pluck('ma_hd')->all());
    }

    public function test_unknown_sort_column_falls_back_to_newest_contract_id(): void
    {
        $repository = app(HopDongRepository::class);

        self::assertSame([2, 1], $repository->paginate(['sort' => 'not_a_column', 'direction' => 'sideways'], 10, 30)->pluck('ma_hd')->all());
    }

    public function test_expiring_filter_accepts_bool_like_values_and_excludes_expired_or_late_contracts(): void
    {
        \DB::table('hop_dong')->insert([
            ['ma_hd' => 3, 'ma_nv' => '00001', 'ma_lhd' => 1, 'ngay_ky' => '2026-01-01', 'ngay_het_han' => '2026-09-17', 'luong_co_ban' => 30],
            ['ma_hd' => 4, 'ma_nv' => '00002', 'ma_lhd' => 1, 'ngay_ky' => '2026-01-01', 'ngay_het_han' => '2026-10-18', 'luong_co_ban' => 40],
            ['ma_hd' => 5, 'ma_nv' => '00001', 'ma_lhd' => 1, 'ngay_ky' => '2026-01-01', 'ngay_het_han' => '2026-09-16', 'luong_co_ban' => 50],
        ]);

        Carbon::setTestNow(Carbon::parse('2026-09-17 09:00:00'));

        try {
            $page = app(HopDongRepository::class)->paginate(['sap_het_han' => '1'], 10, 30);

            self::assertSame([3], $page->pluck('ma_hd')->all());
        } finally {
            Carbon::setTestNow();
            \DB::table('hop_dong')->whereIn('ma_hd', [3, 4, 5])->delete();
        }
    }

    public function test_employee_contracts_are_exact_owner_scoped_and_newest_first(): void
    {
        \DB::table('nhan_vien')->insert([
            ['ma_nv' => '00007', 'ho_ten' => 'Bảy'],
            ['ma_nv' => '00008', 'ho_ten' => 'Tám'],
        ]);
        \DB::table('hop_dong')->insert([
            ['ma_hd' => 4, 'ma_nv' => '00007', 'ma_lhd' => 1, 'ngay_ky' => '2024-01-01', 'ngay_het_han' => null, 'luong_co_ban' => 40],
            ['ma_hd' => 9, 'ma_nv' => '00007', 'ma_lhd' => 1, 'ngay_ky' => '2025-01-01', 'ngay_het_han' => null, 'luong_co_ban' => 90],
            ['ma_hd' => 8, 'ma_nv' => '00008', 'ma_lhd' => 1, 'ngay_ky' => '2026-01-01', 'ngay_het_han' => null, 'luong_co_ban' => 80],
        ]);

        try {
            $page = app(HopDongRepository::class)->paginateForEmployee('00007', 20);

            self::assertSame([9, 4], array_map(
                static fn (object $row): int => (int) $row->ma_hd,
                $page->items(),
            ));
            self::assertSame(['00007', '00007'], array_map(
                static fn (object $row): string => $row->ma_nv,
                $page->items(),
            ));
        } finally {
            \DB::table('hop_dong')->whereIn('ma_hd', [4, 8, 9])->delete();
            \DB::table('nhan_vien')->whereIn('ma_nv', ['00007', '00008'])->delete();
        }
    }
}
