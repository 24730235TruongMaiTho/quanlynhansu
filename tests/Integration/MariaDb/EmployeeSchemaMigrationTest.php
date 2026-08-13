<?php

namespace Tests\Integration\MariaDb;

use PDO;
use PDOException;

class EmployeeSchemaMigrationTest extends MariaDbTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->runSql(base_path('tests/Fixtures/MariaDb/employee_legacy_schema.sql'));
    }

    public function test_email_null_or_blank_fails_before_any_schema_or_data_change(): void
    {
        $this->pdo()->exec('ALTER TABLE nhan_vien MODIFY email NVARCHAR(100) NULL');
        $ids = $this->seedReferences();

        foreach ([null, '   '] as $index => $email) {
            $this->insertEmployee('NV00'.($index + 1), $email, '00000000000'.($index + 1), $ids);
            $this->assertMigrationFails('NV_MIGRATION_EMAIL_INVALID');
            $this->pdo()->exec('DELETE FROM nhan_vien');
        }
    }

    public function test_malformed_email_fails_before_ddl(): void
    {
        $ids = $this->seedReferences();
        $this->insertEmployee('NV001', 'khong-phai-email', '000000000001', $ids);

        $this->assertMigrationFails('NV_MIGRATION_EMAIL_INVALID');
    }

    public function test_duplicate_email_after_lowercase_and_trim_fails_before_ddl(): void
    {
        $ids = $this->seedReferences();
        $this->insertEmployee('NV001', '  AN@example.test ', '000000000001', $ids);
        $this->insertEmployee('NV002', 'an@EXAMPLE.TEST', '000000000002', $ids);

        $this->assertMigrationFails('NV_MIGRATION_EMAIL_INVALID');
    }

    public function test_cccd_null_or_blank_fails_before_any_schema_or_data_change(): void
    {
        $this->pdo()->exec('ALTER TABLE nhan_vien MODIFY cccd VARCHAR(20) NULL');
        $ids = $this->seedReferences();

        foreach ([null, '   '] as $index => $cccd) {
            $this->insertEmployee('NV00'.($index + 1), 'nv'.$index.'@example.test', $cccd, $ids);
            $this->assertMigrationFails('NV_MIGRATION_CCCD_INVALID');
            $this->pdo()->exec('DELETE FROM nhan_vien');
        }
    }

    public function test_cccd_with_fewer_than_twelve_digits_fails_before_ddl(): void
    {
        $ids = $this->seedReferences();
        $this->insertEmployee('NV001', 'an@example.test', '12345678901', $ids);

        $this->assertMigrationFails('NV_MIGRATION_CCCD_INVALID');
    }

    public function test_duplicate_cccd_after_trim_fails_before_ddl(): void
    {
        $this->pdo()->exec('ALTER TABLE nhan_vien MODIFY cccd VARCHAR(20) NOT NULL');
        $ids = $this->seedReferences();
        $this->insertEmployee('NV001', 'an@example.test', '123456789012', $ids);
        $this->insertEmployee('NV002', 'binh@example.test', ' 123456789012 ', $ids);

        $this->assertMigrationFails('NV_MIGRATION_CCCD_INVALID');
    }

    public function test_employee_code_outside_nv_three_digit_contract_fails_before_ddl(): void
    {
        $ids = $this->seedReferences();
        $this->insertEmployee('AB001', 'an@example.test', '123456789012', $ids);

        $this->assertMigrationFails('NV_MIGRATION_CCCD_INVALID');
    }

    public function test_employee_code_nv000_fails_before_ddl(): void
    {
        $ids = $this->seedReferences();
        $this->insertEmployee('NV000', 'an@example.test', '123456789012', $ids);

        $this->assertMigrationFails('NV_MIGRATION_CCCD_INVALID');
    }

    public function test_missing_status_mapping_fails_before_ddl(): void
    {
        $this->insertStatuses(['Đang làm việc', 'Thử việc']);

        $this->assertMigrationFails('NV_MIGRATION_STATUS_AMBIGUOUS');
    }

    public function test_duplicate_status_mapping_fails_before_ddl(): void
    {
        $this->insertStatuses(['Đang làm việc', ' ĐANG LÀM VIỆC ', 'Thử việc', 'Đã nghỉ']);

        $this->assertMigrationFails('NV_MIGRATION_STATUS_AMBIGUOUS');
    }

    public function test_unrecognized_extra_status_fails_before_ddl(): void
    {
        $this->insertStatuses(['Đang làm việc', 'Thử việc', 'Đã nghỉ', 'Đang công tác']);

        $this->assertMigrationFails('NV_MIGRATION_STATUS_AMBIGUOUS');
    }

    public function test_status_label_with_missing_accent_is_not_mapped_as_a_system_status(): void
    {
        $this->insertStatuses(['Đang lam việc', 'Thử việc', 'Đã nghỉ']);

        $this->assertMigrationFails('NV_MIGRATION_STATUS_AMBIGUOUS');
    }

    public function test_existing_terminated_employee_requires_a_confirmed_termination_date(): void
    {
        $ids = $this->seedReferences();
        $terminatedStatus = (int) $this->pdo()->query(
            "SELECT ma_tt FROM trang_thai_lam_viec WHERE LOWER(TRIM(ten_tt)) = 'đã nghỉ'"
        )->fetchColumn();
        $ids['status'] = $terminatedStatus;
        $this->insertEmployee('NV001', 'an@example.test', '123456789012', $ids);

        $this->assertMigrationFails('NV_MIGRATION_EXISTING_TERMINATION_DATE_REQUIRED');
    }

    public function test_duplicate_normalized_default_role_name_fails_before_ddl(): void
    {
        $statement = $this->pdo()->prepare('INSERT INTO vai_tro (ten_vt, mo_ta) VALUES (?, NULL), (?, NULL)');
        $statement->execute(['Nhân viên mặc định', ' NHÂN VIÊN MẶC ĐỊNH ']);

        $this->assertMigrationFails('NV_MIGRATION_ROLE_AMBIGUOUS');
    }

    public function test_default_role_candidate_with_permission_fails_before_ddl(): void
    {
        $this->pdo()->exec("INSERT INTO vai_tro (ten_vt, mo_ta) VALUES ('Nhân viên mặc định', NULL)");
        $roleId = (int) $this->pdo()->lastInsertId();
        $this->pdo()->exec("INSERT INTO quyen (ma_quyen, ky_hieu_quyen, ten_quyen, module) VALUES (1, 'LEGACY', 'Legacy', 'TEST')");
        $this->pdo()->exec("INSERT INTO vai_tro_quyen (ma_vt, ma_quyen) VALUES ({$roleId}, 1)");

        $this->assertMigrationFails('NV_MIGRATION_ROLE_AMBIGUOUS');
    }

    public function test_existing_default_symbol_on_a_different_role_fails_before_ddl(): void
    {
        $this->pdo()->exec('ALTER TABLE vai_tro ADD ky_hieu VARCHAR(50) NULL');
        $this->pdo()->exec(
            "INSERT INTO vai_tro (ten_vt, mo_ta, ky_hieu) VALUES ('Quản trị', NULL, 'NHAN_VIEN_MAC_DINH')"
        );

        $this->assertMigrationFails('NV_MIGRATION_ROLE_AMBIGUOUS');
        $this->assertFoundationObjectsAbsent(true);
    }

    public function test_case_variant_default_symbol_on_a_different_role_fails_before_ddl(): void
    {
        $this->pdo()->exec('ALTER TABLE vai_tro ADD ky_hieu VARCHAR(50) NULL');
        $this->pdo()->exec(
            "INSERT INTO vai_tro (ten_vt, mo_ta, ky_hieu) VALUES ('Quản trị', NULL, 'nhan_vien_mac_dinh')"
        );

        $this->assertMigrationFails('NV_MIGRATION_ROLE_AMBIGUOUS');
        $this->assertFoundationObjectsAbsent(true);
    }

    public function test_existing_default_role_candidate_with_a_conflicting_symbol_fails_before_ddl(): void
    {
        $this->pdo()->exec('ALTER TABLE vai_tro ADD ky_hieu VARCHAR(50) NULL');
        $this->pdo()->exec(
            "INSERT INTO vai_tro (ten_vt, mo_ta, ky_hieu) VALUES ('Nhân viên mặc định', NULL, 'LEGACY_ROLE')"
        );

        $this->assertMigrationFails('NV_MIGRATION_ROLE_AMBIGUOUS');
        $this->assertFoundationObjectsAbsent(true);
    }

    public function test_default_role_label_with_missing_accent_is_not_claimed_as_the_system_role(): void
    {
        $this->pdo()->exec("INSERT INTO vai_tro (ten_vt, mo_ta) VALUES ('Nhân viên mac định', 'Legacy')");
        $legacyRoleId = (int) $this->pdo()->lastInsertId();

        $this->runMigration();

        $statement = $this->pdo()->prepare('SELECT ky_hieu FROM vai_tro WHERE ma_vt = ?');
        $statement->execute([$legacyRoleId]);
        $this->assertNull($statement->fetchColumn());
        $this->assertSame(1, (int) $this->pdo()->query(
            "SELECT COUNT(*) FROM vai_tro
             WHERE BINARY LOWER(TRIM(ten_vt)) = BINARY 'nhân viên mặc định'
               AND BINARY ky_hieu = BINARY 'NHAN_VIEN_MAC_DINH'"
        )->fetchColumn());
    }

    public function test_clean_empty_legacy_schema_gains_the_employee_foundation(): void
    {
        $this->runMigration();

        $this->assertSame(
            ['DA_NGHI' => 'Đã nghỉ', 'DANG_LAM' => 'Đang làm việc', 'THU_VIEC' => 'Thử việc'],
            $this->pdo()->query('SELECT ky_hieu, ten_tt FROM trang_thai_lam_viec ORDER BY ky_hieu')
                ->fetchAll(PDO::FETCH_KEY_PAIR)
        );
        $this->assertSame(1, (int) $this->pdo()->query(
            "SELECT COUNT(*) FROM vai_tro WHERE ky_hieu = 'NHAN_VIEN_MAC_DINH'"
        )->fetchColumn());
        $this->assertSame(0, (int) $this->pdo()->query(
            "SELECT COUNT(*) FROM vai_tro_quyen vtq JOIN vai_tro vt ON vt.ma_vt = vtq.ma_vt
             WHERE vt.ky_hieu = 'NHAN_VIEN_MAC_DINH'"
        )->fetchColumn());
        $this->assertSame('0', (string) $this->pdo()->query(
            "SELECT so_da_cap FROM bo_dem_ma_nhan_vien WHERE ten_bo_dem = 'NHAN_VIEN'"
        )->fetchColumn());
        $this->assertFoundationColumnsAndConstraints();
        $this->assertAddressCascade();
    }

    public function test_clean_legacy_rows_are_normalized_and_counter_uses_largest_suffix(): void
    {
        $ids = $this->seedReferences('Nhân viên mặc định');
        $this->insertEmployee('NV007', '  AN@example.test ', '000000000007', $ids);
        $this->insertEmployee('NV042', 'Binh@Example.Test', '000000000042', $ids);

        $this->runMigration();

        $rows = $this->pdo()->query('SELECT ma_nv, email, cccd FROM nhan_vien ORDER BY ma_nv')->fetchAll(PDO::FETCH_ASSOC);
        $this->assertSame('an@example.test', $rows[0]['email']);
        $this->assertSame('binh@example.test', $rows[1]['email']);
        $this->assertSame('42', (string) $this->pdo()->query(
            "SELECT so_da_cap FROM bo_dem_ma_nhan_vien WHERE ten_bo_dem = 'NHAN_VIEN'"
        )->fetchColumn());
        $this->assertSame(1, (int) $this->pdo()->query(
            "SELECT COUNT(*) FROM vai_tro WHERE ky_hieu = 'NHAN_VIEN_MAC_DINH'"
        )->fetchColumn());
    }

    public function test_existing_counter_is_never_lowered(): void
    {
        $this->pdo()->exec(
            'CREATE TABLE bo_dem_ma_nhan_vien (
                ten_bo_dem VARCHAR(30) PRIMARY KEY,
                so_da_cap SMALLINT UNSIGNED NOT NULL
            )'
        );
        $this->pdo()->exec("INSERT INTO bo_dem_ma_nhan_vien VALUES ('NHAN_VIEN', 250)");

        $this->runMigration();

        $this->assertSame('250', (string) $this->pdo()->query(
            "SELECT so_da_cap FROM bo_dem_ma_nhan_vien WHERE ten_bo_dem = 'NHAN_VIEN'"
        )->fetchColumn());
    }

    private function runMigration(): void
    {
        $this->runSql(base_path('database/sql/employee/2026_08_12_001_schema.sql'));
    }

    private function assertMigrationFails(string $errorCode): void
    {
        $before = $this->databaseSnapshot();

        try {
            $this->runMigration();
            $this->fail("Migration should fail with {$errorCode}.");
        } catch (PDOException $exception) {
            $this->assertStringContainsString($errorCode, $exception->getMessage());
        }

        $this->assertSame($before, $this->databaseSnapshot(), 'A failed preflight must not mutate schema or data.');
        $this->assertFoundationObjectsAbsent($this->columnExists('vai_tro', 'ky_hieu'));
    }

    private function databaseSnapshot(): array
    {
        $schema = $this->pdo()->query(
            "SELECT TABLE_NAME, COLUMN_NAME, ORDINAL_POSITION, COLUMN_TYPE, IS_NULLABLE
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
             ORDER BY TABLE_NAME, ORDINAL_POSITION"
        )->fetchAll(PDO::FETCH_ASSOC);

        return [
            'schema' => $schema,
            'nhan_vien' => $this->pdo()->query('SELECT * FROM nhan_vien ORDER BY ma_nv')->fetchAll(PDO::FETCH_ASSOC),
            'status' => $this->pdo()->query('SELECT * FROM trang_thai_lam_viec ORDER BY ma_tt')->fetchAll(PDO::FETCH_ASSOC),
            'roles' => $this->pdo()->query('SELECT * FROM vai_tro ORDER BY ma_vt')->fetchAll(PDO::FETCH_ASSOC),
            'role_permissions' => $this->pdo()->query('SELECT * FROM vai_tro_quyen ORDER BY ma_vt, ma_quyen')->fetchAll(PDO::FETCH_ASSOC),
        ];
    }

    private function assertFoundationObjectsAbsent(bool $roleSymbolPreexisted = false): void
    {
        $this->assertFalse($this->columnExists('trang_thai_lam_viec', 'ky_hieu'));
        $this->assertFalse($this->columnExists('nhan_vien', 'anh_dai_dien'));
        $this->assertFalse($this->columnExists('nhan_vien', 'ngay_nghi_viec'));
        $this->assertSame($roleSymbolPreexisted, $this->columnExists('vai_tro', 'ky_hieu'));
        $this->assertFalse($this->tableExists('dia_chi_nhan_vien'));
        $this->assertFalse($this->tableExists('bo_dem_ma_nhan_vien'));
    }

    private function assertFoundationColumnsAndConstraints(): void
    {
        $definitions = [
            ['trang_thai_lam_viec', 'ky_hieu', 'varchar(20)', 'NO'],
            ['vai_tro', 'ky_hieu', 'varchar(50)', 'YES'],
            ['nhan_vien', 'anh_dai_dien', 'varchar(255)', 'YES'],
            ['nhan_vien', 'ngay_nghi_viec', 'date', 'YES'],
            ['bo_dem_ma_nhan_vien', 'so_da_cap', 'smallint(5) unsigned', 'NO'],
        ];
        foreach ($definitions as [$table, $column, $type, $nullable]) {
            $statement = $this->pdo()->prepare(
                'SELECT COLUMN_TYPE, IS_NULLABLE FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
            );
            $statement->execute([$table, $column]);
            $this->assertSame(
                ['COLUMN_TYPE' => $type, 'IS_NULLABLE' => $nullable],
                $statement->fetch(PDO::FETCH_ASSOC),
                "Unexpected definition for {$table}.{$column}."
            );
        }

        $this->assertTrue($this->tableExists('dia_chi_nhan_vien'));
        $this->assertTrue($this->tableExists('bo_dem_ma_nhan_vien'));
        foreach ([
            ['nhan_vien', 'uq_nhan_vien_email'],
            ['nhan_vien', 'uq_nhan_vien_cccd'],
            ['trang_thai_lam_viec', 'uq_trang_thai_lam_viec_ky_hieu'],
            ['vai_tro', 'uq_vai_tro_ky_hieu'],
        ] as [$table, $index]) {
            $statement = $this->pdo()->prepare(
                'SELECT COUNT(*) FROM information_schema.STATISTICS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ? AND NON_UNIQUE = 0'
            );
            $statement->execute([$table, $index]);
            $this->assertSame(1, (int) $statement->fetchColumn(), "Missing unique index {$index}.");
        }

        $this->assertSame(1, (int) $this->pdo()->query(
            "SELECT COUNT(*) FROM information_schema.CHECK_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = DATABASE()
               AND CONSTRAINT_NAME = 'ck_nhan_vien_ma_nv'
               AND CHECK_CLAUSE LIKE '%00[1-9]%'
               AND CHECK_CLAUSE LIKE '%[1-9][0-9]{2}%'"
        )->fetchColumn());
    }

    private function assertAddressCascade(): void
    {
        $ids = $this->seedReferencesAfterMigration();
        $this->insertEmployee('NV001', 'cascade@example.test', '100000000001', $ids);
        $this->pdo()->exec(
            "INSERT INTO dia_chi_nhan_vien VALUES ('NV001', 'Số 1', 'Phường 1', 'Quận 1', 'TP HCM')"
        );
        $this->pdo()->exec("DELETE FROM nhan_vien WHERE ma_nv = 'NV001'");

        $this->assertSame(0, (int) $this->pdo()->query(
            "SELECT COUNT(*) FROM dia_chi_nhan_vien WHERE ma_nv = 'NV001'"
        )->fetchColumn());
        $this->assertSame('CASCADE', $this->pdo()->query(
            "SELECT DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'dia_chi_nhan_vien'"
        )->fetchColumn());
    }

    private function seedReferences(string $roleName = 'Vai trò cũ'): array
    {
        $this->insertStatuses(['Đang làm việc', 'Thử việc', 'Đã nghỉ']);
        $this->pdo()->exec("INSERT INTO phong_ban (ten_pb) VALUES ('Phòng thử nghiệm')");
        $department = (int) $this->pdo()->lastInsertId();
        $this->pdo()->exec("INSERT INTO chuc_vu (ten_cv, he_so_phu_cap) VALUES ('Chuyên viên', 0.10)");
        $position = (int) $this->pdo()->lastInsertId();
        $statement = $this->pdo()->prepare('INSERT INTO vai_tro (ten_vt, mo_ta) VALUES (?, NULL)');
        $statement->execute([$roleName]);
        $role = (int) $this->pdo()->lastInsertId();
        $status = (int) $this->pdo()->query(
            "SELECT ma_tt FROM trang_thai_lam_viec WHERE LOWER(TRIM(ten_tt)) = 'đang làm việc'"
        )->fetchColumn();

        return compact('department', 'position', 'role', 'status');
    }

    private function seedReferencesAfterMigration(): array
    {
        $this->pdo()->exec("INSERT INTO phong_ban (ten_pb) VALUES ('Phòng thử nghiệm')");
        $department = (int) $this->pdo()->lastInsertId();
        $this->pdo()->exec("INSERT INTO chuc_vu (ten_cv, he_so_phu_cap) VALUES ('Chuyên viên', 0.10)");
        $position = (int) $this->pdo()->lastInsertId();
        $role = (int) $this->pdo()->query(
            "SELECT ma_vt FROM vai_tro WHERE ky_hieu = 'NHAN_VIEN_MAC_DINH'"
        )->fetchColumn();
        $status = (int) $this->pdo()->query(
            "SELECT ma_tt FROM trang_thai_lam_viec WHERE ky_hieu = 'DANG_LAM'"
        )->fetchColumn();

        return compact('department', 'position', 'role', 'status');
    }

    private function insertStatuses(array $names): void
    {
        $statement = $this->pdo()->prepare('INSERT INTO trang_thai_lam_viec (ten_tt) VALUES (?)');
        foreach ($names as $name) {
            $statement->execute([$name]);
        }
    }

    private function insertEmployee(string $code, ?string $email, ?string $cccd, array $ids): void
    {
        $statement = $this->pdo()->prepare(
            'INSERT INTO nhan_vien (
                ma_nv, ho_ten, ngay_sinh, gioi_tinh, sdt, email, ngay_vao_lam,
                ma_pb, ma_cv, dan_toc, cccd, noi_cap_cccd, hoc_van, ma_tt, mat_khau, ma_vt
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $statement->execute([
            $code, 'Nhân viên '.$code, '1990-01-01', 1, '0900000000', $email, '2020-01-01',
            $ids['department'], $ids['position'], 'Kinh', $cccd, 'TP HCM', 'Đại học',
            $ids['status'], str_repeat('a', 64), $ids['role'],
        ]);
    }

    private function columnExists(string $table, string $column): bool
    {
        $statement = $this->pdo()->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $statement->execute([$table, $column]);

        return (int) $statement->fetchColumn() === 1;
    }

    private function tableExists(string $table): bool
    {
        $statement = $this->pdo()->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
        );
        $statement->execute([$table]);

        return (int) $statement->fetchColumn() === 1;
    }
}
