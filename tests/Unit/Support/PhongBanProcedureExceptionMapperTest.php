<?php

namespace Tests\Unit\Support;

use App\Support\PhongBanProcedureExceptionMapper;
use Illuminate\Database\QueryException;
use PDOException;
use PHPUnit\Framework\TestCase;

class PhongBanProcedureExceptionMapperTest extends TestCase
{
    public function test_it_maps_name_unique_constraint_to_a_safe_duplicate_error(): void
    {
        $exception = new QueryException(
            'mysql',
            'CALL sp_phong_ban_them(?)',
            [],
            new PDOException("Duplicate entry 'Kỹ thuật' for key 'uq_phong_ban_ten_pb'"),
        );

        $mapped = (new PhongBanProcedureExceptionMapper)->map($exception);

        $this->assertSame('PB_NAME_DUPLICATE', $mapped->domainCode);
        $this->assertSame('Tên phòng ban đã tồn tại.', $mapped->getMessage());
        $this->assertSame('ten_pb', $mapped->field);
    }
}
