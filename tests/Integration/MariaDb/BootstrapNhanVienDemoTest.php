<?php

namespace Tests\Integration\MariaDb;

use App\Console\Commands\BootstrapNhanVienDemo;
use App\Contracts\NhanVienRepositoryContract;
use App\Contracts\NhanVienServiceContract;
use App\Enums\NhanVienRemovalAction;
use App\Models\NhanVien;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\DatabaseManager;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Testing\PendingCommand;
use Mockery;
use PDO;
use RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Process\Process;

final class BootstrapNhanVienDemoTest extends MariaDbTestCase
{
    private array $previousBootstrapEnvironment = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->runSql(base_path('tests/Fixtures/MariaDb/employee_legacy_schema.sql'));
        $this->runSql(base_path('database/sql/employee/2026_08_12_001_schema.sql'));
        $this->runSql(base_path('database/sql/employee/2026_08_12_002_read_routines.sql'));
        $this->runSql(base_path('database/sql/employee/2026_08_12_003_create_routines.sql'));
        $this->runSql(base_path('database/sql/employee/2026_08_12_004_update_routines.sql'));
        $this->runSql(base_path('database/sql/employee/2026_08_12_005_lifecycle_auth_routines.sql'));
        $this->runSql(base_path('database/sql/employee/2026_08_12_006_rbac.sql'));
        $this->installMasterCreateProceduresForFixture();

        $this->rememberEnvironment([
            'MARIADB_TEST_ENABLED',
            'MARIADB_TEST_DATABASE',
            'APP_ENV',
            'DB_URL',
            'DB_SOCKET',
        ]);
        $this->setEnvironment('MARIADB_TEST_ENABLED', '1');
        $this->setEnvironment('MARIADB_TEST_DATABASE', $this->databaseName());
        $this->setEnvironment('APP_ENV', 'testing');
        $this->setEnvironment('DB_URL', '');
        $this->setEnvironment('DB_SOCKET', '');
    }

    protected function tearDown(): void
    {
        foreach ($this->previousBootstrapEnvironment as $name => $value) {
            if ($value['exists']) {
                putenv($name.'='.$value['value']);
            } else {
                putenv($name);
            }
        }

        parent::tearDown();
    }

    public function test_command_is_registered_and_does_not_run_without_explicit_invocation(): void
    {
        $this->assertArrayHasKey('employee:bootstrap-demo', $this->app->make(Kernel::class)->all());

        $this->assertSame(0, (int) $this->pdo()->query('SELECT COUNT(*) FROM nhan_vien')->fetchColumn());
    }

    public function test_success_creates_admin_with_exact_five_permissions_and_no_contract_or_payroll(): void
    {
        $result = $this->runBootstrap();

        $result
            ->assertExitCode(0)
            ->expectsOutputToContain('NV001')
            ->expectsOutputToContain('nhom3@')
            ->doesntExpectOutputToContain('admin@example.test')
            ->doesntExpectOutputToContain('Nguyễn Quản Trị')
            ->doesntExpectOutputToContain('0901234567')
            ->doesntExpectOutputToContain('001200000001')
            ->doesntExpectOutputToContain('Cục CSQLHC')
            ->doesntExpectOutputToContain('1 Đường Kiểm Thử')
            ->doesntExpectOutputToContain('Phường Test')
            ->doesntExpectOutputToContain('Quận Test')
            ->doesntExpectOutputToContain('TP Hồ Chí Minh')
            ->doesntExpectOutputToContain('$2y$');
        $result->execute();

        $this->assertSame(['NV001'], $this->column('SELECT ma_nv FROM nhan_vien ORDER BY ma_nv'));
        $this->assertSame(1, (int) $this->pdo()->query(
            "SELECT COUNT(*) FROM nhan_vien WHERE ma_nv = 'NV001' AND ma_vt <> (
                SELECT ma_vt FROM vai_tro WHERE ky_hieu = 'NHAN_VIEN_MAC_DINH'
            )"
        )->fetchColumn());
        $this->assertSame([
            'NHAN_VIEN_DAT_LAI_MAT_KHAU',
            'NHAN_VIEN_SUA',
            'NHAN_VIEN_TAO',
            'NHAN_VIEN_XEM',
            'NHAN_VIEN_XOA',
        ], $this->column(
            "SELECT q.ky_hieu_quyen
             FROM nhan_vien nv
             JOIN vai_tro_quyen vtq ON vtq.ma_vt = nv.ma_vt
             JOIN quyen q ON q.ma_quyen = vtq.ma_quyen
             WHERE nv.ma_nv = 'NV001'
             ORDER BY q.ky_hieu_quyen"
        ));
        $this->assertSame(0, (int) $this->pdo()->query('SELECT COUNT(*) FROM hop_dong')->fetchColumn());
        $this->assertSame(0, (int) $this->pdo()->query('SELECT COUNT(*) FROM luong')->fetchColumn());
        $this->assertSame(1, (int) $this->pdo()->query(
            "SELECT COUNT(*) FROM dia_chi_nhan_vien WHERE ma_nv = 'NV001'"
        )->fetchColumn());
    }

    public function test_missing_confirmation_and_required_identity_options_fail_before_writes(): void
    {
        $before = $this->snapshot();
        $this->artisan('employee:bootstrap-demo', ['--require-disposable' => true])
            ->assertExitCode(1);
        $this->assertSame($before, $this->snapshot());

        foreach (['--admin-email', '--admin-cccd', '--admin-name', '--department', '--position', '--position-allowance', '--role', '--admin-phone', '--birth-date', '--start-date', '--gender', '--education', '--ethnicity', '--cccd-place', '--address-line', '--ward', '--district', '--province'] as $missing) {
            $options = $this->bootstrapOptions();
            unset($options[$missing]);

            $before = $this->snapshot();
            $this->artisan('employee:bootstrap-demo', $options)->assertExitCode(1);
            $this->assertSame($before, $this->snapshot(), "Missing option {$missing} must not write.");
        }
    }

    public function test_disposable_guard_and_target_mismatch_fail_before_any_write(): void
    {
        foreach ([
            ['MARIADB_TEST_ENABLED', '0'],
            ['MARIADB_TEST_DATABASE', 'quan_ly_nhan_su'],
        ] as [$name, $value]) {
            $this->setEnvironment($name, $value);
            $before = $this->snapshot();
            $this->artisan('employee:bootstrap-demo', $this->bootstrapOptions())->assertExitCode(1);
            $this->assertSame($before, $this->snapshot());
            $this->setEnvironment($name, $name === 'MARIADB_TEST_ENABLED' ? '1' : $this->databaseName());
        }

        config()->set('database.connections.employee_test.driver', 'sqlite');
        $before = $this->snapshot();
        $this->artisan('employee:bootstrap-demo', $this->bootstrapOptions())->assertExitCode(1);
        $this->assertSame($before, $this->snapshot());

        config()->set('database.connections.employee_test.driver', 'mysql');
        config()->set('database.connections.employee_test.database', 'another_database');
        $before = $this->snapshot();
        $this->artisan('employee:bootstrap-demo', $this->bootstrapOptions())->assertExitCode(1);
        $this->assertSame($before, $this->snapshot());

        config()->set('database.connections.employee_test.database', $this->databaseName());
        $this->setEnvironment('DB_URL', 'mysql://not-used');
        $before = $this->snapshot();
        $this->artisan('employee:bootstrap-demo', $this->bootstrapOptions())->assertExitCode(1);
        $this->assertSame($before, $this->snapshot());
        $this->setEnvironment('DB_URL', '');
    }

    public function test_invalid_input_duplicate_identity_and_ambiguous_references_fail_before_mutation(): void
    {
        $invalid = $this->bootstrapOptions(['--position-allowance' => '-1']);
        $before = $this->snapshot();
        $this->artisan('employee:bootstrap-demo', $invalid)->assertExitCode(1);
        $this->assertSame($before, $this->snapshot());

        $this->pdo()->exec(
            "INSERT INTO phong_ban (ten_pb) VALUES ('Demo Department')"
        );
        $this->pdo()->exec(
            "INSERT INTO chuc_vu (ten_cv, he_so_phu_cap) VALUES ('Demo Position', 0.20)"
        );
        $this->pdo()->exec(
            "INSERT INTO nhan_vien (
                ma_nv, ho_ten, ngay_sinh, gioi_tinh, sdt, email, ngay_vao_lam,
                ma_pb, ma_cv, dan_toc, cccd, noi_cap_cccd, hoc_van, ma_tt, mat_khau, ma_vt
             ) VALUES (
                'NV001', 'Existing', '1990-01-01', 1, '0901234567', 'admin@example.test', '2020-01-01',
                1, 1, 'Kinh', '001200000001', 'Cục CSQLHC', 'Đại học',
                (SELECT ma_tt FROM trang_thai_lam_viec WHERE ky_hieu = 'DANG_LAM'),
                'hash', (SELECT ma_vt FROM vai_tro WHERE ky_hieu = 'NHAN_VIEN_MAC_DINH')
             )"
        );

        $this->artisan('employee:bootstrap-demo', $this->bootstrapOptions())->assertExitCode(1);
        $this->assertSame(1, (int) $this->pdo()->query('SELECT COUNT(*) FROM nhan_vien')->fetchColumn());

        $this->pdo()->exec("INSERT INTO vai_tro (ten_vt, mo_ta) VALUES ('Admin Demo', NULL)");
        $this->pdo()->exec("INSERT INTO vai_tro (ten_vt, mo_ta) VALUES ('Admin Demo', NULL)");
        $this->artisan('employee:bootstrap-demo', $this->bootstrapOptions())->assertExitCode(1);
        $this->assertSame(2, (int) $this->pdo()->query("SELECT COUNT(*) FROM vai_tro WHERE ten_vt = 'Admin Demo'")->fetchColumn());
    }

    public function test_baseline_role_name_is_rejected_before_mutation(): void
    {
        $before = $this->snapshot();
        $this->artisan('employee:bootstrap-demo', $this->bootstrapOptions([
            '--role' => ' Nhân viên mặc định ',
        ]))->assertExitCode(1);

        $this->assertSame($before, $this->snapshot());
    }

    public function test_reusing_master_rows_and_role_is_idempotent_without_duplicate_permissions(): void
    {
        $this->runBootstrap([
            '--department' => 'Reusable Department',
            '--position' => 'Reusable Position',
            '--role' => 'Reusable Admin',
            '--admin-email' => 'first@example.test',
            '--admin-cccd' => '001200000001',
        ])->assertExitCode(0);

        $this->runBootstrap([
            '--department' => ' Reusable Department ',
            '--position' => ' Reusable Position ',
            '--role' => ' Reusable Admin ',
            '--admin-name' => 'Nguyễn Quản Trị Hai',
            '--admin-email' => 'second@example.test',
            '--admin-phone' => '0901234568',
            '--admin-cccd' => '001200000002',
        ])->assertExitCode(0);

        $this->assertSame(1, (int) $this->pdo()->query(
            "SELECT COUNT(*) FROM phong_ban WHERE ten_pb = 'Reusable Department'"
        )->fetchColumn());
        $this->assertSame(1, (int) $this->pdo()->query(
            "SELECT COUNT(*) FROM chuc_vu WHERE ten_cv = 'Reusable Position'"
        )->fetchColumn());
        $role = (int) $this->pdo()->query(
            "SELECT ma_vt FROM vai_tro WHERE ten_vt = 'Reusable Admin'"
        )->fetchColumn();
        $this->assertSame(5, (int) $this->pdo()->query(
            "SELECT COUNT(*) FROM vai_tro_quyen WHERE ma_vt = {$role}"
        )->fetchColumn());
    }

    public function test_failure_after_internal_assignment_rolls_back_new_rows_but_preserves_reused_rows(): void
    {
        $repository = new FaultInjectingNhanVienRepository(
            $this->app->make(NhanVienRepositoryContract::class),
        );
        $this->app->instance(NhanVienRepositoryContract::class, $repository);

        $this->runBootstrap([
            '--department' => 'Pre-existing Department',
            '--position' => 'Pre-existing Position',
            '--role' => 'Pre-existing Role',
            '--admin-email' => 'existing@example.test',
            '--admin-cccd' => '001200000001',
        ])->assertExitCode(0);
        $beforeFailure = $this->snapshot();
        $repository->throwAfterAssignment = true;

        $this->runBootstrap([
            '--admin-name' => 'Nguyễn Quản Trị Lỗi',
            '--admin-email' => 'rollback@example.test',
            '--admin-phone' => '0901234568',
            '--admin-cccd' => '001200000002',
            '--department' => 'New Department',
            '--position' => 'New Position',
            '--role' => 'New Role',
        ])->assertExitCode(1);

        $this->assertSame($beforeFailure, $this->snapshot());

        $this->assertSame(1, (int) $this->pdo()->query("SELECT COUNT(*) FROM nhan_vien WHERE email = 'existing@example.test'")->fetchColumn());
        $this->assertSame(0, (int) $this->pdo()->query("SELECT COUNT(*) FROM nhan_vien WHERE email = 'rollback@example.test'")->fetchColumn());
        $this->assertSame(0, (int) $this->pdo()->query("SELECT COUNT(*) FROM phong_ban WHERE ten_pb = 'New Department'")->fetchColumn());
        $this->assertSame(0, (int) $this->pdo()->query("SELECT COUNT(*) FROM chuc_vu WHERE ten_cv = 'New Position'")->fetchColumn());
        $this->assertSame(0, (int) $this->pdo()->query("SELECT COUNT(*) FROM vai_tro WHERE ten_vt = 'New Role'")->fetchColumn());
        $this->assertSame(1, (int) $this->pdo()->query("SELECT so_da_cap FROM bo_dem_ma_nhan_vien WHERE ten_bo_dem = 'NHAN_VIEN'")->fetchColumn());
    }

    public function test_existing_role_with_extra_legacy_mapping_fails_closed_without_changes(): void
    {
        $this->pdo()->exec("INSERT INTO vai_tro (ten_vt, mo_ta) VALUES ('Legacy Admin', NULL)");
        $role = (int) $this->pdo()->lastInsertId();
        $this->pdo()->exec("INSERT INTO quyen (ma_quyen, ky_hieu_quyen, ten_quyen, module) VALUES (999, 'LEGACY_EXTRA', 'Legacy', 'legacy')");
        $this->pdo()->exec("INSERT INTO vai_tro_quyen (ma_vt, ma_quyen) VALUES ({$role}, 999)");

        $before = $this->snapshot();
        $this->runBootstrap(['--role' => 'Legacy Admin'])->assertExitCode(1);

        $this->assertSame($before, $this->snapshot());
    }

    public function test_existing_role_with_orphan_mapping_fails_closed_without_changes(): void
    {
        $this->pdo()->exec("INSERT INTO vai_tro (ten_vt, mo_ta) VALUES ('Orphan Admin', NULL)");
        $role = (int) $this->pdo()->lastInsertId();
        $this->pdo()->exec('ALTER TABLE vai_tro_quyen DROP FOREIGN KEY fk_vai_tro_quyen_quyen');
        $this->pdo()->exec("INSERT INTO vai_tro_quyen (ma_vt, ma_quyen) VALUES ({$role}, 999)");

        $before = $this->snapshot();
        $this->runBootstrap(['--role' => 'Orphan Admin'])->assertExitCode(1);

        $this->assertSame($before, $this->snapshot());
    }

    public function test_actual_database_mismatch_fails_before_write_and_restores_guarded_connection(): void
    {
        $before = $this->snapshot();
        $connection = $this->app->make(DatabaseManager::class)->connection('employee_test');
        $connection->getPdo()->exec('USE information_schema');

        $this->runBootstrap()->assertExitCode(1);

        $this->app->make(DatabaseManager::class)->disconnect('employee_test');
        $this->app->make(DatabaseManager::class)->purge('employee_test');
        $this->assertSame($before, $this->snapshot());
    }

    public function test_department_ambiguity_is_rejected_without_mutation(): void
    {
        $this->pdo()->exec("INSERT INTO phong_ban (ten_pb) VALUES ('Demo Department'), ('Demo Department')");
        $before = $this->snapshot();
        $this->runBootstrap()->assertExitCode(1);
        $this->assertSame($before, $this->snapshot());
    }

    public function test_position_ambiguity_is_rejected_without_mutation(): void
    {
        $this->pdo()->exec("INSERT INTO chuc_vu (ten_cv, he_so_phu_cap) VALUES ('Demo Position', 0.20), ('Demo Position', 0.20)");
        $before = $this->snapshot();
        $this->runBootstrap()->assertExitCode(1);
        $this->assertSame($before, $this->snapshot());
    }

    public function test_role_ambiguity_is_rejected_without_mutation(): void
    {
        $this->pdo()->exec("INSERT INTO vai_tro (ten_vt, mo_ta) VALUES ('Demo Administrator', NULL), ('Demo Administrator', NULL)");
        $before = $this->snapshot();
        $this->runBootstrap()->assertExitCode(1);
        $this->assertSame($before, $this->snapshot());
    }

    public function test_status_ambiguity_is_rejected_without_mutation(): void
    {
        $this->pdo()->exec('ALTER TABLE trang_thai_lam_viec DROP INDEX uq_trang_thai_lam_viec_ky_hieu');
        $this->pdo()->exec("INSERT INTO trang_thai_lam_viec (ten_tt, ky_hieu) SELECT ten_tt, ky_hieu FROM trang_thai_lam_viec WHERE ky_hieu = 'DANG_LAM'");
        $before = $this->snapshot();
        $this->runBootstrap()->assertExitCode(1);
        $this->assertSame($before, $this->snapshot());
    }

    public function test_permission_ambiguity_is_rejected_without_mutation(): void
    {
        $this->pdo()->exec('ALTER TABLE quyen DROP INDEX uq_quyen_ky_hieu_quyen');
        $this->pdo()->exec("INSERT INTO quyen (ma_quyen, ky_hieu_quyen, ten_quyen, module) SELECT 998, ky_hieu_quyen, ten_quyen, module FROM quyen WHERE ky_hieu_quyen = 'NHAN_VIEN_XEM'");
        $before = $this->snapshot();
        $this->runBootstrap()->assertExitCode(1);
        $this->assertSame($before, $this->snapshot());
    }

    public function test_duplicate_email_and_cccd_are_checked_independently_before_mutation(): void
    {
        $this->pdo()->exec("INSERT INTO phong_ban (ten_pb) VALUES ('Demo Department')");
        $this->pdo()->exec("INSERT INTO chuc_vu (ten_cv, he_so_phu_cap) VALUES ('Demo Position', 0.20)");
        $this->pdo()->exec(
            "INSERT INTO nhan_vien (
                ma_nv, ho_ten, ngay_sinh, gioi_tinh, sdt, email, ngay_vao_lam,
                ma_pb, ma_cv, dan_toc, cccd, noi_cap_cccd, hoc_van, ma_tt, mat_khau, ma_vt
             ) VALUES (
                'NV001', 'Existing', '1990-01-01', 1, '0901234567', 'existing@example.test', '2020-01-01',
                1, 1, 'Kinh', '001200000001', 'Cục CSQLHC', 'Đại học',
                (SELECT ma_tt FROM trang_thai_lam_viec WHERE ky_hieu = 'DANG_LAM'),
                'hash', (SELECT ma_vt FROM vai_tro WHERE ky_hieu = 'NHAN_VIEN_MAC_DINH')
             )"
        );

        $before = $this->snapshot();
        $this->runBootstrap([
            '--admin-email' => 'existing@example.test',
            '--admin-cccd' => '001200000002',
        ])->assertExitCode(1);
        $this->assertSame($before, $this->snapshot());

        $this->pdo()->exec("UPDATE nhan_vien SET email = 'other@example.test', cccd = '001200000002' WHERE ma_nv = 'NV001'");
        $before = $this->snapshot();
        $this->runBootstrap([
            '--admin-email' => 'unique@example.test',
            '--admin-cccd' => '001200000002',
        ])->assertExitCode(1);
        $this->assertSame($before, $this->snapshot());
    }

    public function test_service_payload_delegates_real_service_without_role_override(): void
    {
        $capturing = new CapturingNhanVienService($this->app->make(NhanVienServiceContract::class));
        $this->app->instance(NhanVienServiceContract::class, $capturing);

        $result = $this->runBootstrap();
        $exitCode = $result->execute();

        $this->assertSame(0, $exitCode);
        $this->assertArrayNotHasKey('ma_vt', $capturing->lastPayload);
        $this->assertNotEmpty($capturing->lastPayload);
    }

    public function test_validation_and_environment_guards_fail_before_database_manager_resolution(): void
    {
        $commandOptions = $this->bootstrapOptions();
        $required = [
            'department', 'position', 'position-allowance', 'role', 'admin-name', 'admin-email',
            'admin-phone', 'admin-cccd', 'birth-date', 'start-date', 'gender', 'education',
            'ethnicity', 'cccd-place', 'address-line', 'ward', 'district', 'province',
        ];
        $command = new BootstrapNhanVienDemo(
            Mockery::mock(DatabaseManager::class),
            Mockery::mock(NhanVienServiceContract::class),
            Mockery::mock(NhanVienRepositoryContract::class),
        );
        $input = new ArrayInput(['--yes' => true, '--require-disposable' => true]);
        $input->bind($command->getDefinition());
        foreach ($required as $name) {
            $this->assertNull($input->getOption($name), "{$name} must default to null.");
        }

        $previousEnv = config('app.env');
        try {
            config()->set('app.env', 'production');
            $database = Mockery::mock(DatabaseManager::class);
            $database->shouldNotReceive('getDefaultConnection');
            $database->shouldNotReceive('connection');
            $tester = $this->pureTester($database);
            $this->assertSame(1, $tester->execute($commandOptions));

            config()->set('app.env', 'testing');
            $database = Mockery::mock(DatabaseManager::class);
            $database->shouldNotReceive('getDefaultConnection');
            $database->shouldNotReceive('connection');
            $tester = $this->pureTester($database);
            $missingConfirmation = $commandOptions;
            unset($missingConfirmation['--yes']);
            $this->assertSame(1, $tester->execute($missingConfirmation));

            $database = Mockery::mock(DatabaseManager::class);
            $database->shouldNotReceive('getDefaultConnection');
            $database->shouldNotReceive('connection');
            $tester = $this->pureTester($database);
            $missingDisposable = $commandOptions;
            unset($missingDisposable['--require-disposable']);
            $this->assertSame(1, $tester->execute($missingDisposable));

            foreach ([
                ['MARIADB_TEST_ENABLED', null],
                ['MARIADB_TEST_ENABLED', 'malformed'],
                ['MARIADB_TEST_DATABASE', null],
                ['MARIADB_TEST_DATABASE', 'quan_ly_nhan_su'],
                ['MARIADB_TEST_DATABASE', 'not_a_safe_database'],
            ] as [$name, $value]) {
                $database = Mockery::mock(DatabaseManager::class);
                $database->shouldNotReceive('getDefaultConnection');
                $database->shouldNotReceive('connection');
                $tester = $this->pureTester($database);
                $old = getenv($name);
                if ($value === null) {
                    putenv($name);
                } else {
                    putenv($name.'='.$value);
                }
                try {
                    $this->assertSame(1, $tester->execute($commandOptions));
                } finally {
                    if ($old === false) {
                        putenv($name);
                    } else {
                        putenv($name.'='.$old);
                    }
                }
            }
        } finally {
            config()->set('app.env', $previousEnv);
        }
    }

    public function test_baseline_row_lock_serializes_two_real_artisan_processes_and_reuses_masters(): void
    {
        $pdo = $this->pdo();
        $parentConnectionId = (int) $pdo->query('SELECT CONNECTION_ID()')->fetchColumn();
        $pdo->beginTransaction();
        $lockStatement = $pdo->prepare("SELECT ma_vt FROM vai_tro WHERE ky_hieu = 'NHAN_VIEN_MAC_DINH' FOR UPDATE");
        $lockStatement->execute();
        $baselineRole = (int) $lockStatement->fetchColumn();
        $lockStatement->closeCursor();
        $this->assertTrue($pdo->inTransaction());
        $this->assertGreaterThan(0, $parentConnectionId);
        $this->assertGreaterThan(0, $baselineRole);

        $options = [
            '--department' => 'Concurrent Department',
            '--position' => 'Concurrent Position',
            '--position-allowance' => '0.20',
            '--role' => 'Concurrent Administrator',
            '--admin-name' => 'Concurrent One',
            '--admin-email' => 'concurrent.one@example.test',
            '--admin-phone' => '0901234567',
            '--admin-cccd' => '001200000011',
            '--birth-date' => '1990-01-01',
            '--start-date' => '2026-08-12',
            '--gender' => '1',
            '--education' => 'Đại học',
            '--ethnicity' => 'Kinh',
            '--cccd-place' => 'Cục CSQLHC',
            '--address-line' => '1 Đường Kiểm Thử',
            '--ward' => 'Phường Test',
            '--district' => 'Quận Test',
            '--province' => 'TP Hồ Chí Minh',
            '--yes' => true,
            '--require-disposable' => true,
        ];
        $second = $options;
        $second['--admin-name'] = 'Concurrent Two';
        $second['--admin-email'] = 'concurrent.two@example.test';
        $second['--admin-phone'] = '0901234568';
        $second['--admin-cccd'] = '001200000012';

        $children = [];
        try {
            $children[] = $this->startBootstrapProcess($options);
            $children[] = $this->startBootstrapProcess($second);
            foreach ($children as $child) {
                $this->assertTrue($child->isRunning(), $child->getErrorOutput());
            }
            $waitingQueries = $this->waitForBaselineRowQueries($parentConnectionId);
            $this->assertCount(2, $waitingQueries);
            $this->assertCount(2, array_unique(array_column($waitingQueries, 'ID')));
            $this->assertContainsOnly('string', array_column($waitingQueries, 'COMMAND'));

            $pdo->commit();
            foreach ($children as $child) {
                $child->wait();
                $this->assertSame(0, $child->getExitCode());
            }
        } finally {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            foreach ($children as $child) {
                if ($child->isRunning()) {
                    $child->stop(1);
                }
            }
        }

        $this->assertSame(1, (int) $pdo->query("SELECT COUNT(*) FROM phong_ban WHERE ten_pb = 'Concurrent Department'")->fetchColumn());
        $this->assertSame(1, (int) $pdo->query("SELECT COUNT(*) FROM chuc_vu WHERE ten_cv = 'Concurrent Position'")->fetchColumn());
        $role = (int) $pdo->query("SELECT ma_vt FROM vai_tro WHERE ten_vt = 'Concurrent Administrator'")->fetchColumn();
        $this->assertSame(5, (int) $pdo->query("SELECT COUNT(*) FROM vai_tro_quyen WHERE ma_vt = {$role}")->fetchColumn());
        $this->assertSame(2, (int) $pdo->query("SELECT COUNT(*) FROM nhan_vien WHERE email LIKE 'concurrent.%@example.test'")->fetchColumn());
    }

    private function runBootstrap(array $overrides = []): PendingCommand
    {
        return $this->artisan('employee:bootstrap-demo', $this->bootstrapOptions($overrides));
    }

    private function bootstrapOptions(array $overrides = []): array
    {
        return array_replace([
            '--department' => 'Demo Department',
            '--position' => 'Demo Position',
            '--position-allowance' => '0.20',
            '--role' => 'Demo Administrator',
            '--admin-name' => 'Nguyễn Quản Trị',
            '--admin-email' => 'admin@example.test',
            '--admin-phone' => '0901234567',
            '--admin-cccd' => '001200000001',
            '--birth-date' => '1990-01-01',
            '--start-date' => '2026-08-12',
            '--gender' => '1',
            '--education' => 'Đại học',
            '--ethnicity' => 'Kinh',
            '--cccd-place' => 'Cục CSQLHC',
            '--address-line' => '1 Đường Kiểm Thử',
            '--ward' => 'Phường Test',
            '--district' => 'Quận Test',
            '--province' => 'TP Hồ Chí Minh',
            '--yes' => true,
            '--require-disposable' => true,
        ], $overrides);
    }

    private function pureTester(DatabaseManager $database): CommandTester
    {
        $command = new BootstrapNhanVienDemo(
            $database,
            Mockery::mock(NhanVienServiceContract::class),
            Mockery::mock(NhanVienRepositoryContract::class),
        );
        $command->setLaravel($this->app);

        return new CommandTester($command);
    }

    /** @param array<string, mixed> $options */
    private function startBootstrapProcess(array $options): Process
    {
        $arguments = [PHP_BINARY, base_path('artisan'), 'employee:bootstrap-demo'];
        foreach ($options as $name => $value) {
            $option = ltrim($name, '-');
            $arguments[] = $value === true ? '--'.$option : '--'.$option.'='.(string) $value;
        }

        $environment = getenv();
        if (! is_array($environment)) {
            $environment = [];
        }
        $configured = config('database.connections.employee_test');
        $environment['APP_ENV'] = 'testing';
        $environment['DB_CONNECTION'] = 'mysql';
        $environment['DB_HOST'] = (string) ($configured['host'] ?? '');
        $environment['DB_PORT'] = (string) ($configured['port'] ?? '');
        $environment['DB_DATABASE'] = $this->databaseName();
        $environment['DB_USERNAME'] = (string) ($configured['username'] ?? '');
        $environment['DB_PASSWORD'] = (string) ($configured['password'] ?? '');
        $environment['DB_URL'] = '';
        $environment['DB_SOCKET'] = '';
        $environment['MARIADB_TEST_ENABLED'] = '1';
        $environment['MARIADB_TEST_DATABASE'] = $this->databaseName();
        $environment['MARIADB_TEST_HOST'] = (string) ($configured['host'] ?? '');
        $environment['MARIADB_TEST_PORT'] = (string) ($configured['port'] ?? '');
        $environment['MARIADB_TEST_USERNAME'] = (string) ($configured['username'] ?? '');
        $environment['MARIADB_TEST_PASSWORD'] = (string) ($configured['password'] ?? '');

        $process = new Process($arguments, base_path(), $environment);
        $process->setTimeout(15);
        $process->start();

        return $process;
    }

    /** @return list<array{ID: string, COMMAND: string, INFO: string}> */
    private function waitForBaselineRowQueries(int $parentConnectionId): array
    {
        $configured = config('database.connections.employee_test');
        $metadata = new PDO(
            sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                $configured['host'],
                $configured['port'],
                $this->databaseName(),
            ),
            $configured['username'],
            $configured['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );
        $sql = <<<'SQL'
SELECT ID, COMMAND, INFO
FROM information_schema.PROCESSLIST
WHERE DB = DATABASE()
  AND ID <> ?
  AND COMMAND IN ('Query', 'Execute')
  AND INFO IS NOT NULL
SQL;

        $deadline = microtime(true) + 15;
        do {
            $statement = $metadata->prepare($sql);
            $statement->execute([$parentConnectionId]);
            $rows = array_values(array_filter(
                $statement->fetchAll(PDO::FETCH_ASSOC),
                fn (array $row): bool => $this->isExactBaselineLockQuery((string) $row['INFO']),
            ));
            $threadIds = array_unique(array_column($rows, 'ID'));
            if (count($threadIds) >= 2) {
                return $rows;
            }
            usleep(10_000);
        } while (microtime(true) < $deadline);

        $this->fail('MariaDB did not observe two distinct child baseline lock queries in PROCESSLIST.');

        return [];
    }

    private function isExactBaselineLockQuery(string $info): bool
    {
        $normalized = strtoupper((string) preg_replace('/\s+/', ' ', trim($info)));

        return in_array($normalized, [
            'SELECT MA_VT, TEN_VT FROM VAI_TRO WHERE BINARY KY_HIEU = BINARY ? FOR UPDATE',
            "SELECT MA_VT, TEN_VT FROM VAI_TRO WHERE BINARY KY_HIEU = BINARY 'NHAN_VIEN_MAC_DINH' FOR UPDATE",
        ], true);
    }

    private function snapshot(): array
    {
        $tables = [
            'employees' => 'SELECT * FROM nhan_vien ORDER BY ma_nv',
            'addresses' => 'SELECT * FROM dia_chi_nhan_vien ORDER BY ma_nv',
            'counter' => 'SELECT * FROM bo_dem_ma_nhan_vien ORDER BY ten_bo_dem',
            'departments' => 'SELECT * FROM phong_ban ORDER BY ma_pb',
            'positions' => 'SELECT * FROM chuc_vu ORDER BY ma_cv',
            'roles' => 'SELECT * FROM vai_tro ORDER BY ma_vt',
            'mappings' => 'SELECT * FROM vai_tro_quyen ORDER BY ma_vt, ma_quyen',
            'statuses' => 'SELECT * FROM trang_thai_lam_viec ORDER BY ma_tt',
            'permissions' => 'SELECT * FROM quyen ORDER BY ma_quyen',
            'contracts' => 'SELECT * FROM hop_dong ORDER BY ma_hd',
            'payroll' => 'SELECT * FROM luong ORDER BY ma_luong',
        ];

        $snapshot = [];
        foreach ($tables as $name => $sql) {
            $snapshot[$name] = $this->pdo()->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        }

        return $snapshot;
    }

    private function installMasterCreateProceduresForFixture(): void
    {
        $this->pdo()->exec(<<<'SQL'
            CREATE PROCEDURE sp_phong_ban_them(IN p_ten_pb NVARCHAR(100))
            BEGIN
                INSERT INTO phong_ban (ten_pb) VALUES (TRIM(p_ten_pb));
            END
            SQL);
        $this->pdo()->exec(<<<'SQL'
            CREATE PROCEDURE sp_chuc_vu_them(IN p_ten_cv NVARCHAR(100), IN p_he_so_phu_cap DECIMAL(18,2))
            BEGIN
                INSERT INTO chuc_vu (ten_cv, he_so_phu_cap) VALUES (TRIM(p_ten_cv), p_he_so_phu_cap);
            END
            SQL);
        $this->pdo()->exec(<<<'SQL'
            CREATE PROCEDURE sp_vai_tro_them(IN p_ten_vt NVARCHAR(100), IN p_mo_ta NVARCHAR(255))
            BEGIN
                INSERT INTO vai_tro (ten_vt, mo_ta) VALUES (TRIM(p_ten_vt), p_mo_ta);
            END
            SQL);
    }

    private function column(string $sql): array
    {
        return array_map(
            static fn (array $row): string => (string) array_values($row)[0],
            $this->pdo()->query($sql)->fetchAll(PDO::FETCH_ASSOC),
        );
    }

    private function rememberEnvironment(array $names): void
    {
        foreach ($names as $name) {
            $value = getenv($name);
            $this->previousBootstrapEnvironment[$name] = [
                'exists' => $value !== false,
                'value' => $value === false ? '' : $value,
            ];
        }
    }

    private function setEnvironment(string $name, string $value): void
    {
        putenv($name.'='.$value);
    }
}

final class FaultInjectingNhanVienRepository implements NhanVienRepositoryContract
{
    public bool $throwAfterAssignment = false;

    public function __construct(private NhanVienRepositoryContract $inner) {}

    public function paginate(array $filters): LengthAwarePaginator
    {
        return $this->inner->paginate($filters);
    }

    public function paginateAttendance(array $filters): LengthAwarePaginator
    {
        return $this->inner->paginateAttendance($filters);
    }

    public function find(string $maNv): ?object
    {
        return $this->inner->find($maNv);
    }

    public function create(array $profile, string $passwordHash, ?string $avatarPath): string
    {
        return $this->inner->create($profile, $passwordHash, $avatarPath);
    }

    public function update(string $maNv, array $profile): void
    {
        $this->inner->update($maNv, $profile);
    }

    public function replaceAvatarPath(string $maNv, ?string $newPath): ?string
    {
        return $this->inner->replaceAvatarPath($maNv, $newPath);
    }

    public function upsertAddress(string $maNv, array $address): void
    {
        $this->inner->upsertAddress($maNv, $address);
    }

    public function removeOrTerminate(string $maNv, CarbonImmutable $date): array
    {
        return $this->inner->removeOrTerminate($maNv, $date);
    }

    public function resetPasswordHash(string $maNv, string $hash): void
    {
        $this->inner->resetPasswordHash($maNv, $hash);
    }

    public function rehashAuthenticatedPassword(string $maNv, string $currentHash, string $newHash): void
    {
        $this->inner->rehashAuthenticatedPassword($maNv, $currentHash, $newHash);
    }

    public function findAccountByIdentifier(string $identifier): ?NhanVien
    {
        return $this->inner->findAccountByIdentifier($identifier);
    }

    public function permissionSymbols(string $maNv): array
    {
        return $this->inner->permissionSymbols($maNv);
    }

    public function assignRoleForBootstrap(string $maNv, int $maVt): void
    {
        $this->inner->assignRoleForBootstrap($maNv, $maVt);
        if ($this->throwAfterAssignment) {
            throw new RuntimeException('fault after assignment');
        }
    }

    public function lookups(): array
    {
        return $this->inner->lookups();
    }
}

final class CapturingNhanVienService implements NhanVienServiceContract
{
    public array $lastPayload = [];

    public function __construct(private NhanVienServiceContract $inner) {}

    public function paginate(array $filters): LengthAwarePaginator
    {
        return $this->inner->paginate($filters);
    }

    public function paginateForAttendance(array $filters): LengthAwarePaginator
    {
        return $this->inner->paginateForAttendance($filters);
    }

    public function findOrFail(string $maNv): object
    {
        return $this->inner->findOrFail($maNv);
    }

    public function create(array $validated): string
    {
        $this->lastPayload = $validated;

        return $this->inner->create($validated);
    }

    public function update(string $maNv, array $validated): object
    {
        return $this->inner->update($maNv, $validated);
    }

    public function removeOrTerminate(string $maNv): NhanVienRemovalAction
    {
        return $this->inner->removeOrTerminate($maNv);
    }

    public function resetPassword(string $maNv): void
    {
        $this->inner->resetPassword($maNv);
    }

    public function lookups(): array
    {
        return $this->inner->lookups();
    }
}
