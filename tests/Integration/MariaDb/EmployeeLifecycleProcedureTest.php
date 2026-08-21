<?php

namespace Tests\Integration\MariaDb;

use App\Enums\NhanVienRemovalAction;
use App\Repositories\NhanVienRepository;
use App\Support\NhanVienProcedureExceptionMapper;
use Carbon\CarbonImmutable;
use Illuminate\Database\DatabaseManager;
use PDO;
use PDOException;
use Tests\Support\EmployeeDependencyFixture;

class EmployeeLifecycleProcedureTest extends MariaDbTestCase
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

        $this->pdo()->exec("INSERT INTO phong_ban (ten_pb) VALUES ('Lifecycle fixture')");
        $this->department = (int) $this->pdo()->lastInsertId();
        $this->pdo()->exec("INSERT INTO chuc_vu (ten_cv, he_so_phu_cap) VALUES ('Lifecycle position', 0.10)");
        $this->position = (int) $this->pdo()->lastInsertId();
        $this->workingStatus = $this->lookupStatus('DANG_LAM');
        $this->terminatedStatus = $this->lookupStatus('DA_NGHI');
        $this->defaultRole = (int) $this->pdo()->query(
            "SELECT ma_vt FROM vai_tro WHERE BINARY ky_hieu = BINARY 'NHAN_VIEN_MAC_DINH'"
        )->fetchColumn();
    }

    public function test_no_dependency_hard_deletes_row_and_returns_avatar(): void
    {
        $maNv = $this->createEmployee(['avatar' => 'nhan-vien/avatars/nv001.png']);
        $this->address($maNv);

        $result = $this->remove($maNv, '2026-08-20');

        $this->assertSame(['action' => 'DELETED', 'avatar' => 'nhan-vien/avatars/nv001.png'], $result);
        $this->assertSame(0, (int) $this->pdo()->query("SELECT COUNT(*) FROM nhan_vien WHERE ma_nv = '{$maNv}'")->fetchColumn());
        $this->assertSame(0, (int) $this->pdo()->query("SELECT COUNT(*) FROM dia_chi_nhan_vien WHERE ma_nv = '{$maNv}'")->fetchColumn());
    }

    public function test_each_dependency_terminates_and_resolves_exact_status(): void
    {
        foreach (EmployeeDependencyFixture::dependencyNames() as $index => $dependency) {
            $maNv = $this->createEmployee([
                'email' => "{$dependency}@example.test",
                'cccd' => '0012000000'.str_pad((string) ($index + 10), 2, '0', STR_PAD_LEFT),
            ]);
            (new EmployeeDependencyFixture($this->pdo()))->add($maNv, $dependency);

            $result = $this->remove($maNv, '2026-08-21');

            $this->assertSame('TERMINATED', $result['action'], $dependency);
            $row = $this->pdo()->query("SELECT ma_tt, ngay_nghi_viec FROM nhan_vien WHERE ma_nv = '{$maNv}'")->fetch(PDO::FETCH_ASSOC);
            $this->assertSame($this->terminatedStatus, (int) $row['ma_tt'], $dependency);
            $this->assertSame('2026-08-21', $row['ngay_nghi_viec'], $dependency);
        }
    }

    public function test_termination_is_idempotent_and_preserves_first_date(): void
    {
        $maNv = $this->createEmployee(['avatar' => 'avatar.png']);
        $fixture = new EmployeeDependencyFixture($this->pdo());
        $fixture->add($maNv, 'luong');
        $this->remove($maNv, '2026-08-20');
        $fixture->clear($maNv, 'luong');

        $result = $this->remove($maNv, '2026-08-25');

        $this->assertSame('TERMINATED', $result['action']);
        $this->assertSame('avatar.png', $result['avatar']);
        $this->assertSame('2026-08-20', $this->pdo()->query("SELECT ngay_nghi_viec FROM nhan_vien WHERE ma_nv = '{$maNv}'")->fetchColumn());
        $this->assertSame(1, (int) $this->pdo()->query("SELECT COUNT(*) FROM nhan_vien WHERE ma_nv = '{$maNv}'")->fetchColumn());
    }

    public function test_committed_hard_delete_does_not_reuse_employee_code(): void
    {
        $this->assertSame('NV001', $this->createEmployee());
        $this->assertSame('DELETED', $this->remove('NV001', '2026-08-20')['action']);

        $this->assertSame('NV002', $this->createEmployee([
            'email' => 'second@example.test',
            'cccd' => '001200000002',
        ]));
    }

    public function test_not_found_date_before_hire_and_missing_status_are_domain_errors(): void
    {
        $this->assertProcedureError('NV_NOT_FOUND', fn (): array => $this->remove('NV999', '2026-08-20'));
        $maNv = $this->createEmployee();
        $this->assertProcedureError('NV_REFERENCE_INVALID', fn (): array => $this->remove($maNv, '2026-08-11'));

        $this->pdo()->exec("DELETE FROM trang_thai_lam_viec WHERE BINARY ky_hieu = BINARY 'DA_NGHI'");
        $this->assertProcedureError('NV_STATUS_MISSING', fn (): array => $this->remove($maNv, '2026-08-20'));
    }

    public function test_privileged_target_is_rejected_before_lifecycle_or_reset_mutation(): void
    {
        $maNv = $this->createEmployee();
        $this->pdo()->exec("INSERT INTO vai_tro (ten_vt, mo_ta, ky_hieu) VALUES ('Admin fixture', 'fixture', 'ADMIN_FIXTURE')");
        $adminRole = (int) $this->pdo()->lastInsertId();
        $this->pdo()->prepare('UPDATE nhan_vien SET ma_vt = ? WHERE ma_nv = ?')->execute([$adminRole, $maNv]);

        $this->assertProcedureError('NV_PRIVILEGED_TARGET', fn (): array => $this->remove($maNv, '2026-08-20'));
        $this->assertProcedureError('NV_PRIVILEGED_TARGET', function () use ($maNv): void {
            $statement = $this->pdo()->prepare('CALL sp_nhan_vien_dat_lai_mat_khau(?, ?)');
            $statement->execute([$maNv, 'new-hash']);
            $statement->closeCursor();
        });
        $this->assertSame($this->workingStatus, (int) $this->pdo()->query("SELECT ma_tt FROM nhan_vien WHERE ma_nv = '{$maNv}'")->fetchColumn());
        $this->assertSame($this->originalHash(), $this->pdo()->query("SELECT mat_khau FROM nhan_vien WHERE ma_nv = '{$maNv}'")->fetchColumn());
    }

    public function test_reset_updates_only_default_role_hash(): void
    {
        $maNv = $this->createEmployee();
        $statement = $this->pdo()->prepare('CALL sp_nhan_vien_dat_lai_mat_khau(?, ?)');
        $statement->execute([$maNv, 'new-hash-value']);
        $statement->closeCursor();

        $this->assertSame('new-hash-value', $this->pdo()->query("SELECT mat_khau FROM nhan_vien WHERE ma_nv = '{$maNv}'")->fetchColumn());
    }

    public function test_repository_lifecycle_contract_returns_enum_and_avatar_on_same_connection(): void
    {
        $maNv = $this->createEmployee(['avatar' => 'nhan-vien/avatars/repository.png']);
        (new EmployeeDependencyFixture($this->pdo()))->add($maNv, 'luong');
        /** @var DatabaseManager $database */
        $database = app('db');
        $repository = new NhanVienRepository($database, new NhanVienProcedureExceptionMapper);

        $result = $repository->removeOrTerminate($maNv, CarbonImmutable::parse('2026-08-20'));

        $this->assertSame(NhanVienRemovalAction::Terminated, $result['action']);
        $this->assertSame('nhan-vien/avatars/repository.png', $result['avatar_path']);
    }

    public function test_lifecycle_and_reset_procedures_do_not_control_transactions(): void
    {
        foreach ([
            'sp_nhan_vien_xoa_hoac_nghi_viec',
            'sp_nhan_vien_dat_lai_mat_khau',
            'sp_nhan_vien_cap_nhat_hash_xac_thuc',
            'sp_nhan_vien_lay_tai_khoan_dang_nhap',
        ] as $procedure) {
            $statement = $this->pdo()->prepare(
                'SELECT ROUTINE_DEFINITION FROM information_schema.ROUTINES
                 WHERE ROUTINE_SCHEMA = DATABASE() AND ROUTINE_NAME = ?'
            );
            $statement->execute([$procedure]);
            $definition = (string) $statement->fetchColumn();
            $this->assertDoesNotMatchRegularExpression('/\b(?:START\s+TRANSACTION|COMMIT|ROLLBACK)\b/i', $definition);
        }
    }

    private function lookupStatus(string $symbol): int
    {
        return (int) $this->pdo()->query(
            "SELECT ma_tt FROM trang_thai_lam_viec WHERE BINARY ky_hieu = BINARY '{$symbol}'"
        )->fetchColumn();
    }

    private function createEmployee(array $overrides = []): string
    {
        $data = array_replace([
            'ho_ten' => 'Lifecycle employee',
            'ngay_sinh' => '1990-01-01',
            'gioi_tinh' => 1,
            'sdt' => '0901234567',
            'email' => 'lifecycle@example.test',
            'ngay_vao_lam' => '2026-08-12',
            'ma_pb' => $this->department,
            'ma_cv' => $this->position,
            'dan_toc' => 'Kinh',
            'cccd' => '001200000001',
            'noi_cap_cccd' => 'Fixture',
            'hoc_van' => 'Đại học',
            'ma_tt' => $this->workingStatus,
            'password_hash' => $this->originalHash(),
            'avatar' => null,
        ], $overrides);
        $this->pdo()->exec('SET @nv_ma = NULL');
        $statement = $this->pdo()->prepare(
            'CALL sp_nhan_vien_them(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, @nv_ma)'
        );
        $statement->execute([
            $data['ho_ten'], $data['ngay_sinh'], $data['gioi_tinh'], $data['sdt'], $data['email'],
            $data['ngay_vao_lam'], $data['ma_pb'], $data['ma_cv'], $data['dan_toc'], $data['cccd'],
            $data['noi_cap_cccd'], $data['hoc_van'], $data['ma_tt'], $data['password_hash'], $data['avatar'],
        ]);
        $statement->closeCursor();

        return (string) $this->pdo()->query('SELECT @nv_ma')->fetchColumn();
    }

    private function address(string $maNv): void
    {
        $statement = $this->pdo()->prepare('CALL sp_dia_chi_nhan_vien_luu(?, ?, ?, ?, ?)');
        $statement->execute([$maNv, 'Số 1', 'Phường A', 'Quận A', 'Thành phố A']);
        $statement->closeCursor();
    }

    /** @return array{action: string, avatar: ?string} */
    private function remove(string $maNv, string $date): array
    {
        $this->pdo()->exec('SET @nv_hanh_dong = NULL');
        $this->pdo()->exec('SET @nv_anh_cu = NULL');
        $statement = $this->pdo()->prepare(
            'CALL sp_nhan_vien_xoa_hoac_nghi_viec(?, ?, @nv_hanh_dong, @nv_anh_cu)'
        );
        $statement->execute([$maNv, $date]);
        $statement->closeCursor();

        return [
            'action' => (string) $this->pdo()->query('SELECT @nv_hanh_dong')->fetchColumn(),
            'avatar' => $this->pdo()->query('SELECT @nv_anh_cu')->fetchColumn(),
        ];
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

    private function originalHash(): string
    {
        return 'original-hash';
    }
}
