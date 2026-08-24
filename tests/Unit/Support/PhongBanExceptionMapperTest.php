<?php

namespace Tests\Unit\Support;

use App\Support\PhongBanExceptionMapper;
use Illuminate\Database\QueryException;
use PDOException;
use PHPUnit\Framework\TestCase;

class PhongBanExceptionMapperTest extends TestCase
{
    public function test_it_maps_fresh_legacy_and_sqlite_unique_errors_to_a_safe_duplicate(): void
    {
        $mapper = new PhongBanExceptionMapper;

        foreach ([
            "Duplicate entry 'Kỹ thuật' for key 'uq_phong_ban_ten'",
            "Duplicate entry 'Kỹ thuật' for key 'uq_phong_ban_ten_pb'",
            'UNIQUE constraint failed: phong_ban.ten_pb',
        ] as $message) {
            $mapped = $mapper->map($this->queryException($message));

            $this->assertSame('PB_NAME_DUPLICATE', $mapped->domainCode);
            $this->assertSame('Tên phòng ban đã tồn tại.', $mapped->getMessage());
            $this->assertSame('ten_pb', $mapped->field);
        }
    }

    public function test_it_maps_dependency_and_unknown_database_errors_without_leaking_sql(): void
    {
        $inUse = (new PhongBanExceptionMapper)->map($this->queryException(
            'Cannot delete or update a parent row: a foreign key constraint fails (`nhan_vien`, CONSTRAINT `fk_nv_pb` FOREIGN KEY (`ma_pb`) REFERENCES `phong_ban` (`ma_pb`))',
        ));
        $this->assertSame('PB_IN_USE', $inUse->domainCode);
        $this->assertSame('phong_ban', $inUse->field);

        $unknown = (new PhongBanExceptionMapper)->map($this->queryException('SQLSTATE[HY000] private table details'));
        $this->assertSame('PB_DATABASE_ERROR', $unknown->domainCode);
        $this->assertSame('Không thể xử lý phòng ban lúc này. Vui lòng thử lại sau.', $unknown->getMessage());
        $this->assertStringNotContainsString('private table details', $unknown->getMessage());
    }

    private function queryException(string $message): QueryException
    {
        return new QueryException('mysql', 'private sql', [], new PDOException($message));
    }
}
