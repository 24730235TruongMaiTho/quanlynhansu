<?php

namespace Tests\Integration\MariaDb;

use PDO;
use RuntimeException;

class EmployeeViewCompatibilityTest extends MariaDbTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->runSql(base_path('tests/Fixtures/MariaDb/employee_legacy_schema.sql'));
        $this->runSql(base_path('database/sql/employee/2026_08_12_001_schema.sql'));
    }

    public function test_safe_view_never_exposes_password_hash(): void
    {
        $columns = $this->pdo()->query(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'vw_danh_sach_nhan_vien_chi_tiet'
             ORDER BY ORDINAL_POSITION"
        )->fetchAll(PDO::FETCH_COLUMN);

        $this->assertSame([
            'ma_nv', 'ho_ten', 'ngay_sinh', 'gioi_tinh', 'gioi_tinh_hien_thi',
            'sdt', 'email', 'ngay_vao_lam', 'ma_pb', 'ten_pb', 'ma_cv', 'ten_cv',
            'he_so_phu_cap', 'dan_toc', 'cccd', 'noi_cap_cccd', 'hoc_van', 'ma_tt',
            'ky_hieu', 'ten_tt', 'ngay_nghi_viec', 'ma_vt', 'ky_hieu_vai_tro',
            'ten_vt', 'anh_dai_dien',
        ], $columns);
        $this->assertNotContains('mat_khau', $columns);
    }

    public function test_attendance_and_payroll_consumers_keep_their_safe_legacy_columns(): void
    {
        foreach (['fn_so_ngay_cong_chuan', 'fn_so_ngay_cong_thuc_te', 'fn_tinh_luong_thuc_nhan', 'fn_tinh_so_ngay_cong'] as $function) {
            $this->installCanonicalRoutine('FUNCTION', $function);
        }
        foreach (['sp_cham_cong_nhan_vien_tim_kiem', 'sp_luong_tim_kiem', 'sp_luong_xem'] as $procedure) {
            $this->installCanonicalRoutine('PROCEDURE', $procedure);
        }
        foreach (['sp_nhan_vien_danh_sach_phan_trang', 'sp_nhan_vien_chi_tiet'] as $procedure) {
            $this->pdo()->exec("DROP PROCEDURE IF EXISTS `{$procedure}`");
            $this->installCanonicalRoutine('PROCEDURE', $procedure);
        }
        $this->seedConsumerFixture();

        $attendance = $this->executeProcedure('CALL sp_cham_cong_nhan_vien_tim_kiem(?, ?)', ['NV001', '2026-08-01']);
        $payrollSearch = $this->executeProcedure('CALL sp_luong_tim_kiem(?, ?, ?, ?)', [null, '2026-08-01', null, null]);
        $payrollDetail = $this->executeProcedure('CALL sp_luong_xem(?, ?)', ['NV001', '2026-08-01']);
        $this->pdo()->exec('SET @compat_employee_total = 0');
        $employeeSearch = $this->executeProcedure(
            'CALL sp_nhan_vien_danh_sach_phan_trang(?, ?, ?, ?, ?, ?, @compat_employee_total)',
            ['', null, null, null, 1, 20],
        );
        $employeeDetail = $this->executeProcedure('CALL sp_nhan_vien_chi_tiet(?)', ['NV001']);

        $this->assertSafeColumns($attendance, ['ma_nv', 'ho_ten', 'ngay_sinh', 'gioi_tinh_hien_thi', 'sdt', 'email', 'ten_pb', 'ten_cv']);
        $this->assertSafeColumns($payrollSearch, ['ma_nv', 'ho_ten', 'ngay_sinh', 'gioi_tinh', 'sdt', 'email', 'ngay_vao_lam', 'ma_pb', 'ten_pb', 'ma_cv', 'ten_cv', 'hoc_van']);
        $this->assertSafeColumns($payrollDetail, ['ma_nv', 'ho_ten', 'ngay_sinh', 'gioi_tinh', 'sdt', 'email', 'ngay_vao_lam', 'ma_pb', 'ten_pb', 'ma_cv', 'ten_cv', 'hoc_van']);
        $this->assertSame([
            'ma_nv', 'ho_ten', 'sdt', 'email', 'ngay_vao_lam', 'anh_dai_dien', 'ma_pb',
            'ten_pb', 'ma_cv', 'ten_cv', 'ma_tt', 'ky_hieu', 'ten_tt',
        ], array_keys($employeeSearch));
        $this->assertSame($this->safeEmployeeColumns(), array_keys($employeeDetail));
        $this->assertArrayNotHasKey('mat_khau', $employeeSearch);
        $this->assertArrayNotHasKey('mat_khau', $employeeDetail);

        $definitions = $this->pdo()->query(
            "SELECT ROUTINE_DEFINITION FROM information_schema.ROUTINES
             WHERE ROUTINE_SCHEMA = DATABASE()
               AND ROUTINE_NAME IN ('sp_cham_cong_nhan_vien_tim_kiem', 'sp_luong_tim_kiem', 'sp_luong_xem')"
        )->fetchAll(PDO::FETCH_COLUMN);
        foreach ($definitions as $definition) {
            $this->assertStringNotContainsString('mat_khau', strtolower((string) $definition));
        }
    }

    private function installCanonicalRoutine(string $type, string $name): void
    {
        $dump = file_get_contents(base_path('quan_ly_nhan_su.session.sql'));
        if ($dump === false) {
            throw new RuntimeException('Unable to read canonical SQL dump.');
        }

        $pattern = '/^CREATE '.preg_quote($type, '/').' '.preg_quote($name, '/').'\b.*?^END\/\//ms';
        if (preg_match($pattern, $dump, $matches) !== 1) {
            throw new RuntimeException("Unable to extract {$type} {$name} from canonical SQL dump.");
        }

        $this->pdo()->exec(substr(rtrim($matches[0]), 0, -2));
    }

    private function seedConsumerFixture(): void
    {
        $this->pdo()->exec("INSERT INTO phong_ban (ten_pb) VALUES ('Phòng thử nghiệm')");
        $department = (int) $this->pdo()->lastInsertId();
        $this->pdo()->exec("INSERT INTO chuc_vu (ten_cv, he_so_phu_cap) VALUES ('Chuyên viên', 0.10)");
        $position = (int) $this->pdo()->lastInsertId();
        $status = (int) $this->pdo()->query("SELECT ma_tt FROM trang_thai_lam_viec WHERE ky_hieu = 'DANG_LAM'")->fetchColumn();
        $role = (int) $this->pdo()->query("SELECT ma_vt FROM vai_tro WHERE ky_hieu = 'NHAN_VIEN_MAC_DINH'")->fetchColumn();

        $statement = $this->pdo()->prepare(
            'INSERT INTO nhan_vien (
                ma_nv, ho_ten, ngay_sinh, gioi_tinh, sdt, email, ngay_vao_lam,
                ma_pb, ma_cv, dan_toc, cccd, noi_cap_cccd, hoc_van, ma_tt, mat_khau, ma_vt
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $statement->execute([
            'NV001', 'Nguyễn An', '1990-01-01', 1, '0900000000', 'an@example.test', '2020-01-01',
            $department, $position, 'Kinh', '123456789012', 'TP HCM', 'Đại học', $status,
            str_repeat('a', 64), $role,
        ]);
        $this->pdo()->exec("INSERT INTO loai_hop_dong (ten_lhd) VALUES ('Không xác định thời hạn')");
        $contractType = (int) $this->pdo()->lastInsertId();
        $this->pdo()->exec(
            "INSERT INTO hop_dong (ma_nv, ma_lhd, ngay_ky, ngay_het_han, luong_co_ban)
             VALUES ('NV001', {$contractType}, '2020-01-01', NULL, 10000000)"
        );
        $this->pdo()->exec(
            "INSERT INTO lich_su_he_so_luong (ma_nv, he_so_luong, tu_ngay, den_ngay)
             VALUES ('NV001', 1.00, '2020-01-01', '2099-12-31')"
        );
        $this->pdo()->exec(
            "INSERT INTO cham_cong (ma_nv, ngay_lam, so_gio_lam, vao_muon, ve_som)
             VALUES ('NV001', '2026-08-03', 8, 0, 0)"
        );
        $this->pdo()->exec(
            "INSERT INTO luong (ma_nv, ky_luong, thuong, phat, bao_hiem, thue)
             VALUES ('NV001', '2026-08-01', 0, 0, 0, 0)"
        );
    }

    private function executeProcedure(string $sql, array $bindings): array
    {
        $statement = $this->pdo()->prepare($sql);
        $statement->execute($bindings);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        $statement->closeCursor();

        $this->assertIsArray($row, "Expected a row from {$sql}.");

        return $row;
    }

    private function assertSafeColumns(array $row, array $expectedColumns): void
    {
        $this->assertNotEmpty($row);
        $this->assertArrayNotHasKey('mat_khau', $row);
        foreach ($expectedColumns as $column) {
            $this->assertArrayHasKey($column, $row);
        }
    }

    private function safeEmployeeColumns(): array
    {
        return [
            'ma_nv', 'ho_ten', 'ngay_sinh', 'gioi_tinh', 'sdt', 'email', 'ngay_vao_lam',
            'ma_pb', 'ten_pb', 'ma_cv', 'ten_cv', 'dan_toc', 'cccd', 'noi_cap_cccd',
            'hoc_van', 'ma_tt', 'ky_hieu', 'ten_tt', 'ngay_nghi_viec', 'ma_vt',
            'ky_hieu_vai_tro', 'ten_vt', 'anh_dai_dien', 'dia_chi_cu_the', 'phuong_xa',
            'quan_huyen', 'tinh_thanh',
        ];
    }
}
