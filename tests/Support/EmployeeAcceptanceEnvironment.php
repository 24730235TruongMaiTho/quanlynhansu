<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Contracts\NhanVienRepositoryContract;
use App\Support\DisposableMariaDbGuard;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use PDO;
use RuntimeException;
use Throwable;

/** @internal Native Windows no-follow lease used only by the acceptance CLI. */
final class EmployeeAcceptanceNative
{
    private static $ffi = null;

    private static $ntdll = null;

    public static function ffi(): object
    {
        if (! extension_loaded('FFI') || ! class_exists('FFI')) {
            throw new RuntimeException('Native acceptance lease is unavailable.');
        }
        if (self::$ffi !== null) {
            return self::$ffi;
        }

        try {
            self::$ffi = \FFI::cdef(<<<'CDEF'
typedef void* HANDLE;
typedef unsigned int DWORD;
typedef int BOOL;
typedef struct { unsigned int dwLowDateTime; unsigned int dwHighDateTime; } FILETIME;
typedef struct {
    DWORD dwFileAttributes;
    FILETIME ftCreationTime;
    FILETIME ftLastAccessTime;
    FILETIME ftLastWriteTime;
    DWORD dwVolumeSerialNumber;
    DWORD nFileSizeHigh;
    DWORD nFileSizeLow;
    DWORD nNumberOfLinks;
    DWORD nFileIndexHigh;
    DWORD nFileIndexLow;
} BY_HANDLE_FILE_INFORMATION;
typedef struct {
    int ReplaceIfExists;
    HANDLE RootDirectory;
    DWORD FileNameLength;
    unsigned short FileName[1024];
} FILE_RENAME_INFO;
typedef struct { DWORD Flags; } FILE_DISPOSITION_INFO_EX;
typedef struct { int DeleteFile; } FILE_DISPOSITION_INFO;
HANDLE CreateFileW(const unsigned short*, DWORD, DWORD, void*, DWORD, DWORD, void*);
BOOL GetFileInformationByHandle(HANDLE, BY_HANDLE_FILE_INFORMATION*);
BOOL SetFileInformationByHandle(HANDLE, int, void*, DWORD);
DWORD GetLastError(void);
BOOL CloseHandle(HANDLE);
CDEF, 'kernel32.dll');
        } catch (Throwable $exception) {
            throw new RuntimeException('Native acceptance lease is unavailable.', 0, $exception);
        }

        return self::$ffi;
    }

    public static function wide(object $ffi, string $value): object
    {
        $encoded = function_exists('mb_convert_encoding')
            ? mb_convert_encoding($value, 'UTF-16LE', 'UTF-8')
            : (function_exists('iconv') ? iconv('UTF-8', 'UTF-16LE', $value) : false);
        if (! is_string($encoded)) {
            throw new RuntimeException('Native acceptance path encoding is unavailable.');
        }
        $units = unpack('v*', $encoded) ?: [];
        $buffer = $ffi->new('unsigned short['.(count($units) + 1).']');
        foreach ($units as $index => $unit) {
            $buffer[$index - 1] = $unit;
        }
        $buffer[count($units)] = 0;

        return $buffer;
    }

    public static function ntdll(): object
    {
        if (self::$ntdll !== null) {
            return self::$ntdll;
        }
        self::$ntdll = \FFI::cdef(<<<'CDEF'
typedef void* HANDLE;
typedef unsigned int DWORD;
typedef int NTSTATUS;
typedef struct { void* Status; unsigned long long Information; } IO_STATUS_BLOCK;
typedef struct {
    unsigned char ReplaceIfExists;
    unsigned char Padding[7];
    HANDLE RootDirectory;
    DWORD FileNameLength;
    unsigned short FileName[1024];
} FILE_RENAME_INFORMATION_NATIVE;
NTSTATUS NtSetInformationFile(HANDLE, IO_STATUS_BLOCK*, void*, DWORD, int);
CDEF, 'ntdll.dll');

        return self::$ntdll;
    }
}

/** @internal A live handle lease; closing it releases the OS lock. */
final class EmployeeAcceptanceDirectoryLease
{
    private object $ffi;

    private $handle;

    private string $identity;

    private bool $reparse;

    private bool $directory;

    private bool $closed = false;

    public function __construct(string $path, bool $allowReparse = false)
    {
        $this->ffi = EmployeeAcceptanceNative::ffi();
        $wide = EmployeeAcceptanceNative::wide($this->ffi, $path);
        $this->handle = $this->ffi->CreateFileW(
            $wide,
            0x00110083,
            0x00000003,
            null,
            3,
            0x02200000,
            null,
        );
        if (\FFI::isNull($this->handle)) {
            throw new RuntimeException('Native acceptance path lease failed.');
        }

        try {
            $information = $this->ffi->new('BY_HANDLE_FILE_INFORMATION');
            if ($this->ffi->GetFileInformationByHandle($this->handle, \FFI::addr($information)) === 0) {
                throw new RuntimeException('Native acceptance path identity failed.');
            }
            $this->reparse = (($information->dwFileAttributes & 0x00000400) !== 0);
            if ($this->reparse && ! $allowReparse) {
                throw new RuntimeException('Native acceptance reparse point rejected.');
            }
            $this->identity = sprintf(
                '%08X:%08X%08X',
                $information->dwVolumeSerialNumber,
                $information->nFileIndexHigh,
                $information->nFileIndexLow,
            );
            $this->directory = is_dir($path);
        } catch (Throwable $exception) {
            $this->close();
            throw $exception;
        }
    }

    public function identity(): string
    {
        return $this->identity;
    }

    public function refreshIdentity(): string
    {
        if ($this->closed) {
            throw new RuntimeException('Native acceptance lease is closed.');
        }
        $information = $this->ffi->new('BY_HANDLE_FILE_INFORMATION');
        if ($this->ffi->GetFileInformationByHandle($this->handle, \FFI::addr($information)) === 0) {
            throw new RuntimeException('Native acceptance path identity failed.');
        }
        $this->reparse = (($information->dwFileAttributes & 0x00000400) !== 0);
        $this->directory = (($information->dwFileAttributes & 0x00000010) !== 0);

        return sprintf(
            '%08X:%08X%08X',
            $information->dwVolumeSerialNumber,
            $information->nFileIndexHigh,
            $information->nFileIndexLow,
        );
    }

    public function isReparse(): bool
    {
        return $this->reparse;
    }

    public function isDirectory(): bool
    {
        return $this->directory;
    }

    public function renameTo(EmployeeAcceptanceDirectoryLease $parent, string $leaf): void
    {
        if ($this->closed || $parent->closed || strpbrk($leaf, "\\/") !== false || $leaf === '.' || $leaf === '..') {
            throw new RuntimeException('Native acceptance rename target is invalid.');
        }
        $encoded = mb_convert_encoding($leaf, 'UTF-16LE', 'UTF-8');
        $rename = $this->ffi->new('FILE_RENAME_INFO');
        $rename->ReplaceIfExists = 0;
        $rename->RootDirectory = $parent->handle;
        $rename->FileNameLength = strlen($encoded);
        $units = unpack('v*', $encoded) ?: [];
        if (count($units) >= 1024) {
            throw new RuntimeException('Native acceptance rename target is too long.');
        }
        foreach ($units as $index => $unit) {
            $rename->FileName[$index - 1] = $unit;
        }
        $length = 20 + strlen($encoded);
        if ($this->ffi->SetFileInformationByHandle($this->handle, 3, \FFI::addr($rename), $length) !== 0) {
            return;
        }
        if ((int) $this->ffi->GetLastError() !== 87) {
            throw new RuntimeException('Native acceptance quarantine rename failed.');
        }
        $ntdll = EmployeeAcceptanceNative::ntdll();
        $native = $ntdll->new('FILE_RENAME_INFORMATION_NATIVE');
        $native->ReplaceIfExists = 0;
        $native->RootDirectory = $ntdll->cast('HANDLE', $parent->handle);
        $native->FileNameLength = strlen($encoded);
        foreach ($units as $index => $unit) {
            $native->FileName[$index - 1] = $unit;
        }
        $io = $ntdll->new('IO_STATUS_BLOCK');
        $status = $ntdll->NtSetInformationFile(
            $ntdll->cast('HANDLE', $this->handle),
            \FFI::addr($io),
            \FFI::addr($native),
            $length,
            10,
        );
        if ((int) $status !== 0) {
            throw new RuntimeException('Native acceptance quarantine rename failed.');
        }
    }

    public function delete(): void
    {
        if ($this->closed) {
            throw new RuntimeException('Native acceptance lease is closed.');
        }
        $disposition = $this->ffi->new('FILE_DISPOSITION_INFO_EX');
        $disposition->Flags = 0x00000013;
        if ($this->ffi->SetFileInformationByHandle($this->handle, 21, \FFI::addr($disposition), \FFI::sizeof($disposition)) !== 0) {
            return;
        }
        $legacy = $this->ffi->new('FILE_DISPOSITION_INFO');
        $legacy->DeleteFile = 1;
        if ($this->ffi->SetFileInformationByHandle($this->handle, 4, \FFI::addr($legacy), \FFI::sizeof($legacy)) === 0) {
            throw new RuntimeException('Native acceptance delete failed.');
        }
    }

    public function close(): void
    {
        if (! $this->closed && $this->handle !== null) {
            $this->ffi->CloseHandle($this->handle);
            $this->closed = true;
        }
    }

    public function __destruct()
    {
        $this->close();
    }
}

/**
 * Test-only boundary for the browser acceptance environment.
 *
 * This file is deliberately a CLI entry point as well as a small collection
 * of pure guards used by the safety tests.  It never reads DB_DATABASE and it
 * never includes a credential in an error or success payload.
 */
final class EmployeeAcceptanceEnvironment
{
    private const ACTIONS = [
        'create',
        'verify-runtime',
        'seed-roles',
        'assign-role',
        'cleanup-uploads',
        'drop',
    ];

    private const VIEW_ONLY_ROLE = 'Chỉ xem nhân viên';

    private const NO_PERMISSION_ROLE = 'Không có quyền nhân viên';

    private const VIEW_PERMISSION = 'NHAN_VIEN_XEM';

    /** @return list<string> */
    public static function actions(): array
    {
        return self::ACTIONS;
    }

    public static function assertAction(string $action): void
    {
        if (! in_array($action, self::ACTIONS, true)) {
            throw new RuntimeException('Unsupported acceptance action.');
        }
    }

    public static function assertDatabaseName(string $database): void
    {
        DisposableMariaDbGuard::assertSafeDatabaseName($database);
        if (preg_match('/\Aquan_ly_nhan_su_employee_test_[a-f0-9]{12}\z/', $database) !== 1) {
            throw new RuntimeException('Unsafe acceptance database name.');
        }
    }

    public static function runIdFromDatabase(string $database): string
    {
        self::assertDatabaseName($database);

        return substr($database, strlen('quan_ly_nhan_su_employee_test_'));
    }

    public static function databaseNameForRunId(string $runId): string
    {
        if (preg_match('/\A[a-f0-9]{12}\z/', $runId) !== 1) {
            throw new RuntimeException('Unsafe acceptance run ID.');
        }

        $database = 'quan_ly_nhan_su_employee_test_'.$runId;
        self::assertDatabaseName($database);

        return $database;
    }

    /**
     * @return array<string, string>
     */
    public static function syntheticFixture(string $runId): array
    {
        if (preg_match('/\A[a-f0-9]{12}\z/', $runId) !== 1) {
            throw new RuntimeException('Unsafe acceptance run ID.');
        }

        $digits = '';
        foreach (str_split($runId) as $character) {
            $digits .= (string) (hexdec($character) % 10);
        }

        return [
            'expected_ma_nv' => 'NV001',
            'department' => 'PB Acceptance '.$runId,
            'position' => 'CV Acceptance '.$runId,
            'position_allowance' => '0.00',
            'role' => 'Quản trị acceptance '.$runId,
            'admin_name' => 'Quản trị Acceptance',
            'admin_email' => 'admin-'.$runId.'@example.test',
            'admin_phone' => '09'.substr($digits, 0, 8),
            'admin_cccd' => $digits,
            'birth_date' => '1990-01-01',
            'start_date' => '2026-08-12',
            'gender' => '1',
            'education' => 'Đại học',
            'ethnicity' => 'Kinh',
            'cccd_place' => 'Cục CSQLHC',
            'address_line' => '1 Đường Kiểm Thử',
            'ward' => 'Phường Test',
            'district' => 'Quận Test',
            'province' => 'TP Hồ Chí Minh',
        ];
    }

    public static function uploadDirectory(string $repositoryRoot, string $database): string
    {
        $runId = self::runIdFromDatabase($database);
        $root = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $repositoryRoot), DIRECTORY_SEPARATOR);

        return $root.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'public'
            .DIRECTORY_SEPARATOR.'nhan-vien'.DIRECTORY_SEPARATOR.'acceptance'.DIRECTORY_SEPARATOR.$runId
            .DIRECTORY_SEPARATOR.'avatars';
    }

    /** @return array{ok: false, error: string} */
    public static function allowlistedError(string $error, ?string $detail = null): array
    {
        $allowed = [
            'ARGUMENTS_INVALID',
            'CREATE_FAILED',
            'RUNTIME_TARGET_INVALID',
            'SEED_ROLES_FAILED',
            'ASSIGN_ROLE_FAILED',
            'CLEANUP_UPLOADS_FAILED',
            'DROP_FAILED',
        ];

        return ['ok' => false, 'error' => in_array($error, $allowed, true) ? $error : 'ACCEPTANCE_FAILED'];
    }

    public static function main(array $argv): int
    {
        $action = (string) ($argv[1] ?? '');

        try {
            self::assertAction($action);
            if (count($argv) !== 2) {
                throw new RuntimeException('Only the action argument is accepted.');
            }

            $payload = match ($action) {
                'create' => self::createDatabase(),
                'verify-runtime' => self::verifyRuntime(),
                'seed-roles' => self::seedRoles(),
                'assign-role' => self::assignRole(),
                'cleanup-uploads' => self::cleanupUploads(),
                'drop' => self::dropDatabase(),
            };

            fwrite(STDOUT, json_encode($payload, JSON_THROW_ON_ERROR).PHP_EOL);

            return 0;
        } catch (Throwable) {
            $error = match ($action) {
                'create' => 'CREATE_FAILED',
                'verify-runtime' => 'RUNTIME_TARGET_INVALID',
                'seed-roles' => 'SEED_ROLES_FAILED',
                'assign-role' => 'ASSIGN_ROLE_FAILED',
                'cleanup-uploads' => 'CLEANUP_UPLOADS_FAILED',
                'drop' => 'DROP_FAILED',
                default => 'ARGUMENTS_INVALID',
            };
            fwrite(STDERR, json_encode(self::allowlistedError($error), JSON_THROW_ON_ERROR).PHP_EOL);

            return 1;
        }
    }

    /** @return array{database: string} */
    private static function createDatabase(): array
    {
        $credentials = self::credentials();
        $database = self::databaseNameForRunId(bin2hex(random_bytes(6)));
        $admin = null;
        $pdo = null;

        try {
            $admin = self::adminPdo($credentials);
            $admin->exec(sprintf(
                'CREATE DATABASE `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
                $database,
            ));
            $pdo = self::databasePdo($credentials, $database);
            $pdo->exec("SET time_zone = '+07:00'");
            SqlScriptRunner::run($pdo, self::repositoryRoot().'/tests/Fixtures/MariaDb/employee_legacy_schema.sql');
            foreach (self::employeeScripts() as $index => $script) {
                $failureAt = getenv('EMPLOYEE_ACCEPTANCE_TEST_FAIL_SCRIPT');
                if (is_string($failureAt) && $failureAt !== '') {
                    if (preg_match('/\A[1-6]\z/', $failureAt) !== 1) {
                        throw new RuntimeException('Invalid acceptance script failure seam.');
                    }
                    if ((int) $failureAt === $index + 1) {
                        throw new RuntimeException('Injected acceptance script failure.');
                    }
                }
                SqlScriptRunner::run($pdo, $script);
            }
            self::installMasterCreateProceduresForFixture($pdo);
            $pdo = null;

            return ['database' => $database];
        } catch (Throwable $exception) {
            try {
                $pdo = null;
                if ($admin instanceof PDO) {
                    self::dropWithAdmin($admin, $database);
                }
            } catch (Throwable) {
                // The original failure remains intentionally generic.
            }

            throw new RuntimeException('Acceptance database creation failed.', 0, $exception);
        } finally {
            $pdo = null;
            $admin = null;
        }
    }

    /** @return array{ok: true, database: string} */
    private static function verifyRuntime(): array
    {
        $runtime = self::guardedRuntime();

        return ['ok' => true, 'database' => $runtime['database']];
    }

    /** @return array{ok: true} */
    private static function seedRoles(): array
    {
        $runtime = self::guardedRuntime();
        $pdo = $runtime['pdo'];

        try {
            $pdo->beginTransaction();
            $viewRole = self::ensureRole($pdo, self::VIEW_ONLY_ROLE);
            $noPermissionRole = self::ensureRole($pdo, self::NO_PERMISSION_ROLE);
            $permission = self::permissionId($pdo, self::VIEW_PERMISSION);

            self::removeRoleMappings($pdo, $viewRole);
            self::call($pdo, 'CALL sp_vai_tro_quyen_them(?, ?)', [$viewRole, $permission]);
            self::removeRoleMappings($pdo, $noPermissionRole);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw new RuntimeException('Acceptance role fixture failed.', 0, $exception);
        }

        return ['ok' => true];
    }

    /** @return array{ok: true, employee: string, role: string} */
    private static function assignRole(): array
    {
        $runtime = self::guardedRuntime();
        $employee = getenv('EMPLOYEE_ACCEPTANCE_MA_NV');
        $alias = getenv('EMPLOYEE_ACCEPTANCE_ROLE_ALIAS');
        if (! is_string($employee) || preg_match('/\ANV[0-9]{3}\z/', $employee) !== 1
            || ! is_string($alias) || ! in_array($alias, ['view-only', 'no-permission'], true)) {
            throw new RuntimeException('Acceptance role assignment input is invalid.');
        }

        $roleName = $alias === 'view-only' ? self::VIEW_ONLY_ROLE : self::NO_PERMISSION_ROLE;
        $roleId = self::roleId($runtime['pdo'], $roleName);
        $repository = $runtime['app']->make(NhanVienRepositoryContract::class);
        $repository->assignRoleForBootstrap($employee, $roleId);

        return ['ok' => true, 'employee' => $employee, 'role' => $alias];
    }

    /** @return array{ok: true} */
    private static function cleanupUploads(): array
    {
        $runtime = self::guardedRuntime();
        self::removeOwnedUploads(self::repositoryRoot(), $runtime['database']);

        return ['ok' => true];
    }

    /** @return array{ok: true, database: string} */
    private static function dropDatabase(): array
    {
        $runtime = self::guardedRuntime();
        $database = $runtime['database'];
        DB::disconnect('employee_acceptance');
        DB::purge('employee_acceptance');
        $admin = self::adminPdo(self::credentials());
        try {
            self::dropWithAdmin($admin, $database);
        } finally {
            $admin = null;
        }

        return ['ok' => true, 'database' => $database];
    }

    /**
     * @return array{app: mixed, connection: Connection, pdo: PDO, database: string, run_id: string}
     */
    private static function guardedRuntime(): array
    {
        $database = self::requiredEnvironment('MARIADB_TEST_DATABASE');
        self::assertDatabaseName($database);
        $runId = self::runIdFromDatabase($database);
        $credentials = self::credentials();
        self::assertRuntimeEnvironment($runId);

        $app = self::bootLaravel();
        $config = [
            'driver' => 'mysql',
            'host' => $credentials['host'],
            'port' => $credentials['port'],
            'database' => $database,
            'username' => $credentials['username'],
            'password' => $credentials['password'],
            'unix_socket' => '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'timezone' => '+07:00',
        ];
        config()->set('database.connections.employee_acceptance', $config);
        DB::purge('employee_acceptance');
        DB::setDefaultConnection('employee_acceptance');

        $resolved = config('database.connections.employee_acceptance');
        if (! is_array($resolved)
            || ($resolved['driver'] ?? null) !== 'mysql'
            || ($resolved['database'] ?? null) !== $database
            || (string) ($resolved['host'] ?? '') !== $credentials['host']
            || (string) ($resolved['port'] ?? '') !== $credentials['port']
            || (string) ($resolved['username'] ?? '') !== $credentials['username']
            || (string) ($resolved['password'] ?? '') !== $credentials['password']
            || (string) ($resolved['unix_socket'] ?? '') !== '') {
            throw new RuntimeException('Resolved acceptance connection is not guarded.');
        }

        $connection = DB::connection('employee_acceptance');
        $actual = $connection->selectOne('SELECT DATABASE() AS database_name', [], false);
        if ($connection->getDriverName() !== 'mysql'
            || (string) $connection->getConfig('database') !== $database
            || ! is_object($actual)
            || (string) ($actual->database_name ?? '') !== $database) {
            throw new RuntimeException('Actual acceptance database is not guarded.');
        }

        return [
            'app' => $app,
            'connection' => $connection,
            'pdo' => $connection->getPdo(),
            'database' => $database,
            'run_id' => $runId,
        ];
    }

    private static function assertRuntimeEnvironment(string $runId): void
    {
        $credentials = self::credentials();
        $database = self::requiredEnvironment('MARIADB_TEST_DATABASE');
        $expected = [
            'APP_ENV' => 'testing',
            'APP_DEBUG' => 'false',
            'APP_URL' => 'http://127.0.0.1:8012',
            'APP_TIMEZONE' => 'Asia/Ho_Chi_Minh',
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => $credentials['host'],
            'DB_PORT' => $credentials['port'],
            'DB_USERNAME' => $credentials['username'],
            'DB_PASSWORD' => $credentials['password'],
            'DB_DATABASE' => $database,
            'DB_SOCKET' => '',
            'DB_TIMEZONE' => '+07:00',
            'SESSION_DRIVER' => 'cookie',
            'CACHE_STORE' => 'array',
            'QUEUE_CONNECTION' => 'sync',
            'LOG_CHANNEL' => 'stderr',
            'NHAN_VIEN_MODULE_ENABLED' => 'true',
            'EMPLOYEE_AVATAR_PREFIX' => 'nhan-vien/acceptance/'.$runId.'/avatars',
            'EMPLOYEE_ACCEPTANCE_RUN_ID' => $runId,
        ];
        foreach ($expected as $name => $value) {
            if (getenv($name) !== $value) {
                throw new RuntimeException('Acceptance runtime environment is not exact.');
            }
        }

        foreach (['APP_CONFIG_CACHE', 'APP_ROUTES_CACHE'] as $name) {
            $cache = getenv($name);
            $expected = self::repositoryRoot().DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'framework'
                .DIRECTORY_SEPARATOR.'testing'.DIRECTORY_SEPARATOR.'employee-acceptance'.DIRECTORY_SEPARATOR
                .$runId.DIRECTORY_SEPARATOR.($name === 'APP_CONFIG_CACHE' ? 'config.php' : 'routes.php');
            if (! is_string($cache) || $cache === '' || file_exists($cache)
                || strtolower(self::normalizePath($cache)) !== strtolower(self::normalizePath($expected))) {
                throw new RuntimeException('Acceptance cache is active or missing.');
            }
            self::assertSafeDirectoryChain(dirname($expected));
        }

        $dbUrl = getenv('DB_URL');
        if (! is_string($dbUrl) || $dbUrl === '') {
            throw new RuntimeException('Acceptance DB URL is missing.');
        }
        $parsed = parse_url($dbUrl);
        if (! is_array($parsed)
            || ($parsed['scheme'] ?? null) !== 'mysql'
            || ($parsed['host'] ?? null) !== $credentials['host']
            || (string) ($parsed['port'] ?? '') !== $credentials['port']
            || rawurldecode((string) ($parsed['user'] ?? '')) !== $credentials['username']
            || rawurldecode((string) ($parsed['pass'] ?? '')) !== $credentials['password']
            || ltrim((string) ($parsed['path'] ?? ''), '/') !== $database) {
            throw new RuntimeException('Acceptance DB URL is not guarded.');
        }
    }

    /** @return array{host: string, port: string, username: string, password: string} */
    private static function credentials(): array
    {
        if (getenv('MARIADB_TEST_ENABLED') !== '1') {
            throw new RuntimeException('Disposable MariaDB is not enabled.');
        }

        return DisposableMariaDbGuard::environment();
    }

    private static function requiredEnvironment(string $name): string
    {
        $value = getenv($name);
        if (! is_string($value) || $value === '') {
            throw new RuntimeException('Required acceptance environment is missing.');
        }

        return $value;
    }

    /** @param array{host: string, port: string, username: string, password: string} $credentials */
    private static function adminPdo(array $credentials): PDO
    {
        return new PDO(
            sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $credentials['host'], $credentials['port']),
            $credentials['username'],
            $credentials['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );
    }

    /** @param array{host: string, port: string, username: string, password: string} $credentials */
    private static function databasePdo(array $credentials, string $database): PDO
    {
        self::assertDatabaseName($database);

        return new PDO(
            sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                $credentials['host'],
                $credentials['port'],
                $database,
            ),
            $credentials['username'],
            $credentials['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );
    }

    private static function dropWithAdmin(PDO $admin, string $database): void
    {
        self::assertDatabaseName($database);
        $admin->exec(sprintf('DROP DATABASE IF EXISTS `%s`', $database));
    }

    /** @return list<string> */
    private static function employeeScripts(): array
    {
        $root = self::repositoryRoot().'/database/sql/employee';

        return array_map(
            static fn (int $number): string => $root.'/2026_08_12_00'.str_pad((string) $number, 1, '0', STR_PAD_LEFT).'_'.match ($number) {
                1 => 'schema',
                2 => 'read_routines',
                3 => 'create_routines',
                4 => 'update_routines',
                5 => 'lifecycle_auth_routines',
                6 => 'rbac',
            }.'.sql',
            range(1, 6),
        );
    }

    private static function installMasterCreateProceduresForFixture(PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            CREATE PROCEDURE sp_phong_ban_them(IN p_ten_pb NVARCHAR(100))
            BEGIN
                INSERT INTO phong_ban (ten_pb) VALUES (TRIM(p_ten_pb));
            END
            SQL);
        $pdo->exec(<<<'SQL'
            CREATE PROCEDURE sp_chuc_vu_them(IN p_ten_cv NVARCHAR(100), IN p_he_so_phu_cap DECIMAL(18,2))
            BEGIN
                INSERT INTO chuc_vu (ten_cv, he_so_phu_cap) VALUES (TRIM(p_ten_cv), p_he_so_phu_cap);
            END
            SQL);
        $pdo->exec(<<<'SQL'
            CREATE PROCEDURE sp_vai_tro_them(IN p_ten_vt NVARCHAR(100), IN p_mo_ta NVARCHAR(255))
            BEGIN
                INSERT INTO vai_tro (ten_vt, mo_ta) VALUES (TRIM(p_ten_vt), p_mo_ta);
            END
            SQL);
    }

    private static function repositoryRoot(): string
    {
        return dirname(__DIR__, 2);
    }

    private static function bootLaravel(): mixed
    {
        $app = require self::repositoryRoot().'/bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    private static function roleId(PDO $pdo, string $roleName): int
    {
        $statement = $pdo->prepare(
            'SELECT ma_vt FROM vai_tro WHERE BINARY ten_vt = BINARY ? ORDER BY ma_vt',
        );
        $statement->execute([$roleName]);
        $ids = array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
        if (count($ids) !== 1) {
            throw new RuntimeException('Acceptance role fixture is ambiguous.');
        }

        return $ids[0];
    }

    private static function ensureRole(PDO $pdo, string $roleName): int
    {
        $statement = $pdo->prepare(
            'SELECT ma_vt FROM vai_tro WHERE BINARY ten_vt = BINARY ? ORDER BY ma_vt FOR UPDATE',
        );
        $statement->execute([$roleName]);
        $ids = array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
        if (count($ids) > 1) {
            throw new RuntimeException('Acceptance role fixture is ambiguous.');
        }
        if (count($ids) === 1) {
            return $ids[0];
        }

        $insert = $pdo->prepare('INSERT INTO vai_tro (ten_vt, mo_ta, ky_hieu) VALUES (?, ?, NULL)');
        $insert->execute([$roleName, 'Acceptance-only role fixture']);

        return (int) $pdo->lastInsertId();
    }

    private static function permissionId(PDO $pdo, string $symbol): int
    {
        $statement = $pdo->prepare(
            'SELECT ma_quyen FROM quyen WHERE BINARY ky_hieu_quyen = BINARY ? ORDER BY ma_quyen',
        );
        $statement->execute([$symbol]);
        $ids = array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
        if (count($ids) !== 1) {
            throw new RuntimeException('Acceptance permission fixture is invalid.');
        }

        return $ids[0];
    }

    private static function removeRoleMappings(PDO $pdo, int $roleId): void
    {
        $statement = $pdo->prepare('DELETE FROM vai_tro_quyen WHERE ma_vt = ?');
        $statement->execute([$roleId]);
    }

    /** @param list<int|string> $bindings */
    private static function call(PDO $pdo, string $sql, array $bindings): void
    {
        $statement = $pdo->prepare($sql);
        $statement->execute($bindings);
        while ($statement->nextRowset()) {
            // Drain every result set before the next guarded statement.
        }
        $statement->closeCursor();
    }

    private static function removeOwnedUploads(string $repositoryRoot, string $database): void
    {
        $base = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $repositoryRoot), DIRECTORY_SEPARATOR)
            .DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'public'
            .DIRECTORY_SEPARATOR.'nhan-vien'.DIRECTORY_SEPARATOR.'acceptance';
        self::assertSafeDirectoryChain($base);
        $target = self::uploadDirectory($repositoryRoot, $database);
        $initial = @lstat($target);
        if (! is_array($initial)) {
            return;
        }
        $runRoot = dirname($target);
        $baseLease = new EmployeeAcceptanceDirectoryLease($base);
        $runLease = null;
        $targetLease = null;
        try {
            $runLease = new EmployeeAcceptanceDirectoryLease($runRoot);
            $targetLease = new EmployeeAcceptanceDirectoryLease($target, true);
            if ($targetLease->isReparse() || ! $targetLease->isDirectory()) {
                throw new RuntimeException('Acceptance upload target is not a regular directory.');
            }
            self::simulateSwapRaceForTest($target, $runRoot, $base);

            $identity = $targetLease->identity();
            $leaf = self::newQuarantineLeaf();
            $targetLease->renameTo($runLease, $leaf);
            $quarantine = $runRoot.DIRECTORY_SEPARATOR.$leaf;
            if ($targetLease->refreshIdentity() !== $identity || $targetLease->isReparse() || ! $targetLease->isDirectory()) {
                throw new RuntimeException('Acceptance upload target identity changed during cleanup.');
            }
            self::removeDirectoryContents($targetLease, $quarantine);
            $targetLease->delete();
            $targetLease->close();
            $targetLease = null;
            self::deleteEmptyDirectoryByLease($runLease, $runRoot, $baseLease);
        } finally {
            if ($targetLease instanceof EmployeeAcceptanceDirectoryLease) {
                $targetLease->close();
            }
            if ($runLease instanceof EmployeeAcceptanceDirectoryLease) {
                $runLease->close();
            }
            $baseLease->close();
        }
    }

    private static function removeDirectoryContents(EmployeeAcceptanceDirectoryLease $directoryLease, string $directory): void
    {
        $entries = scandir($directory);
        if ($entries === false) {
            throw new RuntimeException('Acceptance upload directory cannot be read.');
        }
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $directory.DIRECTORY_SEPARATOR.$entry;
            $entryLease = new EmployeeAcceptanceDirectoryLease($path, true);
            $identity = $entryLease->identity();
            $leaf = self::newQuarantineLeaf();
            $entryLease->renameTo($directoryLease, $leaf);
            $quarantine = $directory.DIRECTORY_SEPARATOR.$leaf;
            $movedLease = $entryLease;
            try {
                if ($movedLease->refreshIdentity() !== $identity) {
                    throw new RuntimeException('Acceptance upload entry identity changed during cleanup.');
                }
                if ($movedLease->isReparse()) {
                    $movedLease->delete();
                } elseif ($movedLease->isDirectory()) {
                    self::removeDirectoryContents($movedLease, $quarantine);
                    $movedLease->delete();
                } else {
                    $movedLease->delete();
                }
            } finally {
                $entryLease->close();
            }
        }
    }

    private static function newQuarantineLeaf(): string
    {
        return '.employee-acceptance-quarantine-'.bin2hex(random_bytes(16));
    }

    private static function simulateSwapRaceForTest(string $target, string $runRoot, string $base): void
    {
        if (getenv('EMPLOYEE_ACCEPTANCE_TEST_SWAP_RACE') !== '1') {
            return;
        }
        $outside = dirname($base).DIRECTORY_SEPARATOR.'acceptance-swap-outside-'.basename($runRoot);
        $destination = $outside.DIRECTORY_SEPARATOR.'.swap-attempt-'.bin2hex(random_bytes(8));
        $command = ['pwsh', '-NoProfile', '-Command',
            "Move-Item -LiteralPath ".self::quotePowerShell($target)." -Destination ".self::quotePowerShell($destination)." -Force"];
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, self::repositoryRoot());
        if (! is_resource($process)) {
            throw new RuntimeException('Unable to start the disposable swap-race attempt.');
        }
        $exit = proc_close($process);
        if ($exit === 0 || is_array(@lstat($destination))) {
            throw new RuntimeException('Native acceptance parent lease did not block the swap attempt.');
        }
    }

    private static function quotePowerShell(string $value): string
    {
        return "'".str_replace("'", "''", $value)."'";
    }

    private static function deleteEmptyDirectoryByLease(EmployeeAcceptanceDirectoryLease $directoryLease, string $directory, EmployeeAcceptanceDirectoryLease $parentLease): void
    {
        $entries = scandir($directory);
        if ($entries !== false && count(array_diff($entries, ['.', '..'])) === 0) {
            try {
                $directoryLease->renameTo($parentLease, '.employee-acceptance-empty-'.bin2hex(random_bytes(16)));
                $directoryLease->delete();
            } finally {
                // The caller owns and closes the original directory lease.
            }
        }
    }

    private static function assertSafeDirectoryChain(string $path): void
    {
        $normalized = self::normalizePath($path);
        $cursor = $normalized;
        while ($cursor !== dirname($cursor)) {
            if (is_array(@lstat($cursor)) && (is_link($cursor) || self::isReparsePoint($cursor))) {
                throw new RuntimeException('Acceptance path reparse point rejected.');
            }
            if (is_array(@lstat($cursor)) && realpath($cursor) !== false
                && strtolower(self::normalizePath((string) realpath($cursor))) !== strtolower($cursor)) {
                throw new RuntimeException('Acceptance path changed or is outside the guard.');
            }
            $cursor = dirname($cursor);
            if (strtolower($cursor) === strtolower(dirname($cursor))) {
                break;
            }
        }
    }

    private static function assertExactOwnedPath(string $path, string $parent): void
    {
        if (is_link($path) || self::isReparsePoint($path)) {
            throw new RuntimeException('Acceptance path reparse point rejected.');
        }
        $resolved = realpath($path);
        $expected = self::normalizePath($path);
        if ($resolved === false || strtolower(self::normalizePath($resolved)) !== strtolower($expected)) {
            throw new RuntimeException('Acceptance path changed or is outside the guard.');
        }
        $resolvedParent = realpath(dirname($path));
        if ($resolvedParent === false || strtolower(self::normalizePath($resolvedParent)) !== strtolower(self::normalizePath($parent))) {
            throw new RuntimeException('Acceptance path parent changed or is outside the guard.');
        }
    }

    private static function normalizePath(string $path): string
    {
        $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        $root = '';
        if (DIRECTORY_SEPARATOR === '\\' && preg_match('/^[A-Za-z]:\\\\?/', $path) === 1) {
            $root = substr($path, 0, 2).DIRECTORY_SEPARATOR;
            $path = substr($path, 2);
        } elseif (str_starts_with($path, DIRECTORY_SEPARATOR)) {
            $root = DIRECTORY_SEPARATOR;
            $path = ltrim($path, DIRECTORY_SEPARATOR);
        }
        $parts = [];
        foreach (preg_split('/[\\\\\/]+/', $path, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $part) {
            if ($part === '.') {
                continue;
            }
            if ($part === '..' && $parts !== [] && end($parts) !== '..') {
                array_pop($parts);
                continue;
            }
            $parts[] = $part;
        }
        $joined = implode(DIRECTORY_SEPARATOR, $parts);
        if ($root !== '') {
            return $root.($joined === '' ? '' : $joined);
        }

        return $joined === '' ? '.' : $joined;
    }

    private static function isReparsePoint(string $path): bool
    {
        $info = @lstat($path);
        if (! is_array($info)) {
            return false;
        }

        return ($info['mode'] & 0120000) === 0120000;
    }
}

if (PHP_SAPI === 'cli' && realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === realpath(__FILE__)) {
    require dirname(__DIR__, 2).'/vendor/autoload.php';
    exit(EmployeeAcceptanceEnvironment::main($argv));
}
