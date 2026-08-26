<?php

namespace Tests\Integration\MariaDb;

use App\Enums\NhanVienRemovalAction;
use App\Exceptions\ChucVuDomainException;
use App\Exceptions\NhanVienDomainException;
use App\Exceptions\PhongBanDomainException;
use App\Repositories\ChucVuRepository;
use App\Repositories\NhanVienRepository;
use App\Repositories\PhongBanRepository;
use App\Support\DisposableMariaDbGuard;
use Carbon\CarbonImmutable;
use Database\Seeders\LocalDemoSeeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\Support\SqlScriptRunner;

/**
 * Kiểm chứng nguồn dựng fresh hiện hành trên MariaDB. Kiểm thử SQLite không thể
 * chứng minh DDL, khóa ngoại, collation hoặc hành vi bcrypt của MariaDB.
 */
final class FreshEmployeeSchemaContractTest extends MariaDbTestCase
{
    public function test_fresh_pair_builds_exactly_fifteen_tables_and_seed_contract(): void
    {
        $this->runFreshPair();

        $tables = DB::select(
            "SELECT TABLE_NAME FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'
             ORDER BY TABLE_NAME"
        );

        self::assertSame([
            'bo_dem_ma_nhan_vien', 'cham_cong', 'chuc_vu', 'hop_dong',
            'lich_su_he_so_luong', 'loai_hop_dong', 'loai_phep', 'luong',
            'nghi_phep', 'nhan_vien', 'phong_ban', 'quyen',
            'trang_thai_lam_viec', 'vai_tro', 'vai_tro_quyen',
        ], array_map(static fn (object $row): string => (string) $row->TABLE_NAME, $tables));

        self::assertSame(0, (int) DB::table('information_schema.VIEWS')
            ->where('TABLE_SCHEMA', DB::raw('DATABASE()'))->count());
        self::assertSame(0, (int) DB::table('information_schema.ROUTINES')
            ->where('ROUTINE_SCHEMA', DB::raw('DATABASE()'))->count());
        self::assertSame(0, (int) DB::table('information_schema.TRIGGERS')
            ->where('TRIGGER_SCHEMA', DB::raw('DATABASE()'))->count());
        self::assertSame(1, (int) DB::table('nhan_vien')->count());
        self::assertSame(1, (int) DB::table('bo_dem_ma_nhan_vien')
            ->where('ten_bo_dem', 'NHAN_VIEN')->value('so_da_cap'));

        $admin = DB::table('nhan_vien')->where('ma_nv', 'NV001')->first([
            'ma_vt', 'ma_tt', 'mat_khau',
        ]);
        self::assertNotNull($admin);
        self::assertSame(1, (int) $admin->ma_vt);
        self::assertSame(2, (int) $admin->ma_tt);
        self::assertTrue(password_verify('nhom3@2026', (string) $admin->mat_khau));
        $permissionCatalog = DB::table('quyen')->orderBy('ma_quyen')->pluck('ma_quyen')
            ->map(static fn ($id): int => (int) $id)->all();
        $expectedCatalog = [
            101, 102, 103, 104, 105,
            201, 202, 203, 204,
            301, 302, 303, 304,
            401, 402, 403, 404,
            801, 802,
        ];
        self::assertSame($expectedCatalog, $permissionCatalog);

        $rolePermissions = DB::table('vai_tro_quyen')->where('ma_vt', 1)
            ->orderBy('ma_quyen')->pluck('ma_quyen')
            ->map(static fn ($id): int => (int) $id)->all();
        self::assertSame($expectedCatalog, $rolePermissions);

        $employeeContract = DB::table('nhan_vien')->orderBy('ma_nv')
            ->get(['ma_nv', 'ho_ten', 'ma_vt', 'ma_tt', 'anh_dai_dien', 'ngay_nghi_viec']);
        self::assertSame(1, $employeeContract->count());
        self::assertSame('Nguyễn Văn An', $employeeContract[0]->ho_ten);
        self::assertSame([1], $employeeContract->pluck('ma_vt')->map(static fn ($id): int => (int) $id)->all());
        self::assertSame([2], $employeeContract->pluck('ma_tt')->map(static fn ($id): int => (int) $id)->all());
        self::assertCount(1, $employeeContract->whereNull('anh_dai_dien'));
        self::assertNull($employeeContract[0]->ngay_nghi_viec);
    }

    public function test_repository_writes_direct_address_avatar_and_counter_contract(): void
    {
        $this->runFreshPair();
        $repository = app(NhanVienRepository::class);

        $profile = [
            'ho_ten' => 'Nhân viên MariaDB 002',
            'ngay_sinh' => '1990-02-01',
            'gioi_tinh' => 1,
            'sdt' => '0900000002',
            'email' => 'employee002@example.test',
            'ngay_vao_lam' => '2024-02-01',
            'ma_pb' => 1,
            'ma_cv' => 1,
            'dan_toc' => 'Kinh',
            'cccd' => '001000000002',
            'noi_cap_cccd' => 'Cuc CSQLHC',
            'hoc_van' => 'Dai hoc',
            'ma_tt' => 2,
        ];

        self::assertSame('NV002', $repository->create($profile, password_hash('secret', PASSWORD_BCRYPT), 'avatars/maria002.jpg'));
        self::assertSame(2, (int) DB::table('bo_dem_ma_nhan_vien')->where('ten_bo_dem', 'NHAN_VIEN')->value('so_da_cap'));

        $repository->upsertAddress('NV002', [
            'dia_chi_cu_the' => '02 Demo',
            'phuong_xa' => 'Phuong 1',
            'quan_huyen' => 'Quan 1',
            'tinh_thanh' => 'TP HCM',
        ]);
        self::assertSame([
            'dia_chi_cu_the' => '02 Demo',
            'phuong_xa' => 'Phuong 1',
            'quan_huyen' => 'Quan 1',
            'tinh_thanh' => 'TP HCM',
        ], (array) DB::table('nhan_vien')->where('ma_nv', 'NV002')->first([
            'dia_chi_cu_the', 'phuong_xa', 'quan_huyen', 'tinh_thanh',
        ]));
        self::assertSame('avatars/maria002.jpg', $repository->replaceAvatarPath('NV002', 'avatars/maria002-new.jpg'));
        self::assertSame('avatars/maria002-new.jpg', DB::table('nhan_vien')->where('ma_nv', 'NV002')->value('anh_dai_dien'));
        self::assertNotNull($repository->find('NV002'));
    }

    public function test_fresh_repository_preserves_department_identity_and_filters_rows_by_department(): void
    {
        $this->runFreshPair();
        $repository = app(NhanVienRepository::class);
        app(PhongBanRepository::class)->create('Phòng kiểm thử');
        $secondDepartment = (int) DB::table('phong_ban')
            ->where('ten_pb', 'Phòng kiểm thử')
            ->value('ma_pb');
        self::assertGreaterThan(1, $secondDepartment);
        $profile = $this->parallelProfile('department-two@example.test', '001099000002');
        $profile['ma_pb'] = $secondDepartment;
        self::assertSame('NV002', $repository->create($profile, password_hash('secret', PASSWORD_BCRYPT), null));

        $firstDepartmentRows = $repository->paginate([
            'ma_pb' => 1,
            'page' => 1,
            'so_dong' => 100,
        ]);
        $secondDepartmentRows = $repository->paginate([
            'ma_pb' => $secondDepartment,
            'page' => 1,
            'so_dong' => 100,
        ]);

        self::assertContains('NV001', $firstDepartmentRows->getCollection()->pluck('ma_nv')->all());
        self::assertContains('NV002', $secondDepartmentRows->getCollection()->pluck('ma_nv')->all());
        self::assertTrue($firstDepartmentRows->getCollection()->every(
            static fn (object $row): bool => (int) $row->ma_pb === 1,
        ));
        self::assertTrue($secondDepartmentRows->getCollection()->every(
            static fn (object $row): bool => (int) $row->ma_pb === $secondDepartment,
        ));
    }

    public function test_repository_updates_privileged_profile_address_and_avatar_without_system_field_mutation(): void
    {
        $this->runFreshPair();
        $repository = app(NhanVienRepository::class);
        $before = DB::table('nhan_vien')->where('ma_nv', 'NV001')->first([
            'ma_vt', 'mat_khau', 'ngay_nghi_viec',
        ]);
        self::assertNotNull($before);

        $repository->update('NV001', [
            'ho_ten' => 'Nguyễn Văn An cập nhật',
            'email' => 'an.updated@company.com',
            'ma_vt' => 5,
            'mat_khau' => 'plaintext-must-be-ignored',
            'ngay_nghi_viec' => '2026-08-24',
        ]);
        $repository->upsertAddress('NV001', [
            'dia_chi_cu_the' => 'Số 01 mới',
            'phuong_xa' => 'Phường Bến Nghé',
            'quan_huyen' => 'Quận 1',
            'tinh_thanh' => 'TP Hồ Chí Minh',
            'ma_vt' => 5,
        ]);
        $repository->replaceAvatarPath('NV001', 'avatars/nv001-updated.jpg');

        $after = DB::table('nhan_vien')->where('ma_nv', 'NV001')->first([
            'ho_ten', 'email', 'ma_vt', 'mat_khau', 'ngay_nghi_viec',
            'dia_chi_cu_the', 'phuong_xa', 'quan_huyen', 'tinh_thanh', 'anh_dai_dien',
        ]);
        self::assertNotNull($after);
        self::assertSame('Nguyễn Văn An cập nhật', $after->ho_ten);
        self::assertSame('an.updated@company.com', $after->email);
        self::assertSame((int) $before->ma_vt, (int) $after->ma_vt);
        self::assertSame($before->mat_khau, $after->mat_khau);
        self::assertSame($before->ngay_nghi_viec, $after->ngay_nghi_viec);
        self::assertSame('Số 01 mới', $after->dia_chi_cu_the);
        self::assertSame('avatars/nv001-updated.jpg', $after->anh_dai_dien);
    }

    public function test_repository_rejects_active_to_terminated_transition_after_locking_current_status(): void
    {
        $this->runFreshPair();
        $repository = app(NhanVienRepository::class);

        try {
            $repository->update('NV001', [
                'ho_ten' => 'Không được ghi',
                'ma_tt' => 4,
            ]);
            self::fail('Expected active-to-terminated profile update to be rejected.');
        } catch (NhanVienDomainException $exception) {
            self::assertSame('NV_STATUS_TRANSITION_FORBIDDEN', $exception->domainCode);
            self::assertSame('ma_tt', $exception->field);
        }

        self::assertSame('Nguyễn Văn An', DB::table('nhan_vien')->where('ma_nv', 'NV001')->value('ho_ten'));
        self::assertSame(2, (int) DB::table('nhan_vien')->where('ma_nv', 'NV001')->value('ma_tt'));
        self::assertNull(DB::table('nhan_vien')->where('ma_nv', 'NV001')->value('ngay_nghi_viec'));
    }

    public function test_repository_rejects_terminated_to_active_transition_after_locking_current_status(): void
    {
        $this->runFreshPair();
        $repository = app(NhanVienRepository::class);
        $profile = $this->parallelProfile('terminated@example.test', '001099000002');
        self::assertSame('NV002', $repository->create($profile, password_hash('secret', PASSWORD_BCRYPT), null));
        DB::table('nhan_vien')->where('ma_nv', 'NV002')->update([
            'ma_tt' => 4,
            'ngay_nghi_viec' => '2026-08-24',
        ]);

        try {
            $repository->update('NV002', [
                'ho_ten' => 'Không được ghi',
                'ma_tt' => 2,
            ]);
            self::fail('Expected terminated-to-active profile update to be rejected.');
        } catch (NhanVienDomainException $exception) {
            self::assertSame('NV_STATUS_TRANSITION_FORBIDDEN', $exception->domainCode);
            self::assertSame('ma_tt', $exception->field);
        }

        $row = DB::table('nhan_vien')->where('ma_nv', 'NV002')->first(['ho_ten', 'ma_tt', 'ngay_nghi_viec']);
        self::assertNotNull($row);
        self::assertSame('Nhân viên song song', $row->ho_ten);
        self::assertSame(4, (int) $row->ma_tt);
        self::assertSame('2026-08-24', $row->ngay_nghi_viec);
    }

    public function test_repository_terminates_with_dependency_and_hard_deletes_without_one(): void
    {
        $this->runFreshPair();
        $repository = app(NhanVienRepository::class);
        self::assertSame('NV002', $repository->create(
            $this->parallelProfile('with-history@example.test', '001099000002'),
            password_hash('secret', PASSWORD_BCRYPT),
            null,
        ));
        self::assertSame('NV003', $repository->create(
            $this->parallelProfile('without-history@example.test', '001099000003'),
            password_hash('secret', PASSWORD_BCRYPT),
            null,
        ));

        DB::table('cham_cong')->insert([
            'ma_nv' => 'NV002',
            'ngay_lam' => '2026-08-01',
            'so_gio_lam' => 8,
            'vao_muon' => 0,
            've_som' => 0,
        ]);
        $terminated = $repository->removeOrTerminate('NV002', CarbonImmutable::parse('2026-08-24'));
        self::assertSame(NhanVienRemovalAction::Terminated, $terminated['action']);
        self::assertSame(4, (int) DB::table('nhan_vien')->where('ma_nv', 'NV002')->value('ma_tt'));
        self::assertSame('2026-08-24', DB::table('nhan_vien')->where('ma_nv', 'NV002')->value('ngay_nghi_viec'));

        $deleted = $repository->removeOrTerminate('NV003', CarbonImmutable::parse('2026-08-24'));
        self::assertSame(NhanVienRemovalAction::Deleted, $deleted['action']);
        self::assertDatabaseMissing('nhan_vien', ['ma_nv' => 'NV003'], 'employee_test');
    }

    public function test_legacy_sixteen_table_fixture_migrates_and_allowlisted_cleanup_is_safe(): void
    {
        $this->runFreshPair();
        $pdo = $this->pdo();

        DB::table('vai_tro')->where('ma_vt', 1)->update(['ten_vt' => 'Super Admin']);
        foreach ([
            2 => 'Quản trị Nhân sự',
            3 => 'Quản trị CBL',
            4 => 'Trưởng phòng',
            5 => 'Nhân viên',
        ] as $roleId => $roleName) {
            DB::table('vai_tro')->updateOrInsert(
                ['ma_vt' => $roleId],
                ['ten_vt' => $roleName, 'mo_ta' => null],
            );
        }
        foreach ([
            1 => 'Thử việc',
            2 => 'Đang làm việc',
            3 => 'Tạm nghỉ không lương',
            4 => 'Đã nghỉ việc',
        ] as $statusId => $statusName) {
            DB::table('trang_thai_lam_viec')->updateOrInsert(
                ['ma_tt' => $statusId],
                ['ten_tt' => $statusName],
            );
        }
        DB::table('quyen')->insert([
            'ma_quyen' => 501,
            'ky_hieu_quyen' => 'LUONG_VIEW',
            'ten_quyen' => 'Xem bảng lương',
            'module' => 'Luong',
        ]);
        foreach ([401, 402, 403, 404] as $permissionId) {
            DB::table('vai_tro_quyen')->insert([
                'ma_vt' => 2,
                'ma_quyen' => $permissionId,
            ]);
        }

        $this->runSql(base_path('tests/Fixtures/MariaDb/employee_legacy_fifteen_plus_address.sql'));
        self::assertSame(5, (int) DB::table('vai_tro')->where(function ($query): void {
            $query->where(fn ($item) => $item->where('ma_vt', 1)->where('ten_vt', 'Super Admin'))
                ->orWhere(fn ($item) => $item->where('ma_vt', 2)->where('ten_vt', 'Quản trị Nhân sự'))
                ->orWhere(fn ($item) => $item->where('ma_vt', 3)->where('ten_vt', 'Quản trị CBL'))
                ->orWhere(fn ($item) => $item->where('ma_vt', 4)->where('ten_vt', 'Trưởng phòng'))
                ->orWhere(fn ($item) => $item->where('ma_vt', 5)->where('ten_vt', 'Nhân viên'));
        })->count(), 'Dữ liệu test phải có đủ năm vai trò với mã cố định.');
        self::assertSame(4, (int) DB::table('trang_thai_lam_viec')->where(function ($query): void {
            $query->where(fn ($item) => $item->where('ma_tt', 1)->where('ten_tt', 'Thử việc'))
                ->orWhere(fn ($item) => $item->where('ma_tt', 2)->where('ten_tt', 'Đang làm việc'))
                ->orWhere(fn ($item) => $item->where('ma_tt', 3)->where('ten_tt', 'Tạm nghỉ không lương'))
                ->orWhere(fn ($item) => $item->where('ma_tt', 4)->where('ten_tt', 'Đã nghỉ việc'));
        })->count(), 'Dữ liệu test phải có đủ bốn trạng thái với mã cố định.');
        self::assertSame(0, (int) DB::table('nhan_vien')
            ->whereNotBetween('ma_vt', [1, 5])
            ->orWhereNotBetween('ma_tt', [1, 4])
            ->count(), 'Nhân viên test phải tham chiếu đúng mã vai trò và trạng thái.');
        foreach ([
            101 => ['NV_VIEW', 'NhanVien'], 102 => ['NV_CREATE', 'NhanVien'],
            103 => ['NV_EDIT', 'NhanVien'], 104 => ['NV_DELETE', 'NhanVien'],
            201 => ['PB_VIEW', 'PhongBan'], 202 => ['PB_CREATE', 'PhongBan'],
            203 => ['PB_EDIT', 'PhongBan'],
            204 => ['PB_DELETE', 'PhongBan'], 301 => ['CV_VIEW', 'ChucVu'],
            302 => ['CV_CREATE', 'ChucVu'], 303 => ['CV_EDIT', 'ChucVu'],
            304 => ['CV_DELETE', 'ChucVu'],
        ] as $permissionId => [$symbol, $module]) {
            $permission = DB::table('quyen')->where('ma_quyen', $permissionId)->first([
                'ky_hieu_quyen', 'module',
            ]);
            self::assertNotNull($permission, "Thiếu quyền {$permissionId} trong fixture migration.");
            self::assertSame($symbol, $permission->ky_hieu_quyen);
            self::assertSame($module, $permission->module);
        }
        $pdo->exec('CREATE VIEW vw_danh_sach_nhan_vien_chi_tiet AS SELECT ma_nv, ho_ten FROM nhan_vien');
        $pdo->exec('CREATE PROCEDURE sp_nhan_vien_danh_sach() SELECT ma_nv FROM nhan_vien');
        $pdo->exec('CREATE FUNCTION fn_dem_nhan_vien_theo_phong_ban(p_ma_pb INT) RETURNS INT DETERMINISTIC RETURN 0');
        $pdo->exec('CREATE PROCEDURE sp_cham_cong_sentinel() SELECT 1');

        self::assertSame(16, (int) $pdo->query(
            "SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'"
        )->fetchColumn());

        $this->runSql(base_path('database/sql/employee/2026_08_24_001_migrate_to_fifteen_tables.sql'));

        self::assertSame(15, (int) $pdo->query(
            "SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'"
        )->fetchColumn());
        self::assertSame(1, (int) $pdo->query(
            "SELECT COUNT(*) FROM nhan_vien
             WHERE ma_nv = 'NV001' AND dia_chi_cu_the = 'Số 01 đường Lê Lợi'
               AND phuong_xa = 'Phường Bến Nghé' AND quan_huyen = 'Quận 1'"
        )->fetchColumn());
        self::assertSame(1, (int) $pdo->query("SELECT COUNT(*) FROM quyen WHERE ma_quyen = 105")->fetchColumn());
        self::assertSame(10, (int) $pdo->query(
            'SELECT COUNT(*) FROM vai_tro_quyen WHERE ma_vt IN (1, 2) AND ma_quyen BETWEEN 101 AND 105'
        )->fetchColumn());
        self::assertSame(1, (int) $pdo->query(
            "SELECT COUNT(*) FROM vai_tro_quyen WHERE ma_vt = 1 AND ma_quyen = 201"
        )->fetchColumn());
        self::assertSame(8, (int) $pdo->query(
            "SELECT COUNT(*) FROM vai_tro_quyen WHERE ma_vt = 2 AND ma_quyen IN (201, 202, 203, 204, 301, 302, 303, 304)"
        )->fetchColumn());
        self::assertSame(17, (int) $pdo->query(
            "SELECT COUNT(*) FROM vai_tro_quyen WHERE ma_vt = 2 AND ma_quyen IN (101, 102, 103, 104, 105, 201, 202, 203, 204, 301, 302, 303, 304, 401, 402, 403, 404)"
        )->fetchColumn());
        self::assertSame(1, (int) $pdo->query(
            "SELECT COUNT(*) FROM vai_tro_quyen WHERE ma_vt = 2 AND ma_quyen = 501"
        )->fetchColumn());
        self::assertSame(0, (int) $pdo->query(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('vai_tro', 'trang_thai_lam_viec')
               AND COLUMN_NAME = 'ky_hieu'"
        )->fetchColumn());
        self::assertSame('1', (string) $pdo->query(
            "SELECT so_da_cap FROM bo_dem_ma_nhan_vien WHERE ten_bo_dem = 'NHAN_VIEN'"
        )->fetchColumn());

        $this->runSql(base_path('database/sql/employee/2026_08_24_002_cleanup_legacy_employee_objects.sql'));
        self::assertSame(0, (int) $pdo->query(
            "SELECT COUNT(*) FROM information_schema.VIEWS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vw_danh_sach_nhan_vien_chi_tiet'"
        )->fetchColumn());
        self::assertSame(0, (int) $pdo->query(
            "SELECT COUNT(*) FROM information_schema.ROUTINES
             WHERE ROUTINE_SCHEMA = DATABASE() AND ROUTINE_NAME = 'sp_nhan_vien_danh_sach'"
        )->fetchColumn());
        self::assertSame(0, (int) $pdo->query(
            "SELECT COUNT(*) FROM information_schema.ROUTINES
             WHERE ROUTINE_SCHEMA = DATABASE() AND ROUTINE_NAME = 'fn_dem_nhan_vien_theo_phong_ban'"
        )->fetchColumn());
        self::assertSame(1, (int) $pdo->query(
            "SELECT COUNT(*) FROM information_schema.ROUTINES
             WHERE ROUTINE_SCHEMA = DATABASE() AND ROUTINE_NAME = 'sp_cham_cong_sentinel'"
        )->fetchColumn());
    }

    public function test_parallel_direct_repository_creates_issue_unique_consecutive_codes(): void
    {
        $this->runFreshPair();
        [$first, $second] = $this->runDirectCreateRace(
            $this->parallelProfile('parallel-one@example.test', '001099000002'),
            $this->parallelProfile('parallel-two@example.test', '001099000003'),
        );

        self::assertTrue($first['ok'] ?? false, json_encode($first));
        self::assertTrue($second['ok'] ?? false, json_encode($second));
        $codes = [(string) $first['ma_nv'], (string) $second['ma_nv']];
        sort($codes);
        self::assertSame(['NV002', 'NV003'], $codes);
        self::assertSame(3, (int) $this->pdo()->query(
            "SELECT so_da_cap FROM bo_dem_ma_nhan_vien WHERE ten_bo_dem = 'NHAN_VIEN'"
        )->fetchColumn());
        self::assertSame(2, (int) $this->pdo()->query(
            "SELECT COUNT(*) FROM nhan_vien WHERE ma_nv IN ('NV002', 'NV003')"
        )->fetchColumn());
    }

    public function test_department_repository_uses_direct_crud_count_and_safe_errors(): void
    {
        $this->runFreshPair();
        $repository = app(PhongBanRepository::class);

        $rows = $repository->all();
        self::assertCount(1, $rows);
        self::assertSame(
            ['ma_pb', 'ten_pb', 'so_nhan_vien'],
            array_keys(get_object_vars($rows[0])),
        );
        self::assertSame([1], array_map(
            static fn (object $row): int => $row->ma_pb,
            $rows,
        ));
        self::assertSame([1], array_map(
            static fn (object $row): int => $row->so_nhan_vien,
            $rows,
        ));
        self::assertSame('Phòng Công nghệ thông tin', $rows[0]->ten_pb);

        $repository->create('  Phòng mới  ');
        $created = $repository->find(2);
        self::assertNotNull($created);
        self::assertSame('Phòng mới', $created->ten_pb);
        self::assertSame(0, $created->so_nhan_vien);

        $repository->update(2, '  Phòng cập nhật  ');
        self::assertSame('Phòng cập nhật', $repository->find(2)->ten_pb);

        try {
            $repository->create('Phòng Công nghệ thông tin');
            self::fail('Expected duplicate department name to be rejected.');
        } catch (PhongBanDomainException $exception) {
            self::assertSame('PB_NAME_DUPLICATE', $exception->domainCode);
        }

        try {
            $repository->update(999, 'Không tồn tại');
            self::fail('Expected missing department to be rejected.');
        } catch (PhongBanDomainException $exception) {
            self::assertSame('PB_NOT_FOUND', $exception->domainCode);
        }

        try {
            $repository->delete(1);
            self::fail('Expected an in-use seeded department to be protected.');
        } catch (PhongBanDomainException $exception) {
            self::assertSame('PB_IN_USE', $exception->domainCode);
        }

        $repository->delete(2);
        self::assertNull($repository->find(2));
        self::assertSame(0, (int) $this->pdo()->query(
            "SELECT COUNT(*) FROM information_schema.ROUTINES
             WHERE ROUTINE_SCHEMA = DATABASE()"
        )->fetchColumn());
    }

    public function test_position_repository_uses_direct_crud_count_decimal_and_safe_errors(): void
    {
        $this->runFreshPair();
        $repository = app(ChucVuRepository::class);

        $rows = $repository->all();
        self::assertCount(1, $rows);
        self::assertSame(
            ['ma_cv', 'ten_cv', 'he_so_phu_cap', 'so_nhan_vien'],
            array_keys(get_object_vars($rows[0])),
        );
        self::assertSame('Quản trị hệ thống', $rows[0]->ten_cv);
        self::assertSame('1.00', $rows[0]->he_so_phu_cap);

        $repository->create('  Cố vấn  ', '1.5');
        $created = $repository->find(2);
        self::assertNotNull($created);
        self::assertSame('Cố vấn', $created->ten_cv);
        self::assertSame('1.50', $created->he_so_phu_cap);

        $repository->update(2, '  Trưởng cố vấn ', '2');
        self::assertSame('Trưởng cố vấn', $repository->find(2)->ten_cv);
        self::assertSame('2.00', $repository->find(2)->he_so_phu_cap);

        try {
            $repository->create('Quản trị hệ thống', '1');
            self::fail('Expected duplicate position name to be rejected.');
        } catch (ChucVuDomainException $exception) {
            self::assertSame('CV_NAME_DUPLICATE', $exception->domainCode);
        }

        try {
            $repository->update(999, 'Không tồn tại', '1');
            self::fail('Expected missing position to be rejected.');
        } catch (ChucVuDomainException $exception) {
            self::assertSame('CV_NOT_FOUND', $exception->domainCode);
        }

        try {
            $repository->delete(1);
            self::fail('Expected an in-use seeded position to be protected.');
        } catch (ChucVuDomainException $exception) {
            self::assertSame('CV_IN_USE', $exception->domainCode);
        }

        $repository->delete(2);
        self::assertNull($repository->find(2));
        self::assertSame(0, (int) $this->pdo()->query(
            "SELECT COUNT(*) FROM information_schema.ROUTINES
             WHERE ROUTINE_SCHEMA = DATABASE()"
        )->fetchColumn());
    }

    private function runFreshPair(): void
    {
        $this->runFreshSource('database/sql/tao_bang.sql');
        (new LocalDemoSeeder())->run();
    }

    private function runFreshSource(string $relativePath): void
    {
        $source = file_get_contents(base_path($relativePath));
        self::assertIsString($source);
        $source = preg_replace(
            '/^\s*USE\s+quan_ly_nhan_su\s*;\s*$/mi',
            'USE `'.$this->databaseName().'`;',
            $source,
            -1,
            $count,
        );
        self::assertIsString($source);
        self::assertSame(1, $count, "{$relativePath} must select the expected source database exactly once.");

        $path = tempnam(sys_get_temp_dir(), 'fresh-employee-sql-');
        self::assertNotFalse($path);
        try {
            self::assertNotFalse(file_put_contents($path, $source));
            SqlScriptRunner::run($this->pdo(), $path);
        } finally {
            if (is_string($path) && is_file($path)) {
                unlink($path);
            }
        }
    }

    /** @return array<string, mixed> */
    private function parallelProfile(string $email, string $cccd): array
    {
        return [
            'ho_ten' => 'Nhân viên song song',
            'ngay_sinh' => '1990-02-01',
            'gioi_tinh' => 1,
            'sdt' => '0900000031',
            'email' => $email,
            'ngay_vao_lam' => '2024-02-01',
            'ma_pb' => 1,
            'ma_cv' => 1,
            'dan_toc' => 'Kinh',
            'cccd' => $cccd,
            'noi_cap_cccd' => 'Cục CSQLHC',
            'hoc_van' => 'Đại học',
            'ma_tt' => 2,
        ];
    }

    /** @return array{0: array<string, mixed>, 1: array<string, mixed>} */
    private function runDirectCreateRace(array $firstProfile, array $secondProfile): array
    {
        $barrier = tempnam(sys_get_temp_dir(), 'nv-direct-barrier-');
        $firstReady = tempnam(sys_get_temp_dir(), 'nv-direct-ready-');
        $secondReady = tempnam(sys_get_temp_dir(), 'nv-direct-ready-');
        if ($barrier === false || $firstReady === false || $secondReady === false) {
            throw new RuntimeException('Unable to reserve direct worker markers.');
        }
        unlink($barrier);
        unlink($firstReady);
        unlink($secondReady);

        $first = null;
        $second = null;
        try {
            $first = $this->newDirectWorker($firstProfile, $barrier, $firstReady);
            $second = $this->newDirectWorker($secondProfile, $barrier, $secondReady);
            $first->start();
            $second->start();

            $deadline = microtime(true) + 20;
            while (! (is_file($firstReady) && is_file($secondReady))) {
                if (! $first->isRunning() || ! $second->isRunning()) {
                    throw new RuntimeException(
                        'Direct worker exited before ready handshake: '
                        .$first->getErrorOutput().' '.$second->getErrorOutput()
                    );
                }
                if (microtime(true) >= $deadline) {
                    throw new RuntimeException('Direct worker ready handshake timed out.');
                }
                usleep(10_000);
            }

            file_put_contents($barrier, 'go');
            $first->wait();
            $second->wait();

            return [$this->directWorkerResult($first), $this->directWorkerResult($second)];
        } finally {
            foreach ([$first, $second] as $process) {
                if ($process instanceof Process && $process->isRunning()) {
                    $process->stop(1);
                }
            }
            foreach ([$barrier, $firstReady, $secondReady] as $path) {
                if (is_string($path) && is_file($path)) {
                    unlink($path);
                }
            }
        }
    }

    private function newDirectWorker(array $profile, string $barrier, string $ready): Process
    {
        $testEnvironment = DisposableMariaDbGuard::environment();
        $environment = [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => $testEnvironment['host'],
            'DB_PORT' => $testEnvironment['port'],
            'DB_DATABASE' => $this->databaseName(),
            'DB_USERNAME' => $testEnvironment['username'],
            'DB_PASSWORD' => $testEnvironment['password'],
            'DB_URL' => '',
            'DB_SOCKET' => '',
            'MARIADB_TEST_ENABLED' => '1',
            'MARIADB_TEST_HOST' => $testEnvironment['host'],
            'MARIADB_TEST_PORT' => $testEnvironment['port'],
            'MARIADB_TEST_USERNAME' => $testEnvironment['username'],
            'MARIADB_TEST_PASSWORD' => $testEnvironment['password'],
            'MARIADB_TEST_DATABASE' => $this->databaseName(),
            'MARIADB_TEST_BARRIER' => $barrier,
            'MARIADB_TEST_READY' => $ready,
            'MARIADB_TEST_PROFILE' => json_encode($profile, JSON_THROW_ON_ERROR),
        ];

        return new Process(
            [PHP_BINARY, base_path('tests/Support/MariaDbDirectEmployeeCreateWorker.php')],
            base_path(),
            $environment,
            timeout: 45,
        );
    }

    /** @return array<string, mixed> */
    private function directWorkerResult(Process $process): array
    {
        $output = $process->isSuccessful() ? $process->getOutput() : $process->getErrorOutput();
        $result = json_decode($output, true);
        self::assertIsArray($result, "Direct worker returned invalid JSON: {$output}");

        return $result;
    }
}
