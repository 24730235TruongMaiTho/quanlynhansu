<?php

namespace Tests\Integration\MariaDb;

use App\Contracts\NhanVienRepositoryContract;
use App\Exceptions\NhanVienDomainException;
use PDO;
use PDOException;

class EmployeeRbacProcedureTest extends MariaDbTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->runSql(base_path('tests/Fixtures/MariaDb/employee_legacy_schema.sql'));
        $this->runSql(base_path('database/sql/employee/2026_08_12_001_schema.sql'));
        $this->runSql(base_path('database/sql/employee/2026_08_12_002_read_routines.sql'));
        $this->runSql(base_path('database/sql/employee/2026_08_12_003_create_routines.sql'));
        $this->runSql(base_path('database/sql/employee/2026_08_12_004_update_routines.sql'));
        $this->runSql(base_path('database/sql/employee/2026_08_12_005_lifecycle_auth_routines.sql'));
    }

    public function test_non_positive_permission_id_fails_before_any_rbac_ddl(): void
    {
        $this->pdo()->exec(
            "INSERT INTO quyen (ma_quyen, ky_hieu_quyen, ten_quyen, module)
             VALUES (0, 'ZERO_PERMISSION', 'Zero', 'LEGACY')"
        );

        $this->assertRbacMigrationFails('RBAC_PERMISSION_ID_INVALID');
    }

    public function test_over_range_permission_id_fails_before_any_rbac_ddl_with_valid_foreign_keys(): void
    {
        $this->pdo()->exec('ALTER TABLE vai_tro_quyen DROP FOREIGN KEY fk_vai_tro_quyen_quyen');
        $this->pdo()->exec('ALTER TABLE quyen MODIFY ma_quyen BIGINT UNSIGNED NOT NULL');
        $this->pdo()->exec('ALTER TABLE vai_tro_quyen MODIFY ma_quyen BIGINT UNSIGNED NOT NULL');
        $this->pdo()->exec(
            'ALTER TABLE vai_tro_quyen ADD CONSTRAINT fk_vai_tro_quyen_quyen
             FOREIGN KEY (ma_quyen) REFERENCES quyen(ma_quyen) ON DELETE RESTRICT ON UPDATE RESTRICT'
        );
        $role = $this->createRole('Over-range role');
        $this->pdo()->exec(
            "INSERT INTO quyen (ma_quyen, ky_hieu_quyen, ten_quyen, module)
             VALUES (2147483648, 'OVER_RANGE', 'Over range', 'LEGACY')"
        );
        $this->pdo()->prepare(
            'INSERT INTO vai_tro_quyen (ma_vt, ma_quyen) VALUES (?, 2147483648)'
        )->execute([$role]);

        $this->assertRbacMigrationFails('RBAC_PERMISSION_ID_RANGE_INVALID');
    }

    public function test_permission_key_type_drift_fails_before_any_rbac_ddl_even_with_a_valid_foreign_key(): void
    {
        $this->pdo()->exec('ALTER TABLE vai_tro_quyen DROP FOREIGN KEY fk_vai_tro_quyen_quyen');
        $this->pdo()->exec('ALTER TABLE quyen MODIFY ma_quyen BIGINT UNSIGNED NOT NULL');
        $this->pdo()->exec('ALTER TABLE vai_tro_quyen MODIFY ma_quyen BIGINT UNSIGNED NOT NULL');
        $this->pdo()->exec(
            'ALTER TABLE vai_tro_quyen ADD CONSTRAINT fk_vai_tro_quyen_quyen
             FOREIGN KEY (ma_quyen) REFERENCES quyen(ma_quyen) ON DELETE RESTRICT ON UPDATE RESTRICT'
        );
        $role = $this->createRole('Type drift role');
        $this->pdo()->exec(
            "INSERT INTO quyen (ma_quyen, ky_hieu_quyen, ten_quyen, module)
             VALUES (17, 'TYPE_DRIFT', 'Type drift', 'LEGACY')"
        );
        $this->pdo()->prepare(
            'INSERT INTO vai_tro_quyen (ma_vt, ma_quyen) VALUES (?, 17)'
        )->execute([$role]);

        $this->assertRbacMigrationFails('RBAC_PERMISSION_ID_TYPE_INVALID');
    }

    public function test_blank_permission_symbol_fails_before_any_rbac_ddl(): void
    {
        $this->pdo()->exec('ALTER TABLE quyen MODIFY ky_hieu_quyen NVARCHAR(100) NULL');
        $this->pdo()->exec(
            "INSERT INTO quyen (ma_quyen, ky_hieu_quyen, ten_quyen, module)
             VALUES (1, '   ', 'Blank', 'LEGACY')"
        );

        $this->assertRbacMigrationFails('RBAC_PERMISSION_SYMBOL_INVALID');
    }

    public function test_normalized_permission_symbol_collision_fails_before_any_rbac_ddl(): void
    {
        $this->pdo()->exec(
            "INSERT INTO quyen (ma_quyen, ky_hieu_quyen, ten_quyen, module) VALUES
             (1, 'legacy_read', 'One', 'LEGACY'),
             (2, ' LEGACY_READ ', 'Two', 'LEGACY')"
        );

        $this->assertRbacMigrationFails('RBAC_PERMISSION_SYMBOL_DUPLICATE');
    }

    public function test_orphan_role_permission_fails_before_any_rbac_ddl(): void
    {
        $this->pdo()->exec('SET FOREIGN_KEY_CHECKS = 0');
        try {
            $this->pdo()->exec(
                "INSERT INTO vai_tro_quyen (ma_vt, ma_quyen) VALUES (999, 999)"
            );
        } finally {
            $this->pdo()->exec('SET FOREIGN_KEY_CHECKS = 1');
        }

        $this->assertRbacMigrationFails('RBAC_ROLE_PERMISSION_ORPHAN');
    }

    public function test_wrong_permission_foreign_key_fails_before_any_rbac_ddl(): void
    {
        $this->pdo()->exec('ALTER TABLE vai_tro_quyen DROP FOREIGN KEY fk_vai_tro_quyen_quyen');
        $this->pdo()->exec(
            'ALTER TABLE vai_tro_quyen ADD CONSTRAINT fk_wrong_permission FOREIGN KEY (ma_quyen)
             REFERENCES quyen(ma_quyen) ON DELETE CASCADE ON UPDATE RESTRICT'
        );

        $this->assertRbacMigrationFails('RBAC_PERMISSION_FK_INVALID');
    }

    public function test_invalid_role_foreign_key_shape_or_name_fails_before_any_rbac_ddl(): void
    {
        $this->pdo()->exec('ALTER TABLE vai_tro_quyen DROP FOREIGN KEY fk_vai_tro_quyen_vai_tro');
        $this->pdo()->exec(
            'ALTER TABLE vai_tro_quyen ADD CONSTRAINT fk_wrong_role FOREIGN KEY (ma_vt)
             REFERENCES vai_tro(ma_vt) ON DELETE RESTRICT ON UPDATE RESTRICT'
        );

        $this->assertRbacMigrationFails('RBAC_ROLE_FK_INVALID');
    }

    public function test_existing_permission_foreign_key_actions_are_recreated_unchanged(): void
    {
        $this->pdo()->exec('ALTER TABLE vai_tro_quyen DROP FOREIGN KEY fk_vai_tro_quyen_quyen');
        $this->pdo()->exec(
            'ALTER TABLE vai_tro_quyen ADD CONSTRAINT fk_vai_tro_quyen_quyen FOREIGN KEY (ma_quyen)
             REFERENCES quyen(ma_quyen) ON DELETE CASCADE ON UPDATE CASCADE'
        );

        $this->runRbacMigration();

        $rules = $this->pdo()->query(
            "SELECT UPDATE_RULE, DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = DATABASE()
               AND TABLE_NAME = 'vai_tro_quyen'
               AND CONSTRAINT_NAME = 'fk_vai_tro_quyen_quyen'"
        )->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(['UPDATE_RULE' => 'CASCADE', 'DELETE_RULE' => 'CASCADE'], $rules);
    }

    public function test_default_role_mapping_fails_before_any_rbac_ddl(): void
    {
        $role = (int) $this->pdo()->query(
            "SELECT ma_vt FROM vai_tro WHERE BINARY ky_hieu = BINARY 'NHAN_VIEN_MAC_DINH'"
        )->fetchColumn();
        $this->pdo()->exec(
            "INSERT INTO quyen (ma_quyen, ky_hieu_quyen, ten_quyen, module)
             VALUES (1, 'LEGACY_READ', 'Legacy read', 'LEGACY')"
        );
        $this->pdo()->prepare(
            'INSERT INTO vai_tro_quyen (ma_vt, ma_quyen) VALUES (?, 1)'
        )->execute([$role]);

        $this->assertRbacMigrationFails('RBAC_DEFAULT_ROLE_INVALID');
    }

    public function test_missing_baseline_role_fails_before_any_rbac_ddl(): void
    {
        $this->pdo()->exec(
            "DELETE FROM vai_tro WHERE BINARY ky_hieu = BINARY 'NHAN_VIEN_MAC_DINH'"
        );

        $this->assertRbacMigrationFails('RBAC_DEFAULT_ROLE_INVALID');
    }

    public function test_ambiguous_duplicate_baseline_role_fails_before_any_rbac_ddl(): void
    {
        $this->pdo()->exec('ALTER TABLE vai_tro DROP INDEX uq_vai_tro_ky_hieu');
        $this->pdo()->exec(
            "INSERT INTO vai_tro (ten_vt, mo_ta, ky_hieu)
             VALUES ('Duplicate default', 'Controlled drift', 'NHAN_VIEN_MAC_DINH')"
        );

        $this->assertRbacMigrationFails('RBAC_DEFAULT_ROLE_INVALID');
    }

    public function test_valid_legacy_permissions_and_mapping_survive_normalization_and_rbac_ddl(): void
    {
        $this->pdo()->exec("INSERT INTO vai_tro (ten_vt, mo_ta) VALUES ('Legacy role', 'Keep')");
        $role = (int) $this->pdo()->lastInsertId();
        $this->pdo()->exec(
            "INSERT INTO quyen (ma_quyen, ky_hieu_quyen, ten_quyen, module)
             VALUES (17, ' legacy_read ', 'Tên cũ', 'MODULE_CU')"
        );
        $this->pdo()->prepare(
            'INSERT INTO vai_tro_quyen (ma_vt, ma_quyen) VALUES (?, 17)'
        )->execute([$role]);

        $this->runRbacMigration();

        $permission = $this->pdo()->query(
            'SELECT ma_quyen, ky_hieu_quyen, ten_quyen, module FROM quyen WHERE ma_quyen = 17'
        )->fetch(PDO::FETCH_ASSOC);
        $this->assertSame([
            'ma_quyen' => 17,
            'ky_hieu_quyen' => 'LEGACY_READ',
            'ten_quyen' => 'Tên cũ',
            'module' => 'MODULE_CU',
        ], $permission);
        $this->assertSame(1, (int) $this->pdo()->query(
            "SELECT COUNT(*) FROM vai_tro_quyen WHERE ma_vt = {$role} AND ma_quyen = 17"
        )->fetchColumn());
        $this->assertGreaterThan(22, (int) $this->pdo()->query(
            "SELECT AUTO_INCREMENT FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'quyen'"
        )->fetchColumn());
        $this->assertSame(1, (int) $this->pdo()->query(
            "SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'quyen'
               AND INDEX_NAME = 'uq_quyen_ky_hieu_quyen' AND NON_UNIQUE = 0"
        )->fetchColumn());
    }

    public function test_existing_employee_permission_symbol_is_not_duplicated_or_overwritten(): void
    {
        $this->pdo()->exec(
            "INSERT INTO quyen (ma_quyen, ky_hieu_quyen, ten_quyen, module)
             VALUES (11, 'NHAN_VIEN_XEM', 'Legacy label', 'LEGACY_MODULE')"
        );

        $this->runRbacMigration();

        $this->assertSame(1, (int) $this->pdo()->query(
            "SELECT COUNT(*) FROM quyen WHERE ky_hieu_quyen = 'NHAN_VIEN_XEM'"
        )->fetchColumn());
        $this->assertSame([
            'ten_quyen' => 'Legacy label',
            'module' => 'LEGACY_MODULE',
        ], $this->pdo()->query(
            "SELECT ten_quyen, module FROM quyen WHERE ky_hieu_quyen = 'NHAN_VIEN_XEM'"
        )->fetch(PDO::FETCH_ASSOC));
    }

    public function test_five_employee_permissions_are_seeded_by_symbol_and_lookup_is_distinct_and_ordered(): void
    {
        $this->runRbacMigration();
        $ids = $this->seedEmployeeReferences();
        $employee = $this->insertEmployee($ids['baselineRole'], 'NV001');
        $role = $this->createRole('RBAC role');
        $permissionIds = $this->permissionIds();
        foreach ($permissionIds as $permissionId) {
            $this->callProcedure('sp_vai_tro_quyen_them(?, ?)', [$role, $permissionId]);
        }
        $this->pdo()->prepare('UPDATE nhan_vien SET ma_vt = ? WHERE ma_nv = ?')->execute([$role, $employee]);

        $rows = $this->callProcedure('sp_quyen_lay_theo_ma_nhan_vien(?)', [$employee]);
        $this->assertSame([
            'NHAN_VIEN_DAT_LAI_MAT_KHAU',
            'NHAN_VIEN_SUA',
            'NHAN_VIEN_TAO',
            'NHAN_VIEN_XEM',
            'NHAN_VIEN_XOA',
        ], array_column($rows, 'ky_hieu_quyen'));
        $this->assertSame([], $this->callProcedure(
            'sp_quyen_lay_theo_ma_nhan_vien(?)', [$this->insertEmployee($ids['baselineRole'], 'NV002')]
        ));
    }

    public function test_permission_catalog_insert_normalizes_symbols_and_list_has_explicit_deterministic_columns(): void
    {
        $this->runRbacMigration();
        $this->callProcedure(
            'sp_quyen_them(?, ?, ?)',
            [' custom_permission ', 'Custom permission', 'nhan_vien']
        );
        $row = $this->pdo()->query(
            "SELECT ky_hieu_quyen, ten_quyen, module FROM quyen WHERE ky_hieu_quyen = 'CUSTOM_PERMISSION'"
        )->fetch(PDO::FETCH_ASSOC);
        $this->assertSame([
            'ky_hieu_quyen' => 'CUSTOM_PERMISSION',
            'ten_quyen' => 'Custom permission',
            'module' => 'NHAN_VIEN',
        ], $row);
        $rows = $this->callProcedure('sp_quyen_danh_sach()', []);
        $this->assertSame(['ma_quyen', 'ky_hieu_quyen', 'ten_quyen', 'module'], array_keys($rows[0]));
        $this->assertSame(
            array_values(array_unique(array_column($rows, 'ky_hieu_quyen'))),
            array_column($rows, 'ky_hieu_quyen')
        );
        $this->assertProcedureFails(
            'sp_quyen_them(?, ?, ?)', ['not safe', 'Bad', 'NHAN_VIEN'], 'RBAC_PERMISSION_INVALID'
        );
    }

    public function test_baseline_role_is_zero_permission_and_mapping_mutations_reject_it(): void
    {
        $this->runRbacMigration();
        $ids = $this->seedEmployeeReferences();
        $permissionId = $this->permissionIds()['NHAN_VIEN_XEM'];
        $baselineRole = $ids['baselineRole'];

        $this->assertProcedureFails(
            'sp_vai_tro_quyen_them(?, ?)', [$baselineRole, $permissionId], 'VT_DEFAULT_ROLE_FORBIDDEN'
        );
        $this->assertProcedureFails('sp_vai_tro_xoa(?)', [$baselineRole], 'VT_DEFAULT_ROLE_FORBIDDEN');
        $this->assertSame(0, (int) $this->pdo()->query(
            "SELECT COUNT(*) FROM vai_tro_quyen WHERE ma_vt = {$baselineRole}"
        )->fetchColumn());
        $this->assertSame(1, (int) $this->pdo()->query(
            "SELECT COUNT(*) FROM vai_tro WHERE ma_vt = {$baselineRole}"
        )->fetchColumn());
    }

    public function test_internal_assignment_changes_only_role_and_outer_rollback_restores_baseline(): void
    {
        $this->runRbacMigration();
        $ids = $this->seedEmployeeReferences();
        $employee = $this->insertEmployee($ids['baselineRole'], 'NV001');
        $role = $this->createRole('Bootstrap role');
        $before = $this->employeeRow($employee);

        $this->pdo()->beginTransaction();
        $this->callProcedure('sp_nhan_vien_gan_vai_tro_noi_bo(?, ?)', [$employee, $role]);
        $afterAssignment = $this->employeeRow($employee);
        $this->assertSame($before['ho_ten'], $afterAssignment['ho_ten']);
        $this->assertSame($before['email'], $afterAssignment['email']);
        $this->assertSame((string) $role, (string) $afterAssignment['ma_vt']);
        $this->pdo()->rollBack();

        $this->assertSame($before, $this->employeeRow($employee));
        $this->assertProcedureFails(
            'sp_nhan_vien_gan_vai_tro_noi_bo(?, ?)', [$employee, $ids['baselineRole']], 'VT_TARGET_ROLE_INVALID'
        );
    }

    public function test_role_in_use_returns_exact_error_and_unused_role_deletion_is_rollback_safe(): void
    {
        $this->runRbacMigration();
        $ids = $this->seedEmployeeReferences();
        $inUseRole = $this->createRole('In use role');
        $employee = $this->insertEmployee($inUseRole, 'NV001');
        $this->assertProcedureFails('sp_vai_tro_xoa(?)', [$inUseRole], 'VT_DANG_DUOC_SU_DUNG');
        $this->assertSame(1, (int) $this->pdo()->query(
            "SELECT COUNT(*) FROM nhan_vien WHERE ma_nv = '{$employee}'"
        )->fetchColumn());
        $this->assertSame(1, (int) $this->pdo()->query(
            "SELECT COUNT(*) FROM vai_tro WHERE ma_vt = {$inUseRole}"
        )->fetchColumn());

        $unusedRole = $this->createRole('Unused role');
        $this->callProcedure(
            'sp_vai_tro_quyen_them(?, ?)', [$unusedRole, $this->permissionIds()['NHAN_VIEN_XEM']]
        );
        $this->pdo()->beginTransaction();
        $this->callProcedure('sp_vai_tro_xoa(?)', [$unusedRole]);
        $this->assertSame(0, (int) $this->pdo()->query(
            "SELECT COUNT(*) FROM vai_tro WHERE ma_vt = {$unusedRole}"
        )->fetchColumn());
        $this->pdo()->rollBack();
        $this->assertSame(1, (int) $this->pdo()->query(
            "SELECT COUNT(*) FROM vai_tro WHERE ma_vt = {$unusedRole}"
        )->fetchColumn());
        $this->callProcedure('sp_vai_tro_xoa(?)', [$unusedRole]);
        $this->assertSame(0, (int) $this->pdo()->query(
            "SELECT COUNT(*) FROM vai_tro WHERE ma_vt = {$unusedRole}"
        )->fetchColumn());
    }

    public function test_repository_normalizes_permission_results_maps_errors_and_keeps_internal_assignment_off_web_boundaries(): void
    {
        $this->runRbacMigration();
        $ids = $this->seedEmployeeReferences();
        $employee = $this->insertEmployee($ids['baselineRole'], 'NV001');
        $role = $this->createRole('Repository role');
        foreach (array_slice($this->permissionIds(), 0, 2) as $permissionId) {
            $this->callProcedure('sp_vai_tro_quyen_them(?, ?)', [$role, $permissionId]);
        }
        $this->pdo()->prepare('UPDATE nhan_vien SET ma_vt = ? WHERE ma_nv = ?')->execute([$role, $employee]);

        $repository = $this->app->make(NhanVienRepositoryContract::class);
        $this->assertSame(['NHAN_VIEN_DAT_LAI_MAT_KHAU', 'NHAN_VIEN_SUA'], $repository->permissionSymbols($employee));
        $this->assertSame([], $repository->permissionSymbols($this->insertEmployee($ids['baselineRole'], 'NV002')));

        try {
            $repository->assignRoleForBootstrap('NV999', $role);
            $this->fail('Missing employee assignment should fail safely.');
        } catch (NhanVienDomainException $exception) {
            $this->assertSame('NV_NOT_FOUND', $exception->domainCode);
            $this->assertSame('Không tìm thấy nhân viên.', $exception->getMessage());
            $this->assertStringNotContainsString('SQLSTATE', $exception->getMessage());
        }

        foreach ([
            'app/Http',
            'app/Services',
            'app/Contracts/NhanVienServiceContract.php',
            'routes',
        ] as $path) {
            $files = is_dir(base_path($path))
                ? array_values(array_filter(
                    iterator_to_array(new \RecursiveIteratorIterator(
                        new \RecursiveDirectoryIterator(base_path($path), \FilesystemIterator::SKIP_DOTS)
                    )),
                    static fn (\SplFileInfo $file): bool => $file->isFile() && $file->getExtension() === 'php'
                ))
                : [base_path($path)];
            foreach ($files as $file) {
                $filePath = $file instanceof \SplFileInfo ? $file->getPathname() : $file;
                $source = file_get_contents($filePath);
                $this->assertIsString($source);
                $this->assertStringNotContainsString('assignRoleForBootstrap', $source, $filePath);
                $this->assertStringNotContainsString('sp_nhan_vien_gan_vai_tro_noi_bo', $source, $filePath);
            }
        }
    }

    public function test_repository_rejects_empty_or_unsafe_permission_result_symbols(): void
    {
        $this->runRbacMigration();
        $ids = $this->seedEmployeeReferences();
        $employee = $this->insertEmployee($ids['baselineRole'], 'NV001');
        $role = $this->createRole('Malformed result role');
        $this->pdo()->exec('ALTER TABLE quyen MODIFY ky_hieu_quyen NVARCHAR(100) NULL');
        $this->pdo()->exec(
            "INSERT INTO quyen (ky_hieu_quyen, ten_quyen, module) VALUES ('bad symbol', 'Bad', 'NHAN_VIEN')"
        );
        $permissionId = (int) $this->pdo()->lastInsertId();
        $this->pdo()->prepare(
            'INSERT INTO vai_tro_quyen (ma_vt, ma_quyen) VALUES (?, ?)'
        )->execute([$role, $permissionId]);
        $this->pdo()->prepare('UPDATE nhan_vien SET ma_vt = ? WHERE ma_nv = ?')->execute([$role, $employee]);

        try {
            $this->app->make(NhanVienRepositoryContract::class)->permissionSymbols($employee);
            $this->fail('Unsafe permission result should be rejected.');
        } catch (NhanVienDomainException $exception) {
            $this->assertSame('NV_PERMISSION_RESULT_INVALID', $exception->domainCode);
            $this->assertSame('Dữ liệu quyền nhân viên không hợp lệ.', $exception->getMessage());
            $this->assertStringNotContainsString('bad symbol', $exception->getMessage());
        }
    }

    private function runRbacMigration(): void
    {
        $path = base_path('database/sql/employee/2026_08_12_006_rbac.sql');
        $this->assertFileExists($path);
        $this->runSql($path);
    }

    private function assertRbacMigrationFails(string $errorCode): void
    {
        $before = $this->rbacSnapshot();
        try {
            $this->runRbacMigration();
            $this->fail("RBAC migration should fail with {$errorCode}.");
        } catch (PDOException $exception) {
            $this->assertStringContainsString($errorCode, $exception->getMessage());
        }
        $this->assertSame($before, $this->rbacSnapshot());
    }

    private function rbacSnapshot(): array
    {
        return [
            'columns' => $this->pdo()->query(
                "SELECT TABLE_NAME, COLUMN_NAME, ORDINAL_POSITION, COLUMN_TYPE, IS_NULLABLE, EXTRA
                 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME IN ('quyen', 'vai_tro', 'vai_tro_quyen')
                 ORDER BY TABLE_NAME, ORDINAL_POSITION"
            )->fetchAll(PDO::FETCH_ASSOC),
            'indexes' => $this->pdo()->query(
                "SELECT TABLE_NAME, INDEX_NAME, COLUMN_NAME, NON_UNIQUE
                 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME IN ('quyen', 'vai_tro', 'vai_tro_quyen')
                 ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX"
            )->fetchAll(PDO::FETCH_ASSOC),
            'fks' => $this->pdo()->query(
                "SELECT k.TABLE_NAME, k.CONSTRAINT_NAME, k.COLUMN_NAME, k.REFERENCED_TABLE_NAME,
                        k.REFERENCED_COLUMN_NAME, r.UPDATE_RULE, r.DELETE_RULE
                 FROM information_schema.KEY_COLUMN_USAGE k
                 JOIN information_schema.REFERENTIAL_CONSTRAINTS r
                   ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA
                  AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME
                  AND r.TABLE_NAME = k.TABLE_NAME
                 WHERE k.CONSTRAINT_SCHEMA = DATABASE() AND k.TABLE_NAME = 'vai_tro_quyen'
                 ORDER BY CONSTRAINT_NAME"
            )->fetchAll(PDO::FETCH_ASSOC),
            'permissions' => $this->pdo()->query('SELECT * FROM quyen ORDER BY ma_quyen')->fetchAll(PDO::FETCH_ASSOC),
            'roles' => $this->pdo()->query('SELECT * FROM vai_tro ORDER BY ma_vt')->fetchAll(PDO::FETCH_ASSOC),
            'mappings' => $this->pdo()->query('SELECT * FROM vai_tro_quyen ORDER BY ma_vt, ma_quyen')->fetchAll(PDO::FETCH_ASSOC),
            'routines' => $this->pdo()->query(
                "SELECT ROUTINE_NAME, ROUTINE_TYPE, DTD_IDENTIFIER, DATA_TYPE, ROUTINE_DEFINITION
                 FROM information_schema.ROUTINES
                 WHERE ROUTINE_SCHEMA = DATABASE() AND ROUTINE_TYPE = 'PROCEDURE'
                 ORDER BY ROUTINE_NAME"
            )->fetchAll(PDO::FETCH_ASSOC),
            'routine_parameters' => $this->pdo()->query(
                "SELECT SPECIFIC_NAME, ORDINAL_POSITION, PARAMETER_MODE, PARAMETER_NAME,
                        DTD_IDENTIFIER, DATA_TYPE
                 FROM information_schema.PARAMETERS
                 WHERE SPECIFIC_SCHEMA = DATABASE() AND ROUTINE_TYPE = 'PROCEDURE'
                 ORDER BY SPECIFIC_NAME, ORDINAL_POSITION"
            )->fetchAll(PDO::FETCH_ASSOC),
        ];
    }

    /** @return array<string, mixed> */
    private function seedEmployeeReferences(): array
    {
        $this->pdo()->exec("INSERT INTO phong_ban (ten_pb) VALUES ('RBAC department')");
        $department = (int) $this->pdo()->lastInsertId();
        $this->pdo()->exec("INSERT INTO chuc_vu (ten_cv, he_so_phu_cap) VALUES ('RBAC position', 0.10)");
        $position = (int) $this->pdo()->lastInsertId();
        $status = (int) $this->pdo()->query(
            "SELECT ma_tt FROM trang_thai_lam_viec WHERE BINARY ky_hieu = BINARY 'DANG_LAM'"
        )->fetchColumn();
        $baselineRole = (int) $this->pdo()->query(
            "SELECT ma_vt FROM vai_tro WHERE BINARY ky_hieu = BINARY 'NHAN_VIEN_MAC_DINH'"
        )->fetchColumn();

        return compact('department', 'position', 'status', 'baselineRole');
    }

    private function insertEmployee(int $role, string $maNv): string
    {
        $ids = $this->seedEmployeeReferencesIfMissing();
        $statement = $this->pdo()->prepare(
            'INSERT INTO nhan_vien (
                ma_nv, ho_ten, ngay_sinh, gioi_tinh, sdt, email, ngay_vao_lam,
                ma_pb, ma_cv, dan_toc, cccd, noi_cap_cccd, hoc_van, ma_tt, mat_khau, ma_vt
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $number = (int) substr($maNv, 2);
        $statement->execute([
            $maNv,
            'RBAC '.$maNv,
            '1990-01-01',
            1,
            '090000'.str_pad((string) $number, 4, '0', STR_PAD_LEFT),
            strtolower($maNv).'@example.test',
            '2020-01-01',
            $ids['department'],
            $ids['position'],
            'Kinh',
            str_pad((string) $number, 12, '1', STR_PAD_LEFT),
            'TP HCM',
            'Đại học',
            $ids['status'],
            str_repeat('a', 64),
            $role,
        ]);

        return $maNv;
    }

    /** @return array<string, int> */
    private function seedEmployeeReferencesIfMissing(): array
    {
        $department = (int) $this->pdo()->query('SELECT ma_pb FROM phong_ban ORDER BY ma_pb LIMIT 1')->fetchColumn();
        $position = (int) $this->pdo()->query('SELECT ma_cv FROM chuc_vu ORDER BY ma_cv LIMIT 1')->fetchColumn();
        $status = (int) $this->pdo()->query(
            "SELECT ma_tt FROM trang_thai_lam_viec WHERE BINARY ky_hieu = BINARY 'DANG_LAM'"
        )->fetchColumn();
        $baselineRole = (int) $this->pdo()->query(
            "SELECT ma_vt FROM vai_tro WHERE BINARY ky_hieu = BINARY 'NHAN_VIEN_MAC_DINH'"
        )->fetchColumn();

        if ($department === 0) {
            $this->pdo()->exec("INSERT INTO phong_ban (ten_pb) VALUES ('RBAC department')");
            $department = (int) $this->pdo()->lastInsertId();
        }
        if ($position === 0) {
            $this->pdo()->exec("INSERT INTO chuc_vu (ten_cv, he_so_phu_cap) VALUES ('RBAC position', 0.10)");
            $position = (int) $this->pdo()->lastInsertId();
        }

        return compact('department', 'position', 'status', 'baselineRole');
    }

    private function createRole(string $name): int
    {
        $statement = $this->pdo()->prepare('INSERT INTO vai_tro (ten_vt, mo_ta) VALUES (?, NULL)');
        $statement->execute([$name]);

        return (int) $this->pdo()->lastInsertId();
    }

    /** @return array<string, int> */
    private function permissionIds(): array
    {
        return $this->pdo()->query(
            'SELECT ky_hieu_quyen, ma_quyen FROM quyen ORDER BY ky_hieu_quyen'
        )->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    /** @return array<string, mixed> */
    private function employeeRow(string $maNv): array
    {
        $statement = $this->pdo()->prepare(
            'SELECT ho_ten, email, ma_pb, ma_cv, ma_tt, mat_khau, ma_vt FROM nhan_vien WHERE ma_nv = ?'
        );
        $statement->execute([$maNv]);

        return $statement->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array<string, mixed>> */
    private function callProcedure(string $call, array $bindings): array
    {
        $statement = $this->pdo()->prepare('CALL '.$call);
        $statement->execute($bindings);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        $statement->closeCursor();

        return $rows;
    }

    private function assertProcedureFails(string $call, array $bindings, string $errorCode): void
    {
        try {
            $this->callProcedure($call, $bindings);
            $this->fail("Procedure should fail with {$errorCode}.");
        } catch (PDOException $exception) {
            $this->assertStringContainsString($errorCode, $exception->getMessage());
        }
    }
}
