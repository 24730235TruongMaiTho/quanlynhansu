<?php

namespace Tests\Unit\Support;

use App\Support\PhongBanExceptionMapper;
use Illuminate\Database\QueryException;
use PDOException;
use PHPUnit\Framework\TestCase;

class PhongBanExceptionMapperTest extends TestCase
{
    public function test_it_maps_fresh_and_legacy_name_unique_constraints_to_a_safe_duplicate_error(): void
    {
        foreach ([
            "Duplicate entry 'Kỹ thuật' for key 'uq_phong_ban_ten'",
            "Duplicate entry 'Kỹ thuật' for key 'uq_phong_ban_ten_pb'",
            "Duplicate entry 'Kỹ thuật' for key 'phong_ban.uq_phong_ban_ten'",
            'UNIQUE constraint failed: phong_ban.ten_pb',
        ] as $message) {
            $mapped = (new PhongBanExceptionMapper)->map($this->queryException($message));

            $this->assertSame('PB_NAME_DUPLICATE', $mapped->domainCode);
            $this->assertSame('Tên phòng ban đã tồn tại.', $mapped->getMessage());
            $this->assertSame('ten_pb', $mapped->field);
        }
    }

    public function test_it_maps_dependency_constraint_to_a_safe_in_use_error(): void
    {
        $mapped = (new PhongBanExceptionMapper)->map($this->queryException(
            'Cannot delete or update a parent row: a foreign key constraint fails (`nhan_vien`)',
        ));

        $this->assertSame('PB_IN_USE', $mapped->domainCode);
        $this->assertSame('phong_ban', $mapped->field);
    }

    public function test_unknown_database_errors_are_generic_and_do_not_leak_details(): void
    {
        $mapped = (new PhongBanExceptionMapper)->map($this->queryException('secret SQLSTATE and table details'));

        $this->assertSame('PB_DATABASE_ERROR', $mapped->domainCode);
        $this->assertSame('Không thể xử lý phòng ban lúc này. Vui lòng thử lại sau.', $mapped->getMessage());
        $this->assertStringNotContainsString('secret', $mapped->getMessage());
    }

    private function queryException(string $message): QueryException
    {
        return new QueryException('sqlite', 'insert into phong_ban (...) values (...)', [], new PDOException($message));
    }
}
