<?php

namespace Tests\Integration\MariaDb;

use App\Contracts\PhongBanRepositoryContract;
use Illuminate\Support\Facades\DB;
use PDO;
use PDOException;
use PDOStatement;

class PhongBanProcedureTest extends MariaDbTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->runSql(base_path('tests/Fixtures/MariaDb/employee_legacy_schema.sql'));
        $this->pdo()->exec('ALTER TABLE quyen MODIFY ma_quyen INT NOT NULL AUTO_INCREMENT');
        $this->runSql(base_path('database/sql/department/2026_08_22_001_department_contract.sql'));
        $this->pdo()->exec("INSERT INTO chuc_vu (ten_cv, he_so_phu_cap) VALUES ('Chuyên viên', 0.10)");
        $this->pdo()->exec("INSERT INTO trang_thai_lam_viec (ten_tt) VALUES ('Đang làm')");
        $this->pdo()->exec("INSERT INTO vai_tro (ten_vt) VALUES ('Nhân viên')");
    }

    public function test_list_and_detail_return_the_same_explicit_shape_and_counts(): void
    {
        $this->pdo()->exec("INSERT INTO phong_ban (ten_pb) VALUES ('Kỹ thuật'), ('Nhân sự')");

        $listStatement = $this->call('sp_phong_ban_danh_sach()', []);
        $list = $listStatement->fetchAll(PDO::FETCH_ASSOC);
        $listStatement->closeCursor();
        $this->assertSame(['ma_pb', 'ten_pb', 'so_nhan_vien'], array_keys($list[0]));
        $this->assertSame(['Kỹ thuật', 'Nhân sự'], array_column($list, 'ten_pb'));
        $this->assertSame(0, (int) $list[0]['so_nhan_vien']);

        $detailStatement = $this->call('sp_phong_ban_chi_tiet(?)', [1]);
        $detail = $detailStatement->fetch(PDO::FETCH_ASSOC);
        $detailStatement->closeCursor();
        $this->assertSame(['ma_pb', 'ten_pb', 'so_nhan_vien'], array_keys($detail));
        $this->assertSame('Kỹ thuật', $detail['ten_pb']);
    }

    public function test_create_and_update_trim_names_and_preserve_explicit_contract(): void
    {
        $this->call('sp_phong_ban_them(?)', ['  Kỹ thuật  '])->closeCursor();
        $created = $this->pdo()->query('SELECT ma_pb, ten_pb FROM phong_ban')->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(['ma_pb' => 1, 'ten_pb' => 'Kỹ thuật'], $created);

        $this->call('sp_phong_ban_sua(?, ?)', [1, '  Nhân sự  '])->closeCursor();
        $this->assertSame('Nhân sự', $this->pdo()->query('SELECT ten_pb FROM phong_ban WHERE ma_pb = 1')->fetchColumn());
    }

    public function test_duplicate_blank_and_missing_department_errors_are_stable(): void
    {
        $this->pdo()->exec("INSERT INTO phong_ban (ten_pb) VALUES ('Kỹ thuật')");

        $this->expectProcedureError('PB_NAME_REQUIRED', 'sp_phong_ban_them(?)', ['   ']);
        $this->expectProcedureError('PB_NAME_DUPLICATE', 'sp_phong_ban_them(?)', ['Kỹ thuật']);
        $this->expectProcedureError('PB_NOT_FOUND', 'sp_phong_ban_chi_tiet(?)', [999]);
        $this->expectProcedureError('PB_ID_INVALID', 'sp_phong_ban_xoa(?)', [0]);
    }

    public function test_delete_is_blocked_by_employee_dependency_and_succeeds_when_free(): void
    {
        $this->pdo()->exec("INSERT INTO phong_ban (ten_pb) VALUES ('Đang dùng'), ('Trống')");
        $employee = $this->pdo()->prepare(
            'INSERT INTO nhan_vien (
                ma_nv, ho_ten, ngay_sinh, gioi_tinh, sdt, email, ngay_vao_lam,
                ma_pb, ma_cv, dan_toc, cccd, noi_cap_cccd, hoc_van, ma_tt, mat_khau, ma_vt
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $employee->execute([
            'NV001', 'Nguyễn An', '1990-01-01', 1, '0900000001', 'an@example.test', '2020-01-01',
            1, 1, 'Kinh', '123456789001', 'TP HCM', 'Đại học', 1, 'hash', 1,
        ]);

        $this->expectProcedureError('PB_IN_USE', 'sp_phong_ban_xoa(?)', [1]);
        $this->call('sp_phong_ban_xoa(?)', [2])->closeCursor();
        $this->assertSame(1, (int) $this->pdo()->query('SELECT COUNT(*) FROM phong_ban')->fetchColumn());
    }

    public function test_repository_writes_drain_result_sets_before_follow_up_queries(): void
    {
        $repository = $this->app->make(PhongBanRepositoryContract::class);

        $repository->create('Kỹ thuật');
        $this->assertSame(
            'Kỹ thuật',
            DB::connection('employee_test')->table('phong_ban')->where('ma_pb', 1)->value('ten_pb'),
        );

        $repository->update(1, 'Nhân sự');
        $this->assertSame(
            'Nhân sự',
            DB::connection('employee_test')->table('phong_ban')->where('ma_pb', 1)->value('ten_pb'),
        );

        $repository->delete(1);
        $this->assertSame(
            0,
            (int) DB::connection('employee_test')->table('phong_ban')->count(),
        );
    }

    public function test_department_contract_script_replays_without_duplicate_unique_ddl(): void
    {
        $this->runSql(base_path('database/sql/department/2026_08_22_001_department_contract.sql'));

        $this->assertSame(1, (int) $this->pdo()->query(
            "SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'phong_ban'
               AND INDEX_NAME = 'uq_phong_ban_ten_pb'
               AND COLUMN_NAME = 'ten_pb' AND NON_UNIQUE = 0"
        )->fetchColumn());
    }

    public function test_database_unique_constraint_rejects_same_name_from_an_independent_connection(): void
    {
        $configuration = config('database.connections.employee_test');
        $otherConnection = new PDO(
            sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                $configuration['host'],
                $configuration['port'],
                $configuration['database'],
            ),
            $configuration['username'],
            $configuration['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );

        try {
            $this->pdo()->beginTransaction();
            $this->pdo()->exec("INSERT INTO phong_ban (ten_pb) VALUES ('Concurrent')");
            $otherConnection->exec('SET innodb_lock_wait_timeout = 1');

            try {
                $otherConnection->exec("INSERT INTO phong_ban (ten_pb) VALUES ('Concurrent')");
                $this->fail('The concurrent insert should wait on the first transaction.');
            } catch (PDOException $exception) {
                $this->assertSame('HY000', $exception->errorInfo[0] ?? null);
            }

            $this->pdo()->commit();

            try {
                $otherConnection->exec("INSERT INTO phong_ban (ten_pb) VALUES ('Concurrent')");
                $this->fail('The second connection must be rejected by the unique constraint.');
            } catch (PDOException $exception) {
                $this->assertSame('23000', $exception->errorInfo[0] ?? null);
                $this->assertStringContainsString('uq_phong_ban_ten_pb', $exception->getMessage());
            }
        } finally {
            if ($this->pdo()->inTransaction()) {
                $this->pdo()->rollBack();
            }
            $otherConnection = null;
        }
    }

    public function test_permission_catalog_and_name_unique_constraint_are_provisioned_without_role_mapping(): void
    {
        $symbols = $this->pdo()->query(
            "SELECT ky_hieu_quyen FROM quyen WHERE module = 'PHONG_BAN' ORDER BY ky_hieu_quyen"
        )->fetchAll(PDO::FETCH_COLUMN);
        $this->assertSame([
            'PHONG_BAN_SUA', 'PHONG_BAN_TAO', 'PHONG_BAN_XEM', 'PHONG_BAN_XOA',
        ], $symbols);
        $this->assertSame(0, (int) $this->pdo()->query('SELECT COUNT(*) FROM vai_tro_quyen')->fetchColumn());
        $this->assertSame(1, (int) $this->pdo()->query(
            "SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'phong_ban'
               AND COLUMN_NAME = 'ten_pb' AND NON_UNIQUE = 0"
        )->fetchColumn());
    }

    private function call(string $routine, array $bindings): PDOStatement
    {
        $statement = $this->pdo()->prepare("CALL {$routine}");
        $statement->execute($bindings);

        return $statement;
    }

    private function expectProcedureError(string $code, string $routine, array $bindings): void
    {
        try {
            $statement = $this->call($routine, $bindings);
            $statement->closeCursor();
            $this->fail("Routine {$routine} should reject the fixture.");
        } catch (PDOException $exception) {
            $this->assertStringContainsString($code, $exception->getMessage());
        }
    }
}
