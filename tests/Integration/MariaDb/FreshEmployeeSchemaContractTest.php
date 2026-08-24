<?php

namespace Tests\Integration\MariaDb;

use App\Enums\NhanVienRemovalAction;
use App\Exceptions\PhongBanDomainException;
use App\Repositories\NhanVienRepository;
use App\Repositories\PhongBanRepository;
use App\Support\DisposableMariaDbGuard;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\Support\SqlScriptRunner;

/**
 * MariaDB-only proof for the active fresh source. SQLite schema tests cannot
 * prove MariaDB DDL/FK/collation or bcrypt seed behavior.
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
        self::assertSame(30, (int) DB::table('nhan_vien')->count());
        self::assertSame(30, (int) DB::table('bo_dem_ma_nhan_vien')
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
            501, 502, 503, 504,
            601, 602, 603,
            701, 702, 703,
            801, 802,
        ];
        self::assertSame($expectedCatalog, $permissionCatalog);

        $expectedRolePermissions = [
            1 => $expectedCatalog,
            2 => [101, 102, 103, 104, 105, 201, 202, 203, 204, 301, 302, 303, 304, 401, 402, 403, 404],
            3 => [501, 502, 503, 504, 601, 602, 603, 701, 702, 703],
            4 => [101, 401, 601, 603, 701],
            5 => [101, 601, 602, 701],
        ];
        foreach ($expectedRolePermissions as $roleId => $expectedPermissions) {
            $rolePermissions = DB::table('vai_tro_quyen')->where('ma_vt', $roleId)
                ->orderBy('ma_quyen')->pluck('ma_quyen')
                ->map(static fn ($id): int => (int) $id)->all();
            self::assertSame($expectedPermissions, $rolePermissions, "Unexpected permissions for role {$roleId}.");
        }

        $employeeContract = DB::table('nhan_vien')->orderBy('ma_nv')
            ->get(['ma_nv', 'ho_ten', 'ma_vt', 'ma_tt', 'anh_dai_dien', 'ngay_nghi_viec']);
        self::assertSame(30, $employeeContract->count());
        self::assertSame('Nguyễn Văn An', $employeeContract[0]->ho_ten);
        self::assertSame('Bùi Ánh Tuyết', $employeeContract[29]->ho_ten);
        self::assertSame(
            [1, 2, 3, 4, 4, 2, 5, 5, 3, ...array_fill(0, 21, 5)],
            $employeeContract->pluck('ma_vt')->map(static fn ($id): int => (int) $id)->all(),
        );
        self::assertSame(
            [2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 1, 1, 1, 3, 3, 4, 4],
            $employeeContract->pluck('ma_tt')->map(static fn ($id): int => (int) $id)->all(),
        );
        self::assertCount(30, $employeeContract->whereNull('anh_dai_dien'));
        self::assertSame('2025-01-15', $employeeContract[28]->ngay_nghi_viec);
    }

    public function test_repository_writes_direct_address_avatar_and_counter_contract(): void
    {
        $this->runFreshPair();
        $repository = app(NhanVienRepository::class);

        $profile = [
            'ho_ten' => 'MariaDB Employee 031',
            'ngay_sinh' => '1990-02-01',
            'gioi_tinh' => 1,
            'sdt' => '0900000031',
            'email' => 'employee031@example.test',
            'ngay_vao_lam' => '2024-02-01',
            'ma_pb' => 1,
            'ma_cv' => 4,
            'dan_toc' => 'Kinh',
            'cccd' => '001000000031',
            'noi_cap_cccd' => 'Cuc CSQLHC',
            'hoc_van' => 'Dai hoc',
            'ma_tt' => 2,
        ];

        self::assertSame('NV031', $repository->create($profile, password_hash('secret', PASSWORD_BCRYPT), 'avatars/maria031.jpg'));
        self::assertSame(31, (int) DB::table('bo_dem_ma_nhan_vien')->where('ten_bo_dem', 'NHAN_VIEN')->value('so_da_cap'));

        $repository->upsertAddress('NV031', [
            'dia_chi_cu_the' => '31 Demo',
            'phuong_xa' => 'Phuong 1',
            'quan_huyen' => 'Quan 1',
            'tinh_thanh' => 'TP HCM',
        ]);
        self::assertSame([
            'dia_chi_cu_the' => '31 Demo',
            'phuong_xa' => 'Phuong 1',
            'quan_huyen' => 'Quan 1',
            'tinh_thanh' => 'TP HCM',
        ], (array) DB::table('nhan_vien')->where('ma_nv', 'NV031')->first([
            'dia_chi_cu_the', 'phuong_xa', 'quan_huyen', 'tinh_thanh',
        ]));
        self::assertSame('avatars/maria031.jpg', $repository->replaceAvatarPath('NV031', 'avatars/maria031-new.jpg'));
        self::assertSame('avatars/maria031-new.jpg', DB::table('nhan_vien')->where('ma_nv', 'NV031')->value('anh_dai_dien'));
        self::assertNotNull($repository->find('NV031'));
    }

    public function test_department_repository_uses_fresh_tables_directly_for_shape_and_crud_errors(): void
    {
        $this->runFreshPair();
        $repository = app(PhongBanRepository::class);

        $rows = $repository->all();
        self::assertCount(5, $rows);
        self::assertSame(['ma_pb', 'ten_pb', 'so_nhan_vien'], array_keys(get_object_vars($rows[0])));
        self::assertSame(1, $rows[0]->ma_pb);
        self::assertSame('Phòng Nhân sự', $rows[0]->ten_pb);
        self::assertGreaterThan(0, $rows[0]->so_nhan_vien);

        $repository->create('  Phòng Mới  ');
        $created = $repository->find(6);
        self::assertNotNull($created);
        self::assertSame('Phòng Mới', $created->ten_pb);
        self::assertSame(0, $created->so_nhan_vien);

        $repository->update(6, '  Phòng Mới Cập nhật  ');
        self::assertSame('Phòng Mới Cập nhật', $repository->find(6)?->ten_pb);

        try {
            $repository->create('Phòng Nhân sự');
            self::fail('Fresh unique department names must be enforced by the database.');
        } catch (PhongBanDomainException $exception) {
            self::assertSame('PB_NAME_DUPLICATE', $exception->domainCode);
        }

        try {
            $repository->delete(1);
            self::fail('A department referenced by seed employees must not be deleted.');
        } catch (PhongBanDomainException $exception) {
            self::assertSame('PB_IN_USE', $exception->domainCode);
        }

        $repository->delete(6);
        self::assertNull($repository->find(6));

        try {
            $repository->update(999, 'Không tồn tại');
            self::fail('Missing department targets must be rejected.');
        } catch (PhongBanDomainException $exception) {
            self::assertSame('PB_NOT_FOUND', $exception->domainCode);
        }

        self::assertSame(0, (int) DB::table('information_schema.ROUTINES')
            ->where('ROUTINE_SCHEMA', DB::raw('DATABASE()'))->count());
    }

    public function test_repository_terminates_with_dependency_and_hard_deletes_without_one(): void
    {
        $this->runFreshPair();
        $repository = app(NhanVienRepository::class);

        DB::table('cham_cong')->insert([
            'ma_nv' => 'NV007',
            'ngay_lam' => '2026-08-01',
            'so_gio_lam' => 8,
            'vao_muon' => 0,
            've_som' => 0,
        ]);
        $terminated = $repository->removeOrTerminate('NV007', CarbonImmutable::parse('2026-08-24'));
        self::assertSame(NhanVienRemovalAction::Terminated, $terminated['action']);
        self::assertSame(4, (int) DB::table('nhan_vien')->where('ma_nv', 'NV007')->value('ma_tt'));
        self::assertSame('2026-08-24', DB::table('nhan_vien')->where('ma_nv', 'NV007')->value('ngay_nghi_viec'));

        $deleted = $repository->removeOrTerminate('NV008', CarbonImmutable::parse('2026-08-24'));
        self::assertSame(NhanVienRemovalAction::Deleted, $deleted['action']);
        self::assertDatabaseMissing('nhan_vien', ['ma_nv' => 'NV008'], 'employee_test');
    }

    public function test_legacy_sixteen_table_fixture_migrates_and_allowlisted_cleanup_is_safe(): void
    {
        $this->runFreshPair();
        $pdo = $this->pdo();

        $this->runSql(base_path('tests/Fixtures/MariaDb/employee_legacy_fifteen_plus_address.sql'));
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
        self::assertSame('30', (string) $pdo->query(
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
            $this->parallelProfile('parallel-one@example.test', '001099000031'),
            $this->parallelProfile('parallel-two@example.test', '001099000032'),
        );

        self::assertTrue($first['ok'] ?? false, json_encode($first));
        self::assertTrue($second['ok'] ?? false, json_encode($second));
        $codes = [(string) $first['ma_nv'], (string) $second['ma_nv']];
        sort($codes);
        self::assertSame(['NV031', 'NV032'], $codes);
        self::assertSame(32, (int) $this->pdo()->query(
            "SELECT so_da_cap FROM bo_dem_ma_nhan_vien WHERE ten_bo_dem = 'NHAN_VIEN'"
        )->fetchColumn());
        self::assertSame(2, (int) $this->pdo()->query(
            "SELECT COUNT(*) FROM nhan_vien WHERE ma_nv IN ('NV031', 'NV032')"
        )->fetchColumn());
    }

    private function runFreshPair(): void
    {
        $this->runFreshSource('database/tao_bang.sql');
        $this->runFreshSource('database/du_lieu_mau.sql');
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
            'ma_cv' => 4,
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
