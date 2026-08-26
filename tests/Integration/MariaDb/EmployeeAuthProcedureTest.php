<?php

namespace Tests\Integration\MariaDb;

use App\Enums\NhanVienRemovalAction;
use App\Repositories\NhanVienRepository;
use App\Support\NhanVienProcedureExceptionMapper;
use Illuminate\Database\DatabaseManager;
use PDO;
use PDOException;

class EmployeeAuthProcedureTest extends MariaDbTestCase
{
    private int $department;

    private int $position;

    private int $workingStatus;

    private int $terminatedStatus;

    private int $defaultRole;

    protected function setUp(): void
    {
        parent::setUp();
        $this->runSql(base_path('tests/Fixtures/MariaDb/employee_legacy_schema.sql'));
        $this->runSql(base_path('database/sql/employee/2026_08_12_001_schema.sql'));
        $this->runSql(base_path('database/sql/employee/2026_08_12_002_read_routines.sql'));
        $this->runSql(base_path('database/sql/employee/2026_08_12_003_create_routines.sql'));
        $this->runSql(base_path('database/sql/employee/2026_08_12_004_update_routines.sql'));
        $this->runSql(base_path('database/sql/employee/2026_08_12_005_lifecycle_auth_routines.sql'));
        $this->pdo()->exec("INSERT INTO phong_ban (ten_pb) VALUES ('Auth fixture')");
        $this->department = (int) $this->pdo()->lastInsertId();
        $this->pdo()->exec("INSERT INTO chuc_vu (ten_cv, he_so_phu_cap) VALUES ('Auth position', 0.10)");
        $this->position = (int) $this->pdo()->lastInsertId();
        $this->workingStatus = $this->lookupStatus('DANG_LAM');
        $this->terminatedStatus = $this->lookupStatus('DA_NGHI');
        $this->defaultRole = (int) $this->pdo()->query(
            "SELECT ma_vt FROM vai_tro WHERE BINARY ky_hieu = BINARY 'NHAN_VIEN_MAC_DINH'"
        )->fetchColumn();
    }

    public function test_lookup_normalizes_code_and_email_and_returns_exact_six_columns(): void
    {
        $this->createEmployee();
        $this->createEmployee([
            'email' => 'terminated@example.test',
            'cccd' => '001200000002',
            'status' => $this->terminatedStatus,
            'termination_date' => '2026-08-20',
        ]);

        foreach (['NV001', ' nv001 ', ' AUTH@EXAMPLE.TEST '] as $identifier) {
            $statement = $this->pdo()->prepare('CALL sp_nhan_vien_lay_tai_khoan_dang_nhap(?)');
            $statement->execute([$identifier]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
            $statement->closeCursor();
            $this->assertIsArray($row);
            $this->assertSame(['ma_nv', 'ho_ten', 'email', 'mat_khau', 'ma_vt', 'ky_hieu'], array_keys($row));
            $this->assertArrayNotHasKey('plaintext', $row);
            $this->assertSame('NV001', $row['ma_nv']);
        }

        $statement = $this->pdo()->prepare('CALL sp_nhan_vien_lay_tai_khoan_dang_nhap(?)');
        $statement->execute(['terminated@example.test']);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        $statement->closeCursor();
        $this->assertSame('DA_NGHI', $row['ky_hieu']);
    }

    public function test_lookup_unknown_identifier_returns_no_row(): void
    {
        $statement = $this->pdo()->prepare('CALL sp_nhan_vien_lay_tai_khoan_dang_nhap(?)');
        $statement->execute(['missing@example.test']);
        $this->assertFalse($statement->fetch(PDO::FETCH_ASSOC));
        $statement->closeCursor();
    }

    public function test_repository_auth_lookup_hydrates_only_the_server_contract(): void
    {
        $this->createEmployee();
        $repository = $this->repository();

        $employee = $repository->findAccountByIdentifier(' nv001 ');

        $this->assertNotNull($employee);
        $this->assertSame('NV001', $employee->getKey());
        $this->assertSame('original-hash', $employee->getAuthPassword());
        $this->assertArrayNotHasKey('plaintext', $employee->getAttributes());
    }

    public function test_compare_and_swap_updates_baseline_and_admin_roles(): void
    {
        $baseline = $this->createEmployee();
        $admin = $this->createEmployee(['email' => 'admin@example.test', 'cccd' => '001200000002']);
        $adminRole = $this->createRole('ADMIN_FIXTURE');
        $this->pdo()->prepare('UPDATE nhan_vien SET ma_vt = ? WHERE ma_nv = ?')->execute([$adminRole, $admin]);

        $repository = $this->repository();
        $repository->rehashAuthenticatedPassword($baseline, 'original-hash', 'baseline-new-hash');
        $repository->rehashAuthenticatedPassword($admin, 'original-hash', 'admin-new-hash');

        $rows = $this->pdo()->query("SELECT ma_nv, mat_khau FROM nhan_vien ORDER BY ma_nv")->fetchAll(PDO::FETCH_KEY_PAIR);
        $this->assertSame('baseline-new-hash', $rows[$baseline]);
        $this->assertSame('admin-new-hash', $rows[$admin]);
    }

    public function test_compare_and_swap_stale_and_not_found_share_error_and_do_not_mutate(): void
    {
        $maNv = $this->createEmployee();
        $repository = $this->repository();

        foreach ([['wrong-hash', $maNv], ['original-hash', 'NV999']] as [$current, $target]) {
            try {
                $repository->rehashAuthenticatedPassword($target, $current, 'new-hash');
                $this->fail('Expected stale auth hash error.');
            } catch (\App\Exceptions\NhanVienDomainException $exception) {
                $this->assertSame('NV_AUTH_HASH_STALE', $exception->domainCode);
            }
        }
        $this->assertSame('original-hash', $this->pdo()->query("SELECT mat_khau FROM nhan_vien WHERE ma_nv = '{$maNv}'")->fetchColumn());
    }

    public function test_compare_and_swap_rollback_restores_hash(): void
    {
        $maNv = $this->createEmployee();
        $repository = $this->repository();
        $this->pdo()->beginTransaction();
        $repository->rehashAuthenticatedPassword($maNv, 'original-hash', 'temporary-hash');
        $this->pdo()->rollBack();

        $this->assertSame('original-hash', $this->pdo()->query("SELECT mat_khau FROM nhan_vien WHERE ma_nv = '{$maNv}'")->fetchColumn());
    }

    public function test_auth_procedure_has_no_transaction_control_or_plaintext_contract(): void
    {
        $definition = (string) $this->pdo()->query(
            "SELECT ROUTINE_DEFINITION FROM information_schema.ROUTINES
             WHERE ROUTINE_SCHEMA = DATABASE() AND ROUTINE_NAME = 'sp_nhan_vien_cap_nhat_hash_xac_thuc'"
        )->fetchColumn();
        $this->assertDoesNotMatchRegularExpression('/\b(?:START\s+TRANSACTION|COMMIT|ROLLBACK)\b/i', $definition);
        $this->assertStringNotContainsString('SHA2(', strtoupper($definition));
    }

    private function repository(): NhanVienRepository
    {
        /** @var DatabaseManager $database */
        $database = app('db');

        return new NhanVienRepository($database, new NhanVienProcedureExceptionMapper);
    }

    private function lookupStatus(string $symbol): int
    {
        return (int) $this->pdo()->query(
            "SELECT ma_tt FROM trang_thai_lam_viec WHERE BINARY ky_hieu = BINARY '{$symbol}'"
        )->fetchColumn();
    }

    private function createRole(string $symbol): int
    {
        $this->pdo()->prepare('INSERT INTO vai_tro (ten_vt, mo_ta, ky_hieu) VALUES (?, ?, ?)')->execute([$symbol, 'fixture', $symbol]);

        return (int) $this->pdo()->lastInsertId();
    }

    private function createEmployee(array $overrides = []): string
    {
        $data = array_replace([
            'email' => 'auth@example.test',
            'cccd' => '001200000001',
            'status' => $this->workingStatus,
            'termination_date' => null,
        ], $overrides);
        $this->pdo()->exec('SET @nv_ma = NULL');
        $statement = $this->pdo()->prepare(
            'CALL sp_nhan_vien_them(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, @nv_ma)'
        );
        $statement->execute([
            'Auth employee', '1990-01-01', 1, '0901234567', $data['email'], '2026-08-12',
            $this->department, $this->position, 'Kinh', $data['cccd'], 'Fixture', 'Đại học',
            $this->workingStatus, 'original-hash', null,
        ]);
        $statement->closeCursor();
        $maNv = (string) $this->pdo()->query('SELECT @nv_ma')->fetchColumn();
        if ($data['termination_date'] !== null) {
            $this->pdo()->prepare('UPDATE nhan_vien SET ma_tt = ?, ngay_nghi_viec = ? WHERE ma_nv = ?')
                ->execute([$data['status'], $data['termination_date'], $maNv]);
        }

        return $maNv;
    }
}
