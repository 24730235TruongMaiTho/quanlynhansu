<?php

namespace Tests\Integration\MariaDb;

use PDO;
use PDOException;

class EmployeeCreateProcedureTest extends MariaDbTestCase
{
    private int $department;

    private int $position;

    private int $workingStatus;

    private int $terminatedStatus;

    protected function setUp(): void
    {
        parent::setUp();

        $this->runSql(base_path('tests/Fixtures/MariaDb/employee_legacy_schema.sql'));
        $this->runSql(base_path('database/sql/employee/2026_08_12_001_schema.sql'));
        $this->runSql(base_path('database/sql/employee/2026_08_12_002_read_routines.sql'));

        $createScript = base_path('database/sql/employee/2026_08_12_003_create_routines.sql');
        if (is_file($createScript)) {
            $this->runSql($createScript);
        }

        $this->seedLookups();
    }

    public function test_codes_are_sequential_and_a_rolled_back_code_is_reused(): void
    {
        $this->assertSame('NV001', $this->createEmployee());
        $this->assertSame('NV002', $this->createEmployee([
            'email' => 'second@example.test',
            'cccd' => '001200000002',
        ]));

        $this->pdo()->beginTransaction();
        $this->assertSame('NV003', $this->createEmployee([
            'email' => 'rolled-back@example.test',
            'cccd' => '001200000003',
        ]));
        $this->pdo()->rollBack();

        $this->assertSame('NV003', $this->createEmployee([
            'email' => 'third@example.test',
            'cccd' => '001200000004',
        ]));
    }

    public function test_exhausted_counter_fails_without_inserting_or_advancing(): void
    {
        $this->pdo()->exec("UPDATE bo_dem_ma_nhan_vien SET so_da_cap = 999 WHERE ten_bo_dem = 'NHAN_VIEN'");

        $this->assertProcedureError('NV_CODE_EXHAUSTED', fn (): string => $this->createEmployee());

        $this->assertSame(0, (int) $this->pdo()->query('SELECT COUNT(*) FROM nhan_vien')->fetchColumn());
        $this->assertSame(999, (int) $this->pdo()->query(
            "SELECT so_da_cap FROM bo_dem_ma_nhan_vien WHERE ten_bo_dem = 'NHAN_VIEN'"
        )->fetchColumn());
    }

    public function test_email_and_cccd_are_normalized_and_duplicates_are_safe_domain_errors(): void
    {
        $this->assertSame('NV001', $this->createEmployee([
            'email' => '  FIRST@EXAMPLE.TEST  ',
            'cccd' => '001200000001',
        ]));

        $row = $this->pdo()->query("SELECT email, cccd FROM nhan_vien WHERE ma_nv = 'NV001'")
            ->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('first@example.test', $row['email']);
        $this->assertSame('001200000001', $row['cccd']);

        $this->assertProcedureError('NV_EMAIL_DUPLICATE', fn (): string => $this->createEmployee([
            'email' => 'First@Example.Test',
            'cccd' => '001200000002',
        ]));
        $this->assertProcedureError('NV_CCCD_DUPLICATE', fn (): string => $this->createEmployee([
            'email' => 'second@example.test',
            'cccd' => '001200000001',
        ]));
        $this->assertSame(1, (int) $this->pdo()->query('SELECT COUNT(*) FROM nhan_vien')->fetchColumn());
    }

    public function test_missing_references_underage_and_terminated_status_fail_before_insert(): void
    {
        foreach ([
            ['ma_pb' => 999, 'expected' => 'NV_REFERENCE_INVALID'],
            ['ma_cv' => 999, 'expected' => 'NV_REFERENCE_INVALID'],
            ['ma_tt' => 127, 'expected' => 'NV_STATUS_MISSING'],
            ['ngay_sinh' => '2010-01-01', 'expected' => 'NV_REFERENCE_INVALID'],
            ['ma_tt' => $this->terminatedStatus, 'expected' => 'NV_STATUS_MISSING'],
        ] as $index => $case) {
            $expected = $case['expected'];
            unset($case['expected']);
            $case['email'] = "invalid-{$index}@example.test";
            $case['cccd'] = sprintf('0012000001%02d', $index);

            $this->assertProcedureError($expected, fn (): string => $this->createEmployee($case));
        }

        $this->assertSame(0, (int) $this->pdo()->query('SELECT COUNT(*) FROM nhan_vien')->fetchColumn());
    }

    public function test_default_role_is_exact_zero_permission_and_never_client_selected(): void
    {
        $role = $this->defaultRoleId();
        $this->assertSame('NV001', $this->createEmployee());
        $this->assertSame($role, (int) $this->pdo()->query(
            "SELECT ma_vt FROM nhan_vien WHERE ma_nv = 'NV001'"
        )->fetchColumn());

        $this->pdo()->exec("DELETE FROM nhan_vien WHERE ma_nv = 'NV001'");
        $this->pdo()->exec("DELETE FROM vai_tro WHERE ma_vt = {$role}");
        $this->assertProcedureError('NV_DEFAULT_ROLE_INVALID', fn (): string => $this->createEmployee([
            'email' => 'missing-role@example.test',
            'cccd' => '001200000002',
        ]));
    }

    public function test_ambiguous_or_permission_bearing_default_role_fails_closed(): void
    {
        $this->pdo()->exec('ALTER TABLE vai_tro DROP INDEX uq_vai_tro_ky_hieu');
        $this->pdo()->exec(
            "INSERT INTO vai_tro (ten_vt, mo_ta, ky_hieu)
             VALUES ('Nhân viên mặc định trùng', 'fixture', 'NHAN_VIEN_MAC_DINH')"
        );
        $this->assertProcedureError('NV_DEFAULT_ROLE_INVALID', fn (): string => $this->createEmployee());

        $this->pdo()->exec("DELETE FROM vai_tro WHERE ten_vt = 'Nhân viên mặc định trùng'");
        $role = $this->defaultRoleId();
        $this->pdo()->exec(
            "INSERT INTO quyen (ma_quyen, ky_hieu_quyen, ten_quyen, module)
             VALUES (901, 'NV_TEST', 'Quyền test', 'NHAN_VIEN')"
        );
        $this->pdo()->exec("INSERT INTO vai_tro_quyen (ma_vt, ma_quyen) VALUES ({$role}, 901)");
        $this->assertProcedureError('NV_DEFAULT_ROLE_INVALID', fn (): string => $this->createEmployee());
    }

    public function test_password_hash_is_stored_exactly_and_address_upsert_keeps_one_row(): void
    {
        $hash = '$2y$12$'.str_repeat('a', 53);
        $maNv = $this->createEmployee(['password_hash' => $hash]);

        $statement = $this->pdo()->prepare('SELECT mat_khau FROM nhan_vien WHERE ma_nv = ?');
        $statement->execute([$maNv]);
        $this->assertSame($hash, $statement->fetchColumn());

        $this->upsertAddress($maNv, ['Số 1', 'Phường A', 'Quận A', 'TP A']);
        $this->upsertAddress($maNv, ['Số 2', 'Phường B', 'Quận B', 'TP B']);

        $address = $this->pdo()->query("SELECT * FROM dia_chi_nhan_vien WHERE ma_nv = '{$maNv}'")
            ->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(1, (int) $this->pdo()->query(
            "SELECT COUNT(*) FROM dia_chi_nhan_vien WHERE ma_nv = '{$maNv}'"
        )->fetchColumn());
        $this->assertSame([
            'ma_nv' => $maNv,
            'dia_chi_cu_the' => 'Số 2',
            'phuong_xa' => 'Phường B',
            'quan_huyen' => 'Quận B',
            'tinh_thanh' => 'TP B',
        ], $address);
    }

    public function test_create_and_address_procedures_do_not_control_transactions(): void
    {
        foreach (['sp_nhan_vien_them', 'sp_dia_chi_nhan_vien_luu'] as $procedure) {
            $statement = $this->pdo()->prepare(
                'SELECT ROUTINE_DEFINITION FROM information_schema.ROUTINES
                 WHERE ROUTINE_SCHEMA = DATABASE() AND ROUTINE_NAME = ? AND ROUTINE_TYPE = ?'
            );
            $statement->execute([$procedure, 'PROCEDURE']);
            $definition = (string) $statement->fetchColumn();

            $this->assertNotSame('', $definition, "Missing procedure {$procedure}.");
            $this->assertDoesNotMatchRegularExpression(
                '/\b(?:START\s+TRANSACTION|COMMIT|ROLLBACK)\b/i',
                $definition,
                "Procedure {$procedure} must use the caller transaction.",
            );
        }
    }

    private function seedLookups(): void
    {
        $this->pdo()->exec("INSERT INTO phong_ban (ten_pb) VALUES ('Kỹ thuật')");
        $this->department = (int) $this->pdo()->lastInsertId();
        $this->pdo()->exec("INSERT INTO chuc_vu (ten_cv, he_so_phu_cap) VALUES ('Lập trình viên', 0.20)");
        $this->position = (int) $this->pdo()->lastInsertId();
        $this->workingStatus = (int) $this->pdo()->query(
            "SELECT ma_tt FROM trang_thai_lam_viec WHERE BINARY ky_hieu = BINARY 'DANG_LAM'"
        )->fetchColumn();
        $this->terminatedStatus = (int) $this->pdo()->query(
            "SELECT ma_tt FROM trang_thai_lam_viec WHERE BINARY ky_hieu = BINARY 'DA_NGHI'"
        )->fetchColumn();
    }

    private function defaultRoleId(): int
    {
        return (int) $this->pdo()->query(
            "SELECT ma_vt FROM vai_tro WHERE BINARY ky_hieu = BINARY 'NHAN_VIEN_MAC_DINH'"
        )->fetchColumn();
    }

    private function createEmployee(array $overrides = []): string
    {
        $data = array_replace([
            'ho_ten' => 'Nguyễn An',
            'ngay_sinh' => '1990-01-01',
            'gioi_tinh' => 1,
            'sdt' => '0901234567',
            'email' => 'first@example.test',
            'ngay_vao_lam' => '2026-08-12',
            'ma_pb' => $this->department,
            'ma_cv' => $this->position,
            'dan_toc' => 'Kinh',
            'cccd' => '001200000001',
            'noi_cap_cccd' => 'Cục CSQLHC',
            'hoc_van' => 'Đại học',
            'ma_tt' => $this->workingStatus,
            'password_hash' => '$2y$12$'.str_repeat('x', 53),
            'avatar_path' => null,
        ], $overrides);

        $this->pdo()->exec('SET @nv_ma = NULL');
        $statement = $this->pdo()->prepare(
            'CALL sp_nhan_vien_them(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, @nv_ma)'
        );
        $statement->execute(array_values($data));
        $statement->closeCursor();

        return (string) $this->pdo()->query('SELECT @nv_ma')->fetchColumn();
    }

    private function upsertAddress(string $maNv, array $address): void
    {
        $statement = $this->pdo()->prepare('CALL sp_dia_chi_nhan_vien_luu(?, ?, ?, ?, ?)');
        $statement->execute([$maNv, ...$address]);
        $statement->closeCursor();
    }

    private function assertProcedureError(string $code, callable $operation): void
    {
        try {
            $operation();
            $this->fail("Expected procedure error {$code}.");
        } catch (PDOException $exception) {
            $this->assertStringContainsString($code, $exception->getMessage());
        }
    }
}
