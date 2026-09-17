<?php

namespace Tests\Feature\Backend;

use App\Enums\NghiPhepPermission;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\InteractsWithEmployeeModule;
use Tests\TestCase;

final class NghiPhepPaginationTest extends TestCase
{
    use InteractsWithEmployeeModule;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('phong_ban', static function (Blueprint $table): void {
            $table->increments('ma_pb');
            $table->string('ten_pb');
        });
        Schema::create('chuc_vu', static function (Blueprint $table): void {
            $table->increments('ma_cv');
            $table->string('ten_cv');
        });
        Schema::create('nhan_vien', static function (Blueprint $table): void {
            $table->string('ma_nv', 5)->primary();
            $table->string('ho_ten');
            $table->unsignedInteger('ma_pb');
            $table->unsignedInteger('ma_cv');
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

        DB::table('phong_ban')->insert([
            ['ma_pb' => 2, 'ten_pb' => 'Nhân sự'],
            ['ma_pb' => 3, 'ten_pb' => 'Kế toán'],
        ]);
        DB::table('chuc_vu')->insert([
            ['ma_cv' => 1, 'ten_cv' => 'Trưởng phòng'],
            ['ma_cv' => 2, 'ten_cv' => 'Nhân viên'],
        ]);
        DB::table('nhan_vien')->insert([
            ['ma_nv' => '00001', 'ho_ten' => 'Nguyễn An', 'ma_pb' => 2, 'ma_cv' => 1],
            ['ma_nv' => '00002', 'ho_ten' => 'Trần Bình', 'ma_pb' => 2, 'ma_cv' => 2],
            ['ma_nv' => '00003', 'ho_ten' => 'Lê Cường', 'ma_pb' => 3, 'ma_cv' => 2],
        ]);
        DB::table('loai_phep')->insert(['ma_lp' => 1, 'ten_lp' => 'Phép năm']);

        for ($day = 1; $day <= 11; $day++) {
            $date = sprintf('2026-09-%02d', $day);
            DB::table('nghi_phep')->insert([
                'ma_nv' => '00001',
                'tu_ngay' => $date,
                'den_ngay' => $date,
                'ma_lp' => 1,
                'ly_do' => 'Nguyễn cần nghỉ',
                'trang_thai_duyet' => 0,
            ]);
        }

        DB::table('nghi_phep')->insert([
            [
                'ma_nv' => '00001',
                'tu_ngay' => '2026-08-01',
                'den_ngay' => '2026-08-01',
                'ma_lp' => 1,
                'ly_do' => 'Đã xử lý',
                'trang_thai_duyet' => 1,
            ],
            [
                'ma_nv' => '00003',
                'tu_ngay' => '2026-08-02',
                'den_ngay' => '2026-08-02',
                'ma_lp' => 1,
                'ly_do' => 'Ngoài phòng ban',
                'trang_thai_duyet' => 0,
            ],
        ]);
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

    public function test_index_uses_allowlisted_filters_and_shared_pagination_shape(): void
    {
        $this->actingAsEmployeeWithPermissions([NghiPhepPermission::Xem]);

        $response = $this->getJson(
            '/api/v1/nghi-phep?tab=pending&page=2&per_page=10&tu_khoa=Nguyễn&ma_pb=2&ma_cv=1'
        );

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.current_page', 2)
            ->assertJsonPath('data.per_page', 10)
            ->assertJsonPath('data.total', 11)
            ->assertJsonPath('data.from', 11)
            ->assertJsonPath('data.to', 11)
            ->assertJsonPath('data.data.0.ma_nv', '00001')
            ->assertJsonPath('counts.pending', 11)
            ->assertJsonPath('counts.history', 1);
    }

    public function test_read_actor_can_filter_companywide_without_department_scope(): void
    {
        $this->actingAsEmployeeWithPermissions(
            [NghiPhepPermission::Xem],
            ['ma_vt' => 1, 'ma_pb' => null],
        );

        $response = $this->getJson(
            '/api/v1/nghi-phep?tab=pending&per_page=50&ma_pb=3'
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.ma_pb', 3);
    }

    public function test_read_actor_without_department_can_list_companywide(): void
    {
        $this->actingAsEmployeeWithPermissions(
            [NghiPhepPermission::Xem],
            ['ma_vt' => 1, 'ma_pb' => null],
        );

        $this->getJson('/api/v1/nghi-phep?tab=pending')
            ->assertOk()
            ->assertJsonPath('data.total', 12);
    }

    public function test_history_tab_returns_processed_rows_without_changing_tab_counts(): void
    {
        $this->actingAsEmployeeWithPermissions([NghiPhepPermission::Xem]);

        $this->getJson('/api/v1/nghi-phep?tab=history&per_page=10')
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.trang_thai_duyet', 1)
            ->assertJsonPath('counts.pending', 12)
            ->assertJsonPath('counts.history', 1);
    }

    public function test_index_rejects_a_history_range_that_ends_before_it_starts(): void
    {
        $this->actingAsEmployeeWithPermissions([NghiPhepPermission::Xem]);

        $this->getJson('/api/v1/nghi-phep?tab=history&tu_ngay=2026-09-30&den_ngay=2026-09-01')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('den_ngay');
    }

    public function test_generic_update_rejects_crafted_approval_status(): void
    {
        $this->actingAsEmployeeWithPermissions([NghiPhepPermission::Sua]);

        $this->putJson('/api/v1/nghi-phep/1', [
            'ma_nv' => '00001',
            'tu_ngay' => '2026-09-01',
            'den_ngay' => '2026-09-01',
            'ma_lp' => 1,
            'ly_do' => 'Cập nhật hợp lệ',
            'trang_thai_duyet' => 1,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('trang_thai_duyet');

        self::assertSame(0, DB::table('nghi_phep')->where('ma_np', 1)->value('trang_thai_duyet'));
    }
}
