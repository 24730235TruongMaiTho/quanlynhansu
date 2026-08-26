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
        self::assertSame(12, (int) DB::table('information_schema.ROUTINES')
            ->where('ROUTINE_SCHEMA', DB::raw('DATABASE()'))->count());
        self::assertSame(0, (int) DB::table('information_schema.TRIGGERS')
            ->where('TRIGGER_SCHEMA', DB::raw('DATABASE()'))->count());
        self::assertSame(19, (int) DB::table('nhan_vien')->count());
        self::assertSame(19, (int) DB::table('bo_dem_ma_nhan_vien')
            ->where('ten_bo_dem', 'NHAN_VIEN')->value('so_da_cap'));

        $admin = DB::table('nhan_vien')->where('ma_nv', '00001')->first([
            'ma_vt', 'ma_tt', 'mat_khau',
        ]);
        self::assertNotNull($admin);
        self::assertSame(1, (int) $admin->ma_vt);
        self::assertSame(1, (int) $admin->ma_tt);
        self::assertSame(64, strlen((string) $admin->mat_khau));
        self::assertSame('A665A45920422F9D417E4867EFDC4FB8A04A1F3FFF1FA07E998E86F7F7A27AE3', $admin->mat_khau);
        $permissionCatalog = DB::table('quyen')->orderBy('ma_quyen')->pluck('ma_quyen')
            ->map(static fn ($id): int => (int) $id)->all();
        $expectedCatalog = range(1, 37);
        self::assertSame($expectedCatalog, $permissionCatalog);

        $rolePermissions = DB::table('vai_tro_quyen')->where('ma_vt', 1)
            ->orderBy('ma_quyen')->pluck('ma_quyen')
            ->map(static fn ($id): int => (int) $id)->all();
        self::assertSame($expectedCatalog, $rolePermissions);

        $employeeContract = DB::table('nhan_vien')->orderBy('ma_nv')
            ->get(['ma_nv', 'ho_ten', 'ma_vt', 'ma_tt', 'anh_dai_dien', 'ngay_nghi_viec']);
        self::assertSame(19, $employeeContract->count());
        self::assertSame('Nguyễn Văn An', $employeeContract[0]->ho_ten);
        self::assertSame([1, 1, 1, 2, 4, 3, 5, 5, 5, 5, 2, 5, 5, 5, 3, 5, 5, 5, 5], $employeeContract->pluck('ma_vt')->map(static fn ($id): int => (int) $id)->all());
        self::assertSame([1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1], $employeeContract->pluck('ma_tt')->map(static fn ($id): int => (int) $id)->all());
        self::assertCount(19, $employeeContract->whereNull('anh_dai_dien'));
        self::assertSame(19, $employeeContract->whereNull('ngay_nghi_viec')->count());
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

        self::assertSame('00020', $repository->create($profile, password_hash('secret', PASSWORD_BCRYPT), 'avatars/maria002.jpg'));
        self::assertSame(20, (int) DB::table('bo_dem_ma_nhan_vien')->where('ten_bo_dem', 'NHAN_VIEN')->value('so_da_cap'));

        $repository->upsertAddress('00020', [
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
        ], (array) DB::table('nhan_vien')->where('ma_nv', '00020')->first([
            'dia_chi_cu_the', 'phuong_xa', 'quan_huyen', 'tinh_thanh',
        ]));
        self::assertSame('avatars/maria002.jpg', $repository->replaceAvatarPath('00020', 'avatars/maria002-new.jpg'));
        self::assertSame('avatars/maria002-new.jpg', DB::table('nhan_vien')->where('ma_nv', '00020')->value('anh_dai_dien'));
        self::assertNotNull($repository->find('00020'));
    }

    public function test_repository_stops_at_smallint_counter_limit_while_codes_remain_five_digits(): void
    {
        $this->runFreshPair();
        $repository = app(NhanVienRepository::class);
        DB::table('bo_dem_ma_nhan_vien')
            ->where('ten_bo_dem', 'NHAN_VIEN')
            ->update(['so_da_cap' => 65534]);

        $profile = $this->parallelProfile('employee65535@example.test', '001000065535');
        self::assertSame('65535', $repository->create($profile, password_hash('secret', PASSWORD_BCRYPT), null));
        self::assertSame(65535, (int) DB::table('bo_dem_ma_nhan_vien')
            ->where('ten_bo_dem', 'NHAN_VIEN')->value('so_da_cap'));

        try {
            $repository->create(
                $this->parallelProfile('employee-after-limit@example.test', '001000065536'),
                password_hash('secret', PASSWORD_BCRYPT),
                null,
            );
            self::fail('Bộ đếm SMALLINT UNSIGNED phải dừng sau mã 65535.');
        } catch (NhanVienDomainException $exception) {
            self::assertSame('NV_COUNTER_EXHAUSTED', $exception->domainCode);
        }
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
        self::assertSame('00020', $repository->create($profile, password_hash('secret', PASSWORD_BCRYPT), null));

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

        self::assertContains('00001', $firstDepartmentRows->getCollection()->pluck('ma_nv')->all());
        self::assertContains('00020', $secondDepartmentRows->getCollection()->pluck('ma_nv')->all());
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
        $before = DB::table('nhan_vien')->where('ma_nv', '00001')->first([
            'ma_vt', 'mat_khau', 'ngay_nghi_viec',
        ]);
        self::assertNotNull($before);

        $repository->update('00001', [
            'ho_ten' => 'Nguyễn Văn An cập nhật',
            'email' => 'an.updated@company.com',
            'ma_vt' => 5,
            'mat_khau' => 'plaintext-must-be-ignored',
            'ngay_nghi_viec' => '2026-08-24',
        ]);
        $repository->upsertAddress('00001', [
            'dia_chi_cu_the' => 'Số 01 mới',
            'phuong_xa' => 'Phường Bến Nghé',
            'quan_huyen' => 'Quận 1',
            'tinh_thanh' => 'TP Hồ Chí Minh',
            'ma_vt' => 5,
        ]);
        $repository->replaceAvatarPath('00001', 'avatars/nv001-updated.jpg');

        $after = DB::table('nhan_vien')->where('ma_nv', '00001')->first([
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
            $repository->update('00001', [
                'ho_ten' => 'Không được ghi',
                'ma_tt' => 4,
            ]);
            self::fail('Expected active-to-terminated profile update to be rejected.');
        } catch (NhanVienDomainException $exception) {
            self::assertSame('NV_STATUS_TRANSITION_FORBIDDEN', $exception->domainCode);
            self::assertSame('ma_tt', $exception->field);
        }

        self::assertSame('Nguyễn Văn An', DB::table('nhan_vien')->where('ma_nv', '00001')->value('ho_ten'));
        self::assertSame(1, (int) DB::table('nhan_vien')->where('ma_nv', '00001')->value('ma_tt'));
        self::assertNull(DB::table('nhan_vien')->where('ma_nv', '00001')->value('ngay_nghi_viec'));
    }

    public function test_repository_rejects_terminated_to_active_transition_after_locking_current_status(): void
    {
        $this->runFreshPair();
        $repository = app(NhanVienRepository::class);
        $profile = $this->parallelProfile('terminated@example.test', '001099000002');
        self::assertSame('00020', $repository->create($profile, password_hash('secret', PASSWORD_BCRYPT), null));
        DB::table('nhan_vien')->where('ma_nv', '00020')->update([
            'ma_tt' => 4,
            'ngay_nghi_viec' => '2026-08-24',
        ]);

        try {
            $repository->update('00020', [
                'ho_ten' => 'Không được ghi',
                'ma_tt' => 2,
            ]);
            self::fail('Expected terminated-to-active profile update to be rejected.');
        } catch (NhanVienDomainException $exception) {
            self::assertSame('NV_STATUS_TRANSITION_FORBIDDEN', $exception->domainCode);
            self::assertSame('ma_tt', $exception->field);
        }

        $row = DB::table('nhan_vien')->where('ma_nv', '00020')->first(['ho_ten', 'ma_tt', 'ngay_nghi_viec']);
        self::assertNotNull($row);
        self::assertSame('Nhân viên song song', $row->ho_ten);
        self::assertSame(4, (int) $row->ma_tt);
        self::assertSame('2026-08-24', $row->ngay_nghi_viec);
    }

    public function test_repository_terminates_with_dependency_and_hard_deletes_without_one(): void
    {
        $this->runFreshPair();
        $repository = app(NhanVienRepository::class);
        self::assertSame('00020', $repository->create(
            $this->parallelProfile('with-history@example.test', '001099000002'),
            password_hash('secret', PASSWORD_BCRYPT),
            null,
        ));
        self::assertSame('00021', $repository->create(
            $this->parallelProfile('without-history@example.test', '001099000003'),
            password_hash('secret', PASSWORD_BCRYPT),
            null,
        ));

        DB::table('cham_cong')->insert([
            'ma_nv' => '00020',
            'ngay_lam' => '2026-08-01',
            'so_gio_lam' => 8,
            'vao_muon' => 0,
            've_som' => 0,
        ]);
        $terminated = $repository->removeOrTerminate('00020', CarbonImmutable::parse('2026-08-24'));
        self::assertSame(NhanVienRemovalAction::Terminated, $terminated['action']);
        self::assertSame(4, (int) DB::table('nhan_vien')->where('ma_nv', '00020')->value('ma_tt'));
        self::assertSame('2026-08-24', DB::table('nhan_vien')->where('ma_nv', '00020')->value('ngay_nghi_viec'));

        $deleted = $repository->removeOrTerminate('00021', CarbonImmutable::parse('2026-08-24'));
        self::assertSame(NhanVienRemovalAction::Deleted, $deleted['action']);
        self::assertDatabaseMissing('nhan_vien', ['ma_nv' => '00021'], 'employee_test');
    }

    public function test_role_and_permission_procedures_use_explicit_ids_and_protect_default_role(): void
    {
        $this->runFreshPair();
        $pdo = $this->pdo();
        $result = $pdo->query('CALL sp_vai_tro_danh_sach()');
        self::assertCount(5, $result->fetchAll());
        $result->closeCursor();
        $result = $pdo->query('CALL sp_quyen_danh_sach()');
        self::assertCount(37, $result->fetchAll());
        $result->closeCursor();
        $result = $pdo->query("CALL sp_quyen_lay_theo_ma_nhan_vien('00001')");
        self::assertCount(37, $result->fetchAll());
        $result->closeCursor();
        $pdo->exec("CALL sp_vai_tro_them('Vai trò thử nghiệm', 'Mô tả thử nghiệm')");
        self::assertSame(6, (int) $pdo->query("SELECT MAX(ma_vt) FROM vai_tro")->fetchColumn());
        $pdo->exec("CALL sp_quyen_them('Test.Read', 'Đọc thử nghiệm', 'Test')");
        self::assertSame(38, (int) $pdo->query("SELECT MAX(ma_quyen) FROM quyen")->fetchColumn());
        $pdo->exec('CALL sp_vai_tro_quyen_them(6, 38)');
        self::assertSame(1, (int) $pdo->query(
            'SELECT COUNT(*) FROM vai_tro_quyen WHERE ma_vt = 6 AND ma_quyen = 38'
        )->fetchColumn());
        $result = $pdo->query('CALL sp_vai_tro_quyen_lay_quyen_theo_vai_tro(6)');
        self::assertSame(38, (int) $result->fetchColumn());
        $result->closeCursor();
        $pdo->exec('CALL sp_vai_tro_quyen_xoa(6)');
        self::assertSame(0, (int) $pdo->query(
            'SELECT COUNT(*) FROM vai_tro_quyen WHERE ma_vt = 6'
        )->fetchColumn());
        $pdo->exec("CALL sp_nhan_vien_gan_vai_tro_noi_bo('00019', 6)");
        self::assertSame(6, (int) $pdo->query(
            "SELECT ma_vt FROM nhan_vien WHERE ma_nv = '00019'"
        )->fetchColumn());
        $pdo->exec("CALL sp_vai_tro_them('Vai trò xóa thử nghiệm', 'Mô tả xóa thử nghiệm')");
        self::assertSame(7, (int) $pdo->query("SELECT MAX(ma_vt) FROM vai_tro")->fetchColumn());
        $pdo->exec('CALL sp_vai_tro_xoa(7)');
        self::assertSame(0, (int) $pdo->query(
            'SELECT COUNT(*) FROM vai_tro WHERE ma_vt = 7'
        )->fetchColumn());

        try {
            $pdo->exec('CALL sp_vai_tro_quyen_them(5, 1)');
            self::fail('Vai trò mặc định không được gán thêm quyền.');
        } catch (\PDOException $exception) {
            self::assertStringContainsString('VT_DEFAULT_ROLE_FORBIDDEN', $exception->getMessage());
        }
        try {
            $pdo->exec('CALL sp_vai_tro_xoa(5)');
            self::fail('Vai trò mặc định không được xóa.');
        } catch (\PDOException $exception) {
            self::assertStringContainsString('VT_DEFAULT_ROLE_FORBIDDEN', $exception->getMessage());
        }
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
        self::assertSame(['00020', '00021'], $codes);
        self::assertSame(21, (int) $this->pdo()->query(
            "SELECT so_da_cap FROM bo_dem_ma_nhan_vien WHERE ten_bo_dem = 'NHAN_VIEN'"
        )->fetchColumn());
        self::assertSame(2, (int) $this->pdo()->query(
            "SELECT COUNT(*) FROM nhan_vien WHERE ma_nv IN ('00020', '00021')"
        )->fetchColumn());
    }

    public function test_department_repository_uses_direct_crud_count_and_safe_errors(): void
    {
        $this->runFreshPair();
        $repository = app(PhongBanRepository::class);

        $rows = $repository->all();
        self::assertCount(5, $rows);
        self::assertSame(
            ['ma_pb', 'ten_pb', 'so_nhan_vien'],
            array_keys(get_object_vars($rows[0])),
        );
        self::assertSame([1, 2, 3, 4, 5], array_map(
            static fn (object $row): int => $row->ma_pb,
            $rows,
        ));
        self::assertSame([3, 2, 4, 3, 7], array_map(
            static fn (object $row): int => $row->so_nhan_vien,
            $rows,
        ));
        self::assertSame('IT', $rows[0]->ten_pb);

        $repository->create('  Phòng mới  ');
        $created = $repository->find(6);
        self::assertNotNull($created);
        self::assertSame('Phòng mới', $created->ten_pb);
        self::assertSame(0, $created->so_nhan_vien);

        $repository->update(6, '  Phòng cập nhật  ');
        self::assertSame('Phòng cập nhật', $repository->find(6)->ten_pb);

        try {
            $repository->create('IT');
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

        $repository->delete(6);
        self::assertNull($repository->find(6));
        self::assertSame(12, (int) $this->pdo()->query(
            "SELECT COUNT(*) FROM information_schema.ROUTINES
             WHERE ROUTINE_SCHEMA = DATABASE()"
        )->fetchColumn());
    }

    public function test_position_repository_uses_direct_crud_count_decimal_and_safe_errors(): void
    {
        $this->runFreshPair();
        $repository = app(ChucVuRepository::class);

        $rows = $repository->all();
        self::assertCount(6, $rows);
        self::assertSame(
            ['ma_cv', 'ten_cv', 'he_so_phu_cap', 'so_nhan_vien'],
            array_keys(get_object_vars($rows[0])),
        );
        self::assertSame('Giám đốc', $rows[0]->ten_cv);
        self::assertSame('2.00', $rows[0]->he_so_phu_cap);

        $repository->create('  Cố vấn  ', '1.5');
        $created = $repository->find(7);
        self::assertNotNull($created);
        self::assertSame('Cố vấn', $created->ten_cv);
        self::assertSame('1.50', $created->he_so_phu_cap);

        $repository->update(7, '  Trưởng cố vấn ', '2');
        self::assertSame('Trưởng cố vấn', $repository->find(7)->ten_cv);
        self::assertSame('2.00', $repository->find(7)->he_so_phu_cap);

        try {
            $repository->create('Giám đốc', '1');
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

        $repository->delete(7);
        self::assertNull($repository->find(7));
        self::assertSame(12, (int) $this->pdo()->query(
            "SELECT COUNT(*) FROM information_schema.ROUTINES
             WHERE ROUTINE_SCHEMA = DATABASE()"
        )->fetchColumn());
    }

    private function runFreshPair(): void
    {
        $this->runFreshSource('database/sql/tao_bang.sql', 1);
        $this->runFreshSource('database/sql/du_lieu_mau.sql', 1);
        $this->runFreshSource('database/sql/quyen_vai_tro.sql', 1);
    }

    private function runFreshSource(string $relativePath, int $expectedUseCount): void
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
        self::assertSame($expectedUseCount, $count, "{$relativePath} phải chọn đúng cơ sở dữ liệu đích theo hợp đồng.");

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
