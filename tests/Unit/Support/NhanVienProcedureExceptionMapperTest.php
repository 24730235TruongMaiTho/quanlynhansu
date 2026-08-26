<?php

namespace Tests\Unit\Support;

use App\Exceptions\NhanVienDomainException;
use App\Support\NhanVienProcedureExceptionMapper;
use Illuminate\Database\QueryException;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class NhanVienProcedureExceptionMapperTest extends TestCase
{
    #[DataProvider('procedureDomainErrors')]
    public function test_it_maps_whitelisted_procedure_errors_to_safe_domain_errors(
        string $procedureCode,
        string $expectedMessage,
        ?string $expectedField,
    ): void {
        $mapped = (new NhanVienProcedureExceptionMapper())->map($this->queryException($procedureCode));

        $this->assertInstanceOf(NhanVienDomainException::class, $mapped);
        $this->assertSame($procedureCode, $mapped->domainCode);
        $this->assertSame($expectedMessage, $mapped->getMessage());
        $this->assertSame($expectedField, $mapped->field);
    }

    public static function procedureDomainErrors(): array
    {
        return [
            'not found' => ['NV_NOT_FOUND', 'Không tìm thấy nhân viên.', null],
            'duplicate email' => ['NV_EMAIL_DUPLICATE', 'Email đã được sử dụng.', 'email'],
            'duplicate cccd' => ['NV_CCCD_DUPLICATE', 'Số CCCD đã được sử dụng.', 'cccd'],
            'invalid reference' => ['NV_REFERENCE_INVALID', 'Dữ liệu tham chiếu không hợp lệ.', null],
            'code exhausted' => ['NV_CODE_EXHAUSTED', 'Đã hết mã nhân viên để cấp.', null],
            'missing status' => ['NV_STATUS_MISSING', 'Trạng thái làm việc không hợp lệ.', 'ma_tt'],
            'invalid default role' => ['NV_DEFAULT_ROLE_INVALID', 'Vai trò mặc định không hợp lệ.', null],
            'invalid pagination' => ['NV_PAGINATION_INVALID', 'Thông tin phân trang không hợp lệ.', null],
        ];
    }

    public function test_it_maps_email_unique_constraint_to_the_email_field(): void
    {
        $mapped = (new NhanVienProcedureExceptionMapper())->map(
            $this->queryException("Duplicate entry 'an@example.test' for key 'uq_nhan_vien_email'")
        );

        $this->assertSame('NV_EMAIL_DUPLICATE', $mapped->domainCode);
        $this->assertSame('Email đã được sử dụng.', $mapped->getMessage());
        $this->assertSame('email', $mapped->field);
    }

    public function test_it_maps_cccd_unique_constraint_to_the_cccd_field(): void
    {
        $mapped = (new NhanVienProcedureExceptionMapper())->map(
            $this->queryException("Duplicate entry '012345678901' for key 'uq_nhan_vien_cccd'")
        );

        $this->assertSame('NV_CCCD_DUPLICATE', $mapped->domainCode);
        $this->assertSame('Số CCCD đã được sử dụng.', $mapped->getMessage());
        $this->assertSame('cccd', $mapped->field);
    }

    public function test_it_maps_backtick_quoted_unique_constraint_to_the_email_field(): void
    {
        $mapped = (new NhanVienProcedureExceptionMapper())->map(
            $this->queryException('Duplicate entry for key `uq_nhan_vien_email`')
        );

        $this->assertSame('NV_EMAIL_DUPLICATE', $mapped->domainCode);
        $this->assertSame('email', $mapped->field);
    }

    #[DataProvider('lookalikeConstraintNames')]
    public function test_it_does_not_map_lookalike_constraint_names(string $databaseMessage): void
    {
        $mapped = (new NhanVienProcedureExceptionMapper())->map($this->queryException($databaseMessage));

        $this->assertSame('NV_DATABASE_ERROR', $mapped->domainCode);
        $this->assertSame('Không thể xử lý yêu cầu nhân viên. Vui lòng thử lại.', $mapped->getMessage());
        $this->assertNull($mapped->field);
    }

    public static function lookalikeConstraintNames(): array
    {
        return [
            'email suffix in single quotes' => ["Duplicate entry for key 'uq_nhan_vien_email_backup'"],
            'email prefix in backticks' => ['Duplicate entry for key `legacy_uq_nhan_vien_email`'],
            'cccd suffix in backticks' => ['Duplicate entry for key `uq_nhan_vien_cccd_legacy`'],
            'cccd prefix in single quotes' => ["Duplicate entry for key 'legacy_uq_nhan_vien_cccd'"],
        ];
    }

    #[DataProvider('undelimitedConstraintNames')]
    public function test_it_does_not_map_constraint_names_without_a_supported_delimiter(string $databaseMessage): void
    {
        $mapped = (new NhanVienProcedureExceptionMapper())->map($this->queryException($databaseMessage));

        $this->assertSame('NV_DATABASE_ERROR', $mapped->domainCode);
        $this->assertSame('Không thể xử lý yêu cầu nhân viên. Vui lòng thử lại.', $mapped->getMessage());
        $this->assertNull($mapped->field);
    }

    public static function undelimitedConstraintNames(): array
    {
        return [
            'bare email' => ['Duplicate entry for key uq_nhan_vien_email'],
            'bare cccd' => ['Duplicate entry for key uq_nhan_vien_cccd'],
            'double quoted email' => ['Duplicate entry for key "uq_nhan_vien_email"'],
            'double quoted cccd' => ['Duplicate entry for key "uq_nhan_vien_cccd"'],
        ];
    }

    public function test_it_hides_unknown_sql_errors(): void
    {
        $sql = 'insert into nhan_vien (mat_khau) values (\'$2y$12$secret-hash\')';
        $mapped = (new NhanVienProcedureExceptionMapper())->map(
            $this->queryException('SQLSTATE[HY000]: General error: 1234 database exploded', $sql)
        );

        $this->assertSame('NV_DATABASE_ERROR', $mapped->domainCode);
        $this->assertSame('Không thể xử lý yêu cầu nhân viên. Vui lòng thử lại.', $mapped->getMessage());
        $this->assertNull($mapped->field);
        $this->assertStringNotContainsString('SQLSTATE', $mapped->getMessage());
        $this->assertStringNotContainsString('secret-hash', $mapped->getMessage());
        $this->assertStringNotContainsString($sql, $mapped->getMessage());
    }

    private function queryException(string $message, string $sql = 'insert into nhan_vien (...) values (...)'): QueryException
    {
        return new QueryException('mysql', $sql, [], new PDOException($message));
    }
}
