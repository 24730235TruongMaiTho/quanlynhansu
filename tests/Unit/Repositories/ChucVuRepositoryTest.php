<?php

namespace Tests\Unit\Repositories;

use App\Exceptions\ChucVuDomainException;
use App\Repositories\ChucVuRepository;
use App\Support\ChucVuExceptionMapper;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ChucVuRepositoryTest extends TestCase
{
    private ChucVuRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('chuc_vu', static function (Blueprint $table): void {
            $table->increments('ma_cv');
            $table->string('ten_cv', 100)->unique('uq_chuc_vu_ten');
            $table->decimal('he_so_phu_cap', 5, 2);
        });
        Schema::create('nhan_vien', static function (Blueprint $table): void {
            $table->string('ma_nv', 10)->primary();
            $table->unsignedInteger('ma_cv')->nullable();
        });

        $this->repository = new ChucVuRepository(
            $this->app->make(DatabaseManager::class),
            new ChucVuExceptionMapper,
        );
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('nhan_vien');
        Schema::dropIfExists('chuc_vu');

        parent::tearDown();
    }

    public function test_all_and_find_return_explicit_rows_with_employee_counts_and_order(): void
    {
        $this->insertPosition('Nhân viên', '1');
        $this->insertPosition('Giám đốc', '2.5');
        $this->insertEmployee('NV001', 1);
        $this->insertEmployee('NV002', 1);

        $rows = $this->repository->all();

        $this->assertSame([
            'ma_cv', 'ten_cv', 'he_so_phu_cap', 'so_nhan_vien',
        ], array_keys(get_object_vars($rows[0])));
        $this->assertSame(1, $rows[0]->ma_cv);
        $this->assertSame('Nhân viên', $rows[0]->ten_cv);
        $this->assertSame('1.00', $rows[0]->he_so_phu_cap);
        $this->assertSame(2, $rows[0]->so_nhan_vien);
        $this->assertSame('2.50', $rows[1]->he_so_phu_cap);
        $this->assertSame(0, $rows[1]->so_nhan_vien);
        $this->assertSame(2, $this->repository->find(2)->ma_cv);
        $this->assertNull($this->repository->find(999));
    }

    public function test_create_and_update_trim_names_and_normalize_decimal_rate(): void
    {
        $this->repository->create('  Kế toán  ', '1.5');
        $created = $this->repository->find(1);

        $this->assertSame('Kế toán', $created->ten_cv);
        $this->assertSame('1.50', $created->he_so_phu_cap);

        $this->repository->update(1, '  Trưởng phòng ', '02');
        $updated = $this->repository->find(1);

        $this->assertSame('Trưởng phòng', $updated->ten_cv);
        $this->assertSame('2.00', $updated->he_so_phu_cap);
    }

    public function test_invalid_values_fail_closed_without_writes(): void
    {
        foreach ([
            ['', '1'], ['   ', '1'], [str_repeat('a', 101), '1'],
            ['Hợp lệ', ''], ['Hợp lệ', '-1'], ['Hợp lệ', '100'], ['Hợp lệ', '1.234'],
        ] as [$name, $rate]) {
            try {
                $this->repository->create($name, $rate);
                $this->fail('Expected invalid input to throw.');
            } catch (ChucVuDomainException $exception) {
                $this->assertStringStartsWith('CV_', $exception->domainCode);
            }
        }

        $this->assertSame(0, \DB::table('chuc_vu')->count());
    }

    public function test_duplicate_name_maps_to_safe_domain_error(): void
    {
        $this->insertPosition('Kế toán', '1');

        $this->expectException(ChucVuDomainException::class);
        try {
            $this->repository->create('  Kế toán ', '2');
        } catch (ChucVuDomainException $exception) {
            $this->assertSame('CV_NAME_DUPLICATE', $exception->domainCode);
            $this->assertSame('ten_cv', $exception->field);
            throw $exception;
        }
    }

    public function test_update_and_delete_missing_ids_are_not_found_and_invalid_ids_are_rejected(): void
    {
        foreach ([
            function (): void {
                $this->repository->update(999, 'Mới', '1');
            },
            function (): void {
                $this->repository->delete(999);
            },
        ] as $operation) {
            try {
                $operation();
                $this->fail('Expected missing position to throw.');
            } catch (ChucVuDomainException $exception) {
                $this->assertSame('CV_NOT_FOUND', $exception->domainCode);
            }
        }

        try {
            $this->repository->delete(0);
            $this->fail('Expected invalid ID to throw.');
        } catch (ChucVuDomainException $exception) {
            $this->assertSame('CV_ID_INVALID', $exception->domainCode);
        }
    }

    public function test_delete_rejects_positions_in_use_and_deletes_free_position(): void
    {
        $this->insertPosition('Kế toán', '1');
        $this->insertPosition('Nhân sự', '1');
        $this->insertEmployee('NV001', 1);

        try {
            $this->repository->delete(1);
            $this->fail('Expected in-use position to throw.');
        } catch (ChucVuDomainException $exception) {
            $this->assertSame('CV_IN_USE', $exception->domainCode);
            $this->assertSame('chuc_vu', $exception->field);
        }

        $this->repository->delete(2);
        $this->assertNull($this->repository->find(2));
        $this->assertSame(1, \DB::table('chuc_vu')->count());
    }

    private function insertPosition(string $name, string $rate): void
    {
        \DB::table('chuc_vu')->insert(['ten_cv' => $name, 'he_so_phu_cap' => $rate]);
    }

    private function insertEmployee(string $code, int $position): void
    {
        \DB::table('nhan_vien')->insert(['ma_nv' => $code, 'ma_cv' => $position]);
    }
}
