<?php

namespace Tests\Unit\Repositories;

use App\Exceptions\PhongBanDomainException;
use App\Repositories\PhongBanRepository;
use App\Support\PhongBanExceptionMapper;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PhongBanRepositoryTest extends TestCase
{
    private PhongBanRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('phong_ban', static function (Blueprint $table): void {
            $table->increments('ma_pb');
            $table->string('ten_pb', 100)->unique('uq_phong_ban_ten');
        });
        Schema::create('nhan_vien', static function (Blueprint $table): void {
            $table->string('ma_nv', 10)->primary();
            $table->unsignedInteger('ma_pb')->nullable();
        });

        $this->repository = new PhongBanRepository(
            $this->app->make(DatabaseManager::class),
            new PhongBanExceptionMapper,
        );
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('nhan_vien');
        Schema::dropIfExists('phong_ban');

        parent::tearDown();
    }

    public function test_all_and_find_return_explicit_rows_with_employee_counts_and_order(): void
    {
        $this->insertDepartment('Kỹ thuật');
        $this->insertDepartment('Nhân sự');
        $this->insertEmployee('00001', 1);
        $this->insertEmployee('00002', 1);

        $rows = $this->repository->all();

        $this->assertSame(['ma_pb', 'ten_pb', 'so_nhan_vien'], array_keys(get_object_vars($rows[0])));
        $this->assertSame(1, $rows[0]->ma_pb);
        $this->assertSame('Kỹ thuật', $rows[0]->ten_pb);
        $this->assertSame(2, $rows[0]->so_nhan_vien);
        $this->assertSame('Nhân sự', $rows[1]->ten_pb);
        $this->assertSame(0, $rows[1]->so_nhan_vien);
        $this->assertSame(2, $this->repository->find(2)->ma_pb);
        $this->assertNull($this->repository->find(999));
    }

    public function test_paginate_filters_by_name_and_preserves_explicit_shape(): void
    {
        $this->insertDepartment('Phòng Kế hoạch');
        $this->insertDepartment('Phòng Kỹ thuật');
        $this->insertDepartment('Nhân sự');

        $page = $this->repository->paginate(['ten_pb' => 'phòng', 'page' => 1, 'so_dong' => 1]);

        $this->assertSame(2, $page->total());
        $this->assertCount(1, $page->items());
        $this->assertSame('Phòng Kế hoạch', $page->items()[0]->ten_pb);
        $this->assertSame(['ma_pb', 'ten_pb', 'so_nhan_vien'], array_keys(get_object_vars($page->items()[0])));
        $this->assertSame(2, $page->lastPage());
    }

    public function test_create_and_update_trim_names_and_persist_state(): void
    {
        $this->repository->create('  Kỹ thuật  ');
        $this->assertSame('Kỹ thuật', DB::table('phong_ban')->where('ma_pb', 1)->value('ten_pb'));

        $this->repository->update(1, '  Nhân sự  ');
        $this->assertSame('Nhân sự', DB::table('phong_ban')->where('ma_pb', 1)->value('ten_pb'));
    }

    public function test_invalid_names_fail_closed_without_writes(): void
    {
        foreach (['', '   ', str_repeat('a', 101)] as $name) {
            try {
                $this->repository->create($name);
                $this->fail('Expected invalid department name to throw.');
            } catch (PhongBanDomainException $exception) {
                $this->assertContains($exception->domainCode, ['PB_NAME_REQUIRED', 'PB_NAME_TOO_LONG']);
            }
        }

        $this->assertSame(0, DB::table('phong_ban')->count());
    }

    public function test_duplicate_name_maps_to_safe_domain_error(): void
    {
        $this->insertDepartment('Kỹ thuật');

        try {
            $this->repository->create('  Kỹ thuật ');
            $this->fail('Expected duplicate department name to throw.');
        } catch (PhongBanDomainException $exception) {
            $this->assertSame('PB_NAME_DUPLICATE', $exception->domainCode);
            $this->assertSame('ten_pb', $exception->field);
        }
    }

    public function test_update_and_delete_missing_or_invalid_ids_fail_closed(): void
    {
        foreach ([
            function (): void {
                $this->repository->update(999, 'Mới');
            },
            function (): void {
                $this->repository->delete(999);
            },
        ] as $operation) {
            try {
                $operation();
                $this->fail('Expected missing department to throw.');
            } catch (PhongBanDomainException $exception) {
                $this->assertSame('PB_NOT_FOUND', $exception->domainCode);
            }
        }

        try {
            $this->repository->delete(0);
            $this->fail('Expected invalid department ID to throw.');
        } catch (PhongBanDomainException $exception) {
            $this->assertSame('PB_ID_INVALID', $exception->domainCode);
        }
    }

    public function test_delete_rejects_departments_in_use_and_deletes_free_department(): void
    {
        $this->insertDepartment('Đang dùng');
        $this->insertDepartment('Trống');
        $this->insertEmployee('00001', 1);

        try {
            $this->repository->delete(1);
            $this->fail('Expected in-use department to throw.');
        } catch (PhongBanDomainException $exception) {
            $this->assertSame('PB_IN_USE', $exception->domainCode);
            $this->assertSame('phong_ban', $exception->field);
        }

        $this->repository->delete(2);
        $this->assertNull($this->repository->find(2));
        $this->assertSame(1, DB::table('phong_ban')->count());
    }

    private function insertDepartment(string $name): void
    {
        DB::table('phong_ban')->insert(['ten_pb' => $name]);
    }

    private function insertEmployee(string $code, int $department): void
    {
        DB::table('nhan_vien')->insert(['ma_nv' => $code, 'ma_pb' => $department]);
    }
}
