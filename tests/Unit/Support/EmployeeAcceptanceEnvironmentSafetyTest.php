<?php

namespace Tests\Unit\Support;

use App\Support\DisposableMariaDbGuard;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\EmployeeAcceptanceEnvironment;

final class EmployeeAcceptanceEnvironmentSafetyTest extends TestCase
{
    public function test_cli_exposes_only_the_six_normative_actions(): void
    {
        $this->assertSame(
            ['create', 'verify-runtime', 'seed-roles', 'assign-role', 'cleanup-uploads', 'drop'],
            EmployeeAcceptanceEnvironment::actions(),
        );

        $this->expectException(RuntimeException::class);
        EmployeeAcceptanceEnvironment::assertAction('arbitrary');
    }

    public function test_run_id_and_database_name_are_exactly_guarded(): void
    {
        $database = 'quan_ly_nhan_su_employee_test_a1b2c3d4e5f6';

        $this->assertSame('a1b2c3d4e5f6', EmployeeAcceptanceEnvironment::runIdFromDatabase($database));
        $this->assertSame(
            $database,
            EmployeeAcceptanceEnvironment::databaseNameForRunId('a1b2c3d4e5f6'),
        );

        $this->expectException(RuntimeException::class);
        EmployeeAcceptanceEnvironment::runIdFromDatabase('quan_ly_nhan_su');
    }

    public function test_synthetic_fixture_is_deterministic_valid_and_unique_per_run(): void
    {
        $first = EmployeeAcceptanceEnvironment::syntheticFixture('a1b2c3d4e5f6');
        $second = EmployeeAcceptanceEnvironment::syntheticFixture('f6e5d4c3b2a1');

        $this->assertSame('NV001', $first['expected_ma_nv']);
        $this->assertMatchesRegularExpression('/\Aadmin-[a-f0-9]{12}@example\.test\z/', $first['admin_email']);
        $this->assertMatchesRegularExpression('/\A09[0-9]{8}\z/', $first['admin_phone']);
        $this->assertMatchesRegularExpression('/\A[0-9]{12}\z/', $first['admin_cccd']);
        $this->assertArrayNotHasKey('password', $first);
        $this->assertArrayNotHasKey('mat_khau', $first);
        $this->assertNotSame($first['admin_email'], $second['admin_email']);
        $this->assertNotSame($first['admin_phone'], $second['admin_phone']);
        $this->assertNotSame($first['admin_cccd'], $second['admin_cccd']);
    }

    public function test_upload_path_is_recomputed_from_guarded_database_and_stays_under_acceptance_root(): void
    {
        $repo = dirname(__DIR__, 3);
        $path = EmployeeAcceptanceEnvironment::uploadDirectory(
            $repo,
            'quan_ly_nhan_su_employee_test_a1b2c3d4e5f6',
        );

        $expected = $repo.'\\storage\\app\\public\\nhan-vien\\acceptance\\a1b2c3d4e5f6\\avatars';
        $this->assertSame(
            strtolower(str_replace('/', DIRECTORY_SEPARATOR, $expected)),
            strtolower(str_replace('/', DIRECTORY_SEPARATOR, $path)),
        );
        $this->assertStringContainsString('storage'.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'public', $path);

        $this->expectException(RuntimeException::class);
        EmployeeAcceptanceEnvironment::uploadDirectory($repo, 'quan_ly_nhan_su_employee_test_../../etc');
    }

    public function test_allowlisted_errors_never_echo_credentials_or_absolute_sensitive_paths(): void
    {
        $message = EmployeeAcceptanceEnvironment::allowlistedError(
            'CREATE_FAILED',
            'mysql://root:super-secret@127.0.0.1:3306/quan_ly_nhan_su_employee_test_a1b2c3d4e5f6',
        );

        $this->assertSame(['ok' => false, 'error' => 'CREATE_FAILED'], $message);
        $encoded = json_encode($message, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('super-secret', $encoded);
        $this->assertStringNotContainsString('mysql://', $encoded);
        $this->assertStringNotContainsString(dirname(__DIR__, 3), $encoded);
    }

    public function test_database_name_method_reuses_the_shared_guard_contract(): void
    {
        EmployeeAcceptanceEnvironment::assertDatabaseName('quan_ly_nhan_su_employee_test_a1b2c3d4e5f6');
        DisposableMariaDbGuard::assertSafeDatabaseName('quan_ly_nhan_su_employee_test_a1b2c3d4e5f6');
        $this->addToAssertionCount(1);
    }

    public function test_native_upload_cleanup_removes_only_the_disposable_tree(): void
    {
        if (! extension_loaded('FFI')) {
            $this->markTestSkipped('Native no-follow leases require the FFI extension.');
        }

        $repo = dirname(__DIR__, 3);
        $database = EmployeeAcceptanceEnvironment::databaseNameForRunId(bin2hex(random_bytes(6)));
        $runId = EmployeeAcceptanceEnvironment::runIdFromDatabase($database);
        $target = EmployeeAcceptanceEnvironment::uploadDirectory($repo, $database);
        $outside = $repo.'\storage\app\public\nhan-vien\acceptance-unit-outside-'.$runId;
        $nested = $target.DIRECTORY_SEPARATOR.'nested';
        $nestedFile = $nested.DIRECTORY_SEPARATOR.'orphan.txt';
        $sentinel = $outside.DIRECTORY_SEPARATOR.'sentinel.txt';

        if (! is_dir($target) && ! mkdir($target, 0777, true) && ! is_dir($target)) {
            $this->fail('Unable to create the exact disposable upload tree.');
        }
        mkdir($nested, 0777, true);
        file_put_contents($nestedFile, 'owned');
        mkdir($outside, 0777, true);
        file_put_contents($sentinel, 'outside');

        try {
            $method = (new \ReflectionClass(EmployeeAcceptanceEnvironment::class))->getMethod('removeOwnedUploads');
            $method->invoke(null, $repo, $database);

            $this->assertDirectoryDoesNotExist($target);
            $this->assertFileExists($sentinel);
            $this->assertSame('outside', file_get_contents($sentinel));
        } finally {
            if (is_file($nestedFile)) {
                unlink($nestedFile);
            }
            if (is_dir($nested)) {
                rmdir($nested);
            }
            if (is_dir($target)) {
                rmdir($target);
            }
            if (is_file($sentinel)) {
                unlink($sentinel);
            }
            if (is_dir($outside)) {
                rmdir($outside);
            }
            $runRoot = dirname($target);
            if (is_dir($runRoot) && count(scandir($runRoot) ?: []) === 2) {
                rmdir($runRoot);
            }
        }
    }

    public function test_power_shell_harness_has_exclusive_state_and_identity_cleanup_guards(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2).'/Support/employee-acceptance.ps1');
        $this->assertIsString($source);
        $this->assertStringContainsString('[IO.FileMode]::CreateNew', $source);
        $this->assertStringContainsString('ReparsePoint', $source);
        $this->assertStringContainsString('Test-ProcessIdentity', $source);
        $this->assertStringContainsString('-WindowStyle Hidden', $source);
        $this->assertStringNotContainsString('Remove-Item -Recurse', $source);
        $this->assertStringNotContainsString('Remove-Item *', $source);
        $this->assertStringContainsString('employee-acceptance(?:-[a-f0-9]+)?', $source);
    }

    public function test_state_claim_read_and_publish_use_relative_native_handles_without_path_fallback(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2).'/Support/employee-acceptance.ps1');
        $this->assertIsString($source);
        $this->assertStringContainsString('CreateNewRelative', $source);
        $this->assertStringContainsString('CreateStateRelative', $source);
        $this->assertStringContainsString('OpenStateRelative', $source);
        $this->assertStringContainsString('OpenStateVerifyRelative', $source);
        $this->assertStringContainsString('CreateInvocationLockRelative', $source);
        $this->assertStringContainsString('OpenRelative', $source);
        $this->assertStringContainsString('OpenForDelete', $source);
        $this->assertStringContainsString('NtCreateFile', $source);
        $this->assertStringContainsString('OBJ_DONT_REPARSE', $source);
        $this->assertStringContainsString('FILE_CREATE', $source);
        $this->assertStringContainsString('FILE_SHARE_NONE', $source);
        $this->assertStringContainsString('STATE_SHARE_ACCESS', $source);
        $this->assertStringContainsString('MarkDeleteOnClose', $source);
        $this->assertStringContainsString('GetFileInformationByHandleEx', $source);
        $this->assertStringContainsString('FullIdentity', $source);
        $this->assertStringContainsString('FILE_RENAME_INFO_EX', $source);
        $this->assertStringContainsString('FILE_RENAME_INFORMATION_EX', $source);
        $this->assertStringContainsString('FILE_RENAME_REPLACE_IF_EXISTS', $source);
        $this->assertStringContainsString('FILE_RENAME_POSIX_SEMANTICS', $source);
        $this->assertStringContainsString('0x00000003', $source);
        $this->assertStringContainsString('0x00100081, 0x00000003', $source);
        $this->assertStringContainsString('STATE_SHARE_ACCESS, false, 0x00110083', $source);
        $this->assertStringContainsString('FILE_SHARE_READ | FILE_SHARE_WRITE | FILE_SHARE_DELETE, false, 0x00100081', $source);
        $this->assertStringContainsString("throw 'STATE_LOCKED'", $source);
        $this->assertStringNotContainsString('CreateNew(string path)', $source);
        $this->assertStringNotContainsString('[IO.File]::Open($statePath', $source);
    }

    public function test_router_only_emits_health_for_the_exact_guarded_marker(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2).'/Support/employee-acceptance-router.php');
        $this->assertIsString($source);
        $this->assertStringContainsString('/_employee_acceptance_health/', $source);
        $this->assertStringContainsString('hash_equals($runId, $requestedRunId)', $source);
        $this->assertStringContainsString("require __DIR__.'/../../public/index.php'", $source);
    }
}
