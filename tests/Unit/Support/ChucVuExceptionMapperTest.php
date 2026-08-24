<?php

namespace Tests\Unit\Support;

use App\Support\ChucVuExceptionMapper;
use Illuminate\Database\QueryException;
use PDOException;
use PHPUnit\Framework\TestCase;

class ChucVuExceptionMapperTest extends TestCase
{
    public function test_it_maps_unique_foreign_key_and_unknown_database_errors_without_leaking_sql(): void
    {
        $mapper = new ChucVuExceptionMapper;

        $duplicate = $mapper->map($this->queryException(
            "Duplicate entry 'Kế toán' for key 'uq_chuc_vu_ten'",
        ));
        $this->assertSame('CV_NAME_DUPLICATE', $duplicate->domainCode);
        $this->assertSame('ten_cv', $duplicate->field);

        $inUse = $mapper->map($this->queryException(
            'Cannot delete or update a parent row: a foreign key constraint fails (`nhan_vien`, CONSTRAINT `fk_nv_cv` FOREIGN KEY (`ma_cv`) REFERENCES `chuc_vu` (`ma_cv`))',
        ));
        $this->assertSame('CV_IN_USE', $inUse->domainCode);
        $this->assertSame('chuc_vu', $inUse->field);

        $unknown = $mapper->map($this->queryException('SQLSTATE[HY000] private table details'));
        $this->assertSame('CV_DATABASE_ERROR', $unknown->domainCode);
        $this->assertSame('Không thể xử lý chức vụ lúc này. Vui lòng thử lại sau.', $unknown->getMessage());
        $this->assertStringNotContainsString('private table details', $unknown->getMessage());
    }

    private function queryException(string $message): QueryException
    {
        return new QueryException('mysql', 'private sql', [], new PDOException($message));
    }
}
