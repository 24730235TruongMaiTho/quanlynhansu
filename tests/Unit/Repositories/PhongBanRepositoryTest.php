<?php

namespace Tests\Unit\Repositories;

use App\Contracts\PhongBanRepositoryContract;
use App\Exceptions\PhongBanDomainException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PhongBanRepositoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('phong_ban', function (Blueprint $table): void {
            $table->increments('ma_pb');
            $table->string('ten_pb', 100)->unique('uq_phong_ban_ten');
        });

        Schema::create('nhan_vien', function (Blueprint $table): void {
            $table->string('ma_nv', 5)->primary();
            $table->unsignedInteger('ma_pb')->nullable();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('nhan_vien');
        Schema::dropIfExists('phong_ban');

        parent::tearDown();
    }

    public function test_all_and_find_return_explicit_rows_with_employee_counts(): void
    {
        DB::table('phong_ban')->insert([
            ['ma_pb' => 1, 'ten_pb' => 'Kỹ thuật'],
            ['ma_pb' => 2, 'ten_pb' => 'Nhân sự'],
        ]);
        DB::table('nhan_vien')->insert([
            ['ma_nv' => 'NV001', 'ma_pb' => 1],
            ['ma_nv' => 'NV002', 'ma_pb' => 1],
        ]);

        $repository = app(PhongBanRepositoryContract::class);

        $this->assertSame([
            ['ma_pb' => 1, 'ten_pb' => 'Kỹ thuật', 'so_nhan_vien' => 2],
            ['ma_pb' => 2, 'ten_pb' => 'Nhân sự', 'so_nhan_vien' => 0],
        ], array_map(static fn (object $row): array => [
            'ma_pb' => $row->ma_pb,
            'ten_pb' => $row->ten_pb,
            'so_nhan_vien' => $row->so_nhan_vien,
        ], $repository->all()));

        $row = $repository->find(1);

        $this->assertNotNull($row);
        $this->assertSame(['ma_pb', 'ten_pb', 'so_nhan_vien'], array_keys(get_object_vars($row)));
        $this->assertSame(1, $row->ma_pb);
        $this->assertSame('Kỹ thuật', $row->ten_pb);
        $this->assertSame(2, $row->so_nhan_vien);
    }

    public function test_create_and_update_trim_names_at_the_business_boundary(): void
    {
        $repository = app(PhongBanRepositoryContract::class);

        $repository->create('  Tài chính  ');
        $this->assertSame('Tài chính', DB::table('phong_ban')->value('ten_pb'));

        $repository->update(1, '  Kế toán  ');
        $this->assertSame('Kế toán', DB::table('phong_ban')->where('ma_pb', 1)->value('ten_pb'));
    }

    public function test_duplicate_names_are_reported_as_a_safe_domain_error(): void
    {
        DB::table('phong_ban')->insert(['ma_pb' => 1, 'ten_pb' => 'Kỹ thuật']);

        try {
            app(PhongBanRepositoryContract::class)->create('  Kỹ thuật  ');
            $this->fail('Duplicate department names must be rejected.');
        } catch (PhongBanDomainException $exception) {
            $this->assertSame('PB_NAME_DUPLICATE', $exception->domainCode);
            $this->assertSame('ten_pb', $exception->field);
        }
    }

    public function test_blank_and_overlong_names_are_rejected_before_database_write(): void
    {
        $repository = app(PhongBanRepositoryContract::class);

        foreach ([['   ', 'PB_NAME_REQUIRED'], [str_repeat('a', 101), 'PB_NAME_TOO_LONG']] as [$name, $code]) {
            try {
                $repository->create($name);
                $this->fail('Invalid department names must be rejected.');
            } catch (PhongBanDomainException $exception) {
                $this->assertSame($code, $exception->domainCode);
            }
        }

        $this->assertSame(0, DB::table('phong_ban')->count());
    }

    public function test_update_and_delete_missing_department_return_not_found(): void
    {
        $repository = app(PhongBanRepositoryContract::class);

        foreach ([
            static function () use ($repository): void {
                $repository->update(999, 'Mới');
            },
            static function () use ($repository): void {
                $repository->delete(999);
            },
        ] as $operation) {
            try {
                $operation();
                $this->fail('Missing department targets must be rejected.');
            } catch (PhongBanDomainException $exception) {
                $this->assertSame('PB_NOT_FOUND', $exception->domainCode);
            }
        }
    }

    public function test_delete_is_blocked_when_employees_depend_on_department(): void
    {
        DB::table('phong_ban')->insert(['ma_pb' => 1, 'ten_pb' => 'Đang dùng']);
        DB::table('nhan_vien')->insert(['ma_nv' => 'NV001', 'ma_pb' => 1]);

        try {
            app(PhongBanRepositoryContract::class)->delete(1);
            $this->fail('A department with employees must not be deleted.');
        } catch (PhongBanDomainException $exception) {
            $this->assertSame('PB_IN_USE', $exception->domainCode);
            $this->assertSame('phong_ban', $exception->field);
        }

        $this->assertSame(1, DB::table('phong_ban')->count());
    }
}
