<?php

namespace Tests\Integration\MariaDb;

use App\Http\Controllers\Backend\PhongBanController;
use PDO;
use PDOException;

class EmployeeReadProcedureTest extends MariaDbTestCase
{
    private int $departmentOne;

    private int $departmentTwo;

    private int $positionOne;

    private int $positionTwo;

    private int $workingStatus;

    private int $probationStatus;

    protected function setUp(): void
    {
        parent::setUp();

        $this->runSql(base_path('tests/Fixtures/MariaDb/employee_legacy_schema.sql'));
        $this->runSql(base_path('database/sql/employee/2026_08_12_001_schema.sql'));

        $readScript = base_path('database/sql/employee/2026_08_12_002_read_routines.sql');
        if (is_file($readScript)) {
            $this->runSql($readScript);
        }

        $this->seedEmployees();
    }

    public function test_employee_list_has_stable_shape_order_pagination_and_out_total(): void
    {
        [$firstPage, $total] = $this->employeePage(null, null, null, null, 1, 1);

        $this->assertSame(2, $total);
        $this->assertSame(['NV001'], array_column($firstPage, 'ma_nv'));
        $this->assertSame($this->employeeListColumns(), array_keys($firstPage[0]));
        $this->assertArrayNotHasKey('mat_khau', $firstPage[0]);

        [$secondPage, $secondTotal] = $this->employeePage(null, null, null, null, 2, 1);
        $this->assertSame(2, $secondTotal);
        $this->assertSame(['NV002'], array_column($secondPage, 'ma_nv'));

        [$outsidePage, $outsideTotal] = $this->employeePage(null, null, null, null, 3, 1);
        $this->assertSame(2, $outsideTotal);
        $this->assertSame([], $outsidePage);
    }

    public function test_employee_list_searches_all_contract_fields_and_applies_all_three_filters(): void
    {
        foreach (['NV001', 'Nguyễn An', 'an@example.test', '123456789001', 'Kỹ thuật', 'Lập trình viên'] as $keyword) {
            [$rows, $total] = $this->employeePage($keyword, null, null, null, 1, 20);
            $this->assertSame(1, $total, "Unexpected total for keyword [{$keyword}].");
            $this->assertSame(['NV001'], array_column($rows, 'ma_nv'));
        }

        [$rows, $total] = $this->employeePage(
            null,
            $this->departmentTwo,
            $this->positionTwo,
            $this->probationStatus,
            1,
            20,
        );
        $this->assertSame(1, $total);
        $this->assertSame(['NV002'], array_column($rows, 'ma_nv'));

        [$noRows, $noTotal] = $this->employeePage(
            null,
            $this->departmentOne,
            $this->positionTwo,
            $this->workingStatus,
            1,
            20,
        );
        $this->assertSame(0, $noTotal);
        $this->assertSame([], $noRows);
    }

    public function test_employee_list_rejects_invalid_page_and_page_size(): void
    {
        foreach ([[0, 20], [1, 0], [1, 101]] as [$page, $perPage]) {
            try {
                $this->employeePage(null, null, null, null, $page, $perPage);
                $this->fail("Pagination [{$page}, {$perPage}] should be rejected.");
            } catch (PDOException $exception) {
                $this->assertStringContainsString('NV_PAGINATION_INVALID', $exception->getMessage());
            }
        }
    }

    public function test_employee_list_returns_empty_page_and_out_total_for_a_large_positive_page(): void
    {
        [$rows, $total] = $this->employeePage(null, null, null, null, 21_474_838, 100);

        $this->assertSame(2, $total);
        $this->assertSame([], $rows);
    }

    public function test_employee_detail_has_exact_safe_columns_and_nullable_legacy_address(): void
    {
        $statement = $this->pdo()->prepare('CALL sp_nhan_vien_chi_tiet(?)');
        $statement->execute(['NV002']);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        $statement->closeCursor();

        $this->assertIsArray($row);
        $this->assertSame($this->employeeDetailColumns(), array_keys($row));
        $this->assertSame('NHAN_VIEN_MAC_DINH', $row['ky_hieu_vai_tro']);
        foreach (['dia_chi_cu_the', 'phuong_xa', 'quan_huyen', 'tinh_thanh'] as $column) {
            $this->assertNull($row[$column]);
        }
        $this->assertArrayNotHasKey('mat_khau', $row);

        $missing = $this->pdo()->prepare('CALL sp_nhan_vien_chi_tiet(?)');
        $missing->execute(['NV999']);
        $this->assertFalse($missing->fetch(PDO::FETCH_ASSOC));
        $missing->closeCursor();
    }

    public function test_attendance_list_filters_aggregates_exact_month_and_returns_zeroes_for_missing_rows(): void
    {
        $this->seedAttendance();

        [$rows, $total] = $this->attendancePage(null, null, 8, 2026, 1, 20);
        $this->assertSame(2, $total);
        $this->assertSame(['NV001', 'NV002'], array_column($rows, 'ma_nv'));
        $this->assertSame($this->attendanceColumns(), array_keys($rows[0]));
        $this->assertSame(2, (int) $rows[0]['so_lan_vao_muon']);
        $this->assertSame(2, (int) $rows[0]['so_lan_ve_som']);
        $this->assertSame(3.0, (float) $rows[0]['so_ngay_cham_cong']);
        $this->assertSame(0, (int) $rows[1]['so_lan_vao_muon']);
        $this->assertSame(0, (int) $rows[1]['so_lan_ve_som']);
        $this->assertSame(0.0, (float) $rows[1]['so_ngay_cham_cong']);

        foreach ([
            ['Nguyễn', null],
            ['an@example.test', null],
            [null, $this->departmentOne],
        ] as [$keyword, $department]) {
            [$filtered, $filteredTotal] = $this->attendancePage($keyword, $department, 8, 2026, 1, 20);
            $this->assertSame(1, $filteredTotal);
            $this->assertSame(['NV001'], array_column($filtered, 'ma_nv'));
        }

        [$september, $septemberTotal] = $this->attendancePage('NV001', null, 9, 2026, 1, 20);
        $this->assertSame(1, $septemberTotal);
        $this->assertSame(1.0, (float) $september[0]['so_ngay_cham_cong']);

        [$outside, $outsideTotal] = $this->attendancePage(null, null, 8, 2026, 3, 1);
        $this->assertSame(2, $outsideTotal);
        $this->assertSame([], $outside);
    }

    public function test_attendance_day_credit_thresholds_are_mapped_individually(): void
    {
        $this->seedAttendanceThresholdsByMonth();

        foreach ([1 => 0.0, 2 => 0.5, 3 => 0.5, 4 => 1.0, 5 => 1.0] as $month => $expectedDays) {
            [$rows, $total] = $this->attendancePage('NV001', null, $month, 2026, 1, 20);

            $this->assertSame(1, $total, "Unexpected total for threshold month [{$month}].");
            $this->assertSame('NV001', $rows[0]['ma_nv']);
            $this->assertSame(
                $expectedDays,
                (float) $rows[0]['so_ngay_cham_cong'],
                "Unexpected day credit for threshold month [{$month}].",
            );
        }
    }

    public function test_attendance_list_returns_empty_page_and_out_total_for_a_large_positive_page(): void
    {
        [$rows, $total] = $this->attendancePage(null, null, 8, 2026, 21_474_838, 100);

        $this->assertSame(2, $total);
        $this->assertSame([], $rows);
    }

    public function test_attendance_list_rejects_invalid_month_year_page_and_page_size(): void
    {
        foreach ([
            [0, 2026, 1, 20],
            [13, 2026, 1, 20],
            [8, 1999, 1, 20],
            [8, 2101, 1, 20],
            [8, 2026, 0, 20],
            [8, 2026, 1, 0],
            [8, 2026, 1, 101],
        ] as [$month, $year, $page, $perPage]) {
            try {
                $this->attendancePage(null, null, $month, $year, $page, $perPage);
                $this->fail('Invalid attendance pagination/filter input should be rejected.');
            } catch (PDOException $exception) {
                $this->assertStringContainsString('NV_PAGINATION_INVALID', $exception->getMessage());
            }
        }
    }

    public function test_shared_lookup_shapes_remain_compatible_with_the_real_department_controller(): void
    {
        $expected = [
            'sp_phong_ban_danh_sach' => ['ma_pb', 'ten_pb', 'so_nhan_vien'],
            'sp_chuc_vu_danh_sach' => ['ma_cv', 'ten_cv', 'he_so_phu_cap'],
            'sp_vai_tro_danh_sach' => ['ma_vt', 'ten_vt', 'mo_ta'],
            'sp_trang_thai_lam_viec_danh_sach' => ['ma_tt', 'ky_hieu', 'ten_tt'],
        ];

        foreach ($expected as $procedure => $columns) {
            $rows = $this->pdo()->query("CALL {$procedure}()")->fetchAll(PDO::FETCH_ASSOC);
            $this->assertNotEmpty($rows);
            $this->assertSame($columns, array_keys($rows[0]));
        }

        $view = $this->app->make(PhongBanController::class)->index();
        $this->assertSame('backend.phongban.index', $view->name());
    }

    private function seedEmployees(): void
    {
        $this->pdo()->exec("INSERT INTO phong_ban (ten_pb) VALUES ('Kỹ thuật'), ('Nhân sự')");
        [$this->departmentOne, $this->departmentTwo] = array_map(
            'intval',
            $this->pdo()->query('SELECT ma_pb FROM phong_ban ORDER BY ma_pb')->fetchAll(PDO::FETCH_COLUMN),
        );

        $this->pdo()->exec("INSERT INTO chuc_vu (ten_cv, he_so_phu_cap) VALUES ('Lập trình viên', 0.20), ('Chuyên viên nhân sự', 0.10)");
        [$this->positionOne, $this->positionTwo] = array_map(
            'intval',
            $this->pdo()->query('SELECT ma_cv FROM chuc_vu ORDER BY ma_cv')->fetchAll(PDO::FETCH_COLUMN),
        );

        $this->workingStatus = (int) $this->pdo()->query(
            "SELECT ma_tt FROM trang_thai_lam_viec WHERE BINARY ky_hieu = BINARY 'DANG_LAM'"
        )->fetchColumn();
        $this->probationStatus = (int) $this->pdo()->query(
            "SELECT ma_tt FROM trang_thai_lam_viec WHERE BINARY ky_hieu = BINARY 'THU_VIEC'"
        )->fetchColumn();
        $role = (int) $this->pdo()->query(
            "SELECT ma_vt FROM vai_tro WHERE BINARY ky_hieu = BINARY 'NHAN_VIEN_MAC_DINH'"
        )->fetchColumn();

        $statement = $this->pdo()->prepare(
            'INSERT INTO nhan_vien (
                ma_nv, ho_ten, ngay_sinh, gioi_tinh, sdt, email, ngay_vao_lam,
                ma_pb, ma_cv, dan_toc, cccd, noi_cap_cccd, hoc_van, ma_tt, mat_khau, ma_vt
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $statement->execute([
            'NV002', 'Trần Bình', '1992-02-02', 0, '0900000002', 'binh@example.test', '2022-02-02',
            $this->departmentTwo, $this->positionTwo, 'Kinh', '123456789002', 'Hà Nội', 'Đại học',
            $this->probationStatus, str_repeat('b', 60), $role,
        ]);
        $statement->execute([
            'NV001', 'Nguyễn An', '1990-01-01', 1, '0900000001', 'an@example.test', '2020-01-01',
            $this->departmentOne, $this->positionOne, 'Kinh', '123456789001', 'TP HCM', 'Đại học',
            $this->workingStatus, str_repeat('a', 60), $role,
        ]);

        $address = $this->pdo()->prepare(
            'INSERT INTO dia_chi_nhan_vien (ma_nv, dia_chi_cu_the, phuong_xa, quan_huyen, tinh_thanh)
             VALUES (?, ?, ?, ?, ?)'
        );
        $address->execute(['NV001', '1 Nguyễn Huệ', 'Bến Nghé', 'Quận 1', 'TP HCM']);
    }

    private function seedAttendance(): void
    {
        foreach ([
            ['2026-08-01', 3, 1, 0],
            ['2026-08-02', 4, 0, 1],
            ['2026-08-03', 7, 1, 0],
            ['2026-08-04', 8, 0, 1],
            ['2026-08-05', 9, 0, 0],
            ['2026-09-01', 8, 1, 1],
        ] as [$date, $hours, $late, $early]) {
            $statement = $this->pdo()->prepare(
                "INSERT INTO cham_cong (ma_nv, ngay_lam, so_gio_lam, vao_muon, ve_som)
                 VALUES (?, ?, ?, b'{$late}', b'{$early}')"
            );
            $statement->execute(['NV001', $date, $hours]);
        }
    }

    private function seedAttendanceThresholdsByMonth(): void
    {
        foreach ([1 => 3, 2 => 4, 3 => 7, 4 => 8, 5 => 9] as $month => $hours) {
            $statement = $this->pdo()->prepare(
                "INSERT INTO cham_cong (ma_nv, ngay_lam, so_gio_lam, vao_muon, ve_som)
                 VALUES (?, ?, ?, b'0', b'0')"
            );
            $statement->execute(['NV001', sprintf('2026-%02d-01', $month), $hours]);
        }
    }

    private function employeePage(mixed $keyword, mixed $department, mixed $position, mixed $status, int $page, int $perPage): array
    {
        $this->pdo()->exec('SET @tong_so = 0');
        $statement = $this->pdo()->prepare(
            'CALL sp_nhan_vien_danh_sach_phan_trang(?, ?, ?, ?, ?, ?, @tong_so)'
        );
        $statement->execute([$keyword, $department, $position, $status, $page, $perPage]);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        $statement->closeCursor();

        $total = (int) $this->pdo()->query('SELECT @tong_so')->fetchColumn();

        return [$rows, $total];
    }

    private function attendancePage(mixed $keyword, mixed $department, int $month, int $year, int $page, int $perPage): array
    {
        $this->pdo()->exec('SET @tong_so_cham_cong = 0');
        $statement = $this->pdo()->prepare(
            'CALL sp_cham_cong_nhan_vien_phan_trang(?, ?, ?, ?, ?, ?, @tong_so_cham_cong)'
        );
        $statement->execute([$keyword, $department, $month, $year, $page, $perPage]);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        $statement->closeCursor();

        $total = (int) $this->pdo()->query('SELECT @tong_so_cham_cong')->fetchColumn();

        return [$rows, $total];
    }

    private function employeeListColumns(): array
    {
        return [
            'ma_nv', 'ho_ten', 'sdt', 'email', 'ngay_vao_lam', 'anh_dai_dien',
            'ma_pb', 'ten_pb', 'ma_cv', 'ten_cv', 'ma_tt', 'ky_hieu', 'ten_tt',
        ];
    }

    private function employeeDetailColumns(): array
    {
        return [
            'ma_nv', 'ho_ten', 'ngay_sinh', 'gioi_tinh', 'sdt', 'email', 'ngay_vao_lam',
            'ma_pb', 'ten_pb', 'ma_cv', 'ten_cv', 'dan_toc', 'cccd', 'noi_cap_cccd',
            'hoc_van', 'ma_tt', 'ky_hieu', 'ten_tt', 'ngay_nghi_viec', 'ma_vt',
            'ky_hieu_vai_tro', 'ten_vt', 'anh_dai_dien', 'dia_chi_cu_the', 'phuong_xa',
            'quan_huyen', 'tinh_thanh',
        ];
    }

    private function attendanceColumns(): array
    {
        return [
            'ma_nv', 'ho_ten', 'gioi_tinh', 'sdt', 'email', 'ma_pb', 'ten_pb',
            'ma_cv', 'ten_cv', 'so_lan_vao_muon', 'so_lan_ve_som', 'so_ngay_cham_cong',
        ];
    }
}
