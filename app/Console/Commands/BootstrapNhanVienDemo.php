<?php

namespace App\Console\Commands;

use App\Contracts\NhanVienRepositoryContract;
use App\Contracts\NhanVienServiceContract;
use App\Support\DisposableMariaDbGuard;
use Carbon\CarbonImmutable;
use DateTime;
use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use RuntimeException;
use Throwable;

final class BootstrapNhanVienDemo extends Command
{
    protected $signature = 'employee:bootstrap-demo
        {--department= : Synthetic department name}
        {--position= : Synthetic position name}
        {--position-allowance= : Position allowance}
        {--role= : Synthetic administrator role name}
        {--admin-name= : Demo employee name}
        {--admin-email= : Demo employee email}
        {--admin-phone= : Demo employee phone}
        {--admin-cccd= : Demo employee CCCD}
        {--birth-date= : Demo employee birth date}
        {--start-date= : Demo employee start date}
        {--gender= : Demo employee gender, 0 or 1}
        {--education= : Demo employee education}
        {--ethnicity= : Demo employee ethnicity}
        {--cccd-place= : CCCD issuing place}
        {--address-line= : Demo employee address}
        {--ward= : Demo employee ward}
        {--district= : Demo employee district}
        {--province= : Demo employee province}
        {--yes : Confirm explicit demo bootstrap}
        {--require-disposable : Require the guarded disposable MariaDB target}';

    protected $description = 'Create synthetic employee demo data on an explicitly guarded disposable MariaDB database.';

    private const BASELINE_ROLE_SYMBOL = 'NHAN_VIEN_MAC_DINH';

    private const BASELINE_ROLE_NAME = 'Nhân viên mặc định';

    private const WORKING_STATUS_SYMBOL = 'DANG_LAM';

    /** @var list<string> */
    private const EMPLOYEE_PERMISSIONS = [
        'NHAN_VIEN_XEM',
        'NHAN_VIEN_TAO',
        'NHAN_VIEN_SUA',
        'NHAN_VIEN_XOA',
        'NHAN_VIEN_DAT_LAI_MAT_KHAU',
    ];

    public function __construct(
        private DatabaseManager $database,
        private NhanVienServiceContract $service,
        private NhanVienRepositoryContract $repository,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $input = $this->validatedInput();
            $connection = $this->guardedConnection();
            $maNv = $connection->transaction(
                function () use ($connection, $input): string {
                    // This stable Task16 row serializes every bootstrap invocation
                    // before any master/identity preflight or mutation occurs.
                    $baselineRole = $this->lockBaselineRole($connection);
                    $references = $this->preflight($connection, $input, $baselineRole);

                    return $this->mutate($connection, $input, $references);
                },
            );

            $this->line('Mã nhân viên demo: '.$maNv);
            $this->line('Tài khoản dùng quy ước mật khẩu nhom3@{năm tạo}.');

            return self::SUCCESS;
        } catch (Throwable) {
            $this->error('Không thể khởi tạo dữ liệu demo.');

            return self::FAILURE;
        }
    }

    /** @return array<string, mixed> */
    private function validatedInput(): array
    {
        if (! in_array((string) config('app.env'), ['local', 'testing'], true)) {
            throw new RuntimeException('Demo bootstrap is restricted to local and testing environments.');
        }

        if (! $this->option('yes') || ! $this->option('require-disposable')) {
            throw new RuntimeException('Explicit confirmation and disposable guard are required.');
        }

        $textLimits = [
            'department' => 100,
            'position' => 100,
            'role' => 100,
            'admin-name' => 50,
            'education' => 50,
            'ethnicity' => 50,
            'cccd-place' => 50,
            'address-line' => 255,
            'ward' => 100,
            'district' => 100,
            'province' => 100,
        ];
        $input = [];

        foreach ($textLimits as $option => $limit) {
            $input[$option] = $this->requiredText($option, $limit);
        }

        $email = mb_strtolower($this->requiredText('admin-email', 100), 'UTF-8');
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false
            || preg_match('/\A[^@\s]+@[^@\s]+\.[^@\s]+\z/', $email) !== 1) {
            throw new RuntimeException('Invalid email.');
        }
        $input['admin-email'] = $email;

        $phone = $this->requiredText('admin-phone', 15);
        if (preg_match('/\A0[0-9]{9}\z/', $phone) !== 1) {
            throw new RuntimeException('Invalid phone.');
        }
        $input['admin-phone'] = $phone;

        $cccd = $this->requiredText('admin-cccd', 12);
        if (preg_match('/\A[0-9]{12}\z/', $cccd) !== 1) {
            throw new RuntimeException('Invalid CCCD.');
        }
        $input['admin-cccd'] = $cccd;

        $input['birth-date'] = $this->dateOption('birth-date');
        $input['start-date'] = $this->dateOption('start-date');
        if ($input['birth-date'] >= $input['start-date']
            || CarbonImmutable::parse($input['birth-date'])
                ->diffInYears(CarbonImmutable::parse($input['start-date'])) < 18) {
            throw new RuntimeException('Invalid employee age or dates.');
        }

        $gender = $this->requiredText('gender', 1);
        if (! in_array($gender, ['0', '1'], true)) {
            throw new RuntimeException('Invalid gender.');
        }
        $input['gender'] = (int) $gender;

        $allowance = $this->requiredText('position-allowance', 6);
        if (preg_match('/\A(?:[0-9]{1,3})(?:\.[0-9]{1,2})?\z/', $allowance) !== 1
            || (float) $allowance > 999.99) {
            throw new RuntimeException('Invalid position allowance.');
        }
        $input['position-allowance'] = number_format((float) $allowance, 2, '.', '');

        if (mb_strtolower($input['role'], 'UTF-8') === mb_strtolower(self::BASELINE_ROLE_NAME, 'UTF-8')) {
            throw new RuntimeException('The baseline role cannot be selected.');
        }

        return $input;
    }

    private function requiredText(string $option, int $maxLength): string
    {
        $value = $this->option($option);
        if (! is_string($value)) {
            throw new RuntimeException('A required option is missing.');
        }

        $value = trim($value);
        if ($value === '' || mb_strlen($value, 'UTF-8') > $maxLength) {
            throw new RuntimeException('A required option is invalid.');
        }

        return $value;
    }

    private function dateOption(string $option): string
    {
        $value = $this->requiredText($option, 10);
        $date = CarbonImmutable::createFromFormat('!Y-m-d', $value);
        $errors = DateTime::getLastErrors();

        if ($date === false
            || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $date->format('Y-m-d') !== $value) {
            throw new RuntimeException('Invalid date.');
        }

        return $value;
    }

    private function guardedConnection(): Connection
    {
        if (getenv('MARIADB_TEST_ENABLED') !== '1') {
            throw new RuntimeException('Disposable MariaDB is not enabled.');
        }

        $database = getenv('MARIADB_TEST_DATABASE');
        if (! is_string($database) || $database === '') {
            throw new RuntimeException('Disposable database is missing.');
        }
        DisposableMariaDbGuard::assertSafeDatabaseName($database);
        $testEnvironment = DisposableMariaDbGuard::environment();

        $connectionName = $this->database->getDefaultConnection();
        $configured = config('database.connections.'.$connectionName);
        if (! is_array($configured) || ($configured['driver'] ?? null) !== 'mysql') {
            throw new RuntimeException('The resolved connection is not mysql.');
        }

        $configuredUrl = $configured['url'] ?? null;
        if ((is_string($configuredUrl) && $configuredUrl !== '')
            || (is_string(getenv('DB_URL')) && getenv('DB_URL') !== '')) {
            throw new RuntimeException('A DB_URL override is not allowed.');
        }

        if (($configured['database'] ?? null) !== $database) {
            throw new RuntimeException('The configured database is not guarded.');
        }
        if ((string) ($configured['host'] ?? '') !== $testEnvironment['host']
            || (string) ($configured['port'] ?? '') !== (string) $testEnvironment['port']
            || (string) ($configured['username'] ?? '') !== $testEnvironment['username']
            || (string) ($configured['password'] ?? '') !== $testEnvironment['password']) {
            throw new RuntimeException('The configured credentials are not the guarded test credentials.');
        }

        $connection = $this->database->connection($connectionName);
        if ($connection->getDriverName() !== 'mysql'
            || $connection->getConfig('database') !== $database) {
            throw new RuntimeException('The resolved database is not guarded.');
        }

        $actual = $connection->selectOne('SELECT DATABASE() AS database_name', [], false);
        if (! is_object($actual) || ($actual->database_name ?? null) !== $database) {
            throw new RuntimeException('The actual database is not guarded.');
        }

        return $connection;
    }

    private function lockBaselineRole(Connection $connection): int
    {
        $baselineRoles = $this->rows(
            $connection,
            'SELECT ma_vt, ten_vt FROM vai_tro WHERE BINARY ky_hieu = BINARY ? FOR UPDATE',
            [self::BASELINE_ROLE_SYMBOL],
        );
        if (count($baselineRoles) !== 1
            || mb_strtolower(trim((string) $baselineRoles[0]->ten_vt), 'UTF-8') !== mb_strtolower(self::BASELINE_ROLE_NAME, 'UTF-8')) {
            throw new RuntimeException('Baseline role data is invalid.');
        }

        if ((int) $connection->selectOne(
            'SELECT COUNT(*) AS total FROM vai_tro_quyen WHERE ma_vt = ?',
            [(int) $baselineRoles[0]->ma_vt],
            false,
        )->total !== 0) {
            throw new RuntimeException('Baseline role data is invalid.');
        }

        return (int) $baselineRoles[0]->ma_vt;
    }

    /** @return array{department: ?int, position: ?int, role: ?int, status: int, permissions: array<string, int>} */
    private function preflight(Connection $connection, array $input, int $baselineRole): array
    {
        $departments = $this->rows($connection, 'SELECT ma_pb, ten_pb FROM phong_ban WHERE BINARY LOWER(TRIM(ten_pb)) = BINARY LOWER(?)', [$input['department']]);
        if (count($departments) > 1) {
            throw new RuntimeException('Department data is ambiguous.');
        }

        $positions = $this->rows($connection, 'SELECT ma_cv, ten_cv, he_so_phu_cap FROM chuc_vu WHERE BINARY LOWER(TRIM(ten_cv)) = BINARY LOWER(?)', [$input['position']]);
        if (count($positions) > 1) {
            throw new RuntimeException('Position data is ambiguous.');
        }
        if ($positions !== [] && number_format((float) $positions[0]->he_so_phu_cap, 2, '.', '') !== $input['position-allowance']) {
            throw new RuntimeException('Position allowance does not match the reused position.');
        }

        $roles = $this->rows($connection, 'SELECT ma_vt, ten_vt, ky_hieu FROM vai_tro WHERE BINARY LOWER(TRIM(ten_vt)) = BINARY LOWER(?)', [$input['role']]);
        if (count($roles) > 1) {
            throw new RuntimeException('Role data is ambiguous.');
        }
        if ($roles !== [] && mb_strtoupper(trim((string) ($roles[0]->ky_hieu ?? '')), 'UTF-8') === self::BASELINE_ROLE_SYMBOL) {
            throw new RuntimeException('The baseline role cannot be reused.');
        }

        if ($baselineRole <= 0) {
            throw new RuntimeException('Baseline role data is invalid.');
        }
        if (count($this->rows($connection, 'SELECT ma_vt FROM vai_tro WHERE BINARY LOWER(TRIM(ten_vt)) = BINARY LOWER(?)', [self::BASELINE_ROLE_NAME])) !== 1) {
            throw new RuntimeException('Baseline role data is ambiguous.');
        }

        $statusRows = $this->rows($connection, 'SELECT ma_tt, ky_hieu FROM trang_thai_lam_viec');
        $statuses = [];
        foreach ($statusRows as $row) {
            $symbol = mb_strtoupper(trim((string) ($row->ky_hieu ?? '')), 'UTF-8');
            if ($symbol === '' || isset($statuses[$symbol])) {
                throw new RuntimeException('Status data is ambiguous.');
            }
            $statuses[$symbol] = (int) $row->ma_tt;
        }
        if (! isset($statuses[self::WORKING_STATUS_SYMBOL])) {
            throw new RuntimeException('Working status is missing.');
        }

        $permissionRows = $this->rows($connection, 'SELECT ma_quyen, ky_hieu_quyen FROM quyen');
        $permissions = [];
        foreach ($permissionRows as $row) {
            $symbol = mb_strtoupper(trim((string) ($row->ky_hieu_quyen ?? '')), 'UTF-8');
            if ($symbol === '' || isset($permissions[$symbol])) {
                throw new RuntimeException('Permission data is ambiguous.');
            }
            $permissions[$symbol] = (int) $row->ma_quyen;
        }
        foreach (self::EMPLOYEE_PERMISSIONS as $symbol) {
            if (! isset($permissions[$symbol])) {
                throw new RuntimeException('Required permission is missing.');
            }
        }

        $role = $roles === [] ? null : (int) $roles[0]->ma_vt;
        if ($role !== null) {
            $this->assertRolePermissionsSubset($connection, $role, $permissions);
        }

        if ((int) $connection->selectOne(
            'SELECT COUNT(*) AS total FROM nhan_vien WHERE LOWER(TRIM(email)) = LOWER(?)',
            [$input['admin-email']],
            false,
        )->total !== 0
            || (int) $connection->selectOne(
                'SELECT COUNT(*) AS total FROM nhan_vien WHERE TRIM(cccd) = ?',
                [$input['admin-cccd']],
                false,
            )->total !== 0) {
            throw new RuntimeException('Duplicate employee identity.');
        }

        return [
            'department' => $departments === [] ? null : (int) $departments[0]->ma_pb,
            'position' => $positions === [] ? null : (int) $positions[0]->ma_cv,
            'role' => $role,
            'status' => $statuses[self::WORKING_STATUS_SYMBOL],
            'permissions' => array_intersect_key($permissions, array_flip(self::EMPLOYEE_PERMISSIONS)),
        ];
    }

    /** @param array{department: ?int, position: ?int, role: ?int, status: int, permissions: array<string, int>} $references */
    private function mutate(Connection $connection, array $input, array $references): string
    {
        $department = $references['department'] ?? $this->createDepartment($connection, $input['department']);
        $position = $references['position'] ?? $this->createPosition($connection, $input['position'], $input['position-allowance']);
        $role = $references['role'] ?? $this->createRole($connection, $input['role']);

        $mapped = $this->rolePermissionRows($connection, $role);
        $mappedSymbols = [];
        foreach ($mapped as $row) {
            $symbol = mb_strtoupper(trim((string) ($row->ky_hieu_quyen ?? '')), 'UTF-8');
            if ($symbol === '' || isset($mappedSymbols[$symbol])) {
                throw new RuntimeException('Role permission data is ambiguous.');
            }
            $mappedSymbols[$symbol] = true;
        }
        foreach ($references['permissions'] as $symbol => $permission) {
            if (! isset($mappedSymbols[$symbol])) {
                $connection->statement('CALL sp_vai_tro_quyen_them(?, ?)', [$role, $permission]);
            }
        }
        $this->assertExactRolePermissions($connection, $role, $references['permissions']);

        $maNv = $this->service->create([
            'ho_ten' => $input['admin-name'],
            'ngay_sinh' => $input['birth-date'],
            'gioi_tinh' => $input['gender'],
            'sdt' => $input['admin-phone'],
            'email' => $input['admin-email'],
            'ngay_vao_lam' => $input['start-date'],
            'ma_pb' => $department,
            'ma_cv' => $position,
            'dan_toc' => $input['ethnicity'],
            'cccd' => $input['admin-cccd'],
            'noi_cap_cccd' => $input['cccd-place'],
            'hoc_van' => $input['education'],
            'ma_tt' => $references['status'],
            'dia_chi_cu_the' => $input['address-line'],
            'phuong_xa' => $input['ward'],
            'quan_huyen' => $input['district'],
            'tinh_thanh' => $input['province'],
        ]);

        $this->repository->assignRoleForBootstrap($maNv, $role);

        return $maNv;
    }

    /** @param array<string, int> $allPermissions */
    private function assertRolePermissionsSubset(Connection $connection, int $role, array $allPermissions): void
    {
        $expected = array_intersect_key($allPermissions, array_flip(self::EMPLOYEE_PERMISSIONS));
        foreach ($this->rolePermissionRows($connection, $role) as $row) {
            $symbol = mb_strtoupper(trim((string) ($row->ky_hieu_quyen ?? '')), 'UTF-8');
            $permissionId = (int) ($row->ma_quyen ?? 0);
            if (! isset($expected[$symbol]) || $expected[$symbol] !== $permissionId) {
                throw new RuntimeException('Role permission data is outside the approved set.');
            }
        }
    }

    /** @param array<string, int> $permissions */
    private function assertExactRolePermissions(Connection $connection, int $role, array $permissions): void
    {
        $expected = array_intersect_key($permissions, array_flip(self::EMPLOYEE_PERMISSIONS));
        $actual = [];
        foreach ($this->rolePermissionRows($connection, $role) as $row) {
            $symbol = mb_strtoupper(trim((string) ($row->ky_hieu_quyen ?? '')), 'UTF-8');
            $permissionId = (int) ($row->ma_quyen ?? 0);
            if ($symbol === '' || isset($actual[$symbol])) {
                throw new RuntimeException('Role permission data is ambiguous.');
            }
            $actual[$symbol] = $permissionId;
        }

        ksort($expected);
        ksort($actual);
        if (count($actual) !== count($expected) || $actual !== $expected) {
            throw new RuntimeException('Role permissions do not match the approved set.');
        }
    }

    /** @return list<object> */
    private function rolePermissionRows(Connection $connection, int $role): array
    {
        return $this->rows(
            $connection,
            'SELECT q.ky_hieu_quyen, vtq.ma_quyen
             FROM vai_tro_quyen vtq LEFT JOIN quyen q ON q.ma_quyen = vtq.ma_quyen
             WHERE vtq.ma_vt = ?',
            [$role],
        );
    }

    private function createDepartment(Connection $connection, string $name): int
    {
        $connection->statement('CALL sp_phong_ban_them(?)', [$name]);
        $rows = $this->rows($connection, 'SELECT ma_pb FROM phong_ban WHERE BINARY LOWER(TRIM(ten_pb)) = BINARY LOWER(?)', [$name]);
        if (count($rows) !== 1) {
            throw new RuntimeException('Department creation result is ambiguous.');
        }

        return (int) $rows[0]->ma_pb;
    }

    private function createPosition(Connection $connection, string $name, string $allowance): int
    {
        $connection->statement('CALL sp_chuc_vu_them(?, ?)', [$name, $allowance]);
        $rows = $this->rows($connection, 'SELECT ma_cv, he_so_phu_cap FROM chuc_vu WHERE BINARY LOWER(TRIM(ten_cv)) = BINARY LOWER(?)', [$name]);
        if (count($rows) !== 1
            || number_format((float) $rows[0]->he_so_phu_cap, 2, '.', '') !== $allowance) {
            throw new RuntimeException('Position creation result is ambiguous.');
        }

        return (int) $rows[0]->ma_cv;
    }

    private function createRole(Connection $connection, string $name): int
    {
        $connection->statement('CALL sp_vai_tro_them(?, ?)', [$name, null]);
        $rows = $this->rows($connection, 'SELECT ma_vt, ky_hieu FROM vai_tro WHERE BINARY LOWER(TRIM(ten_vt)) = BINARY LOWER(?)', [$name]);
        if (count($rows) !== 1
            || mb_strtoupper(trim((string) ($rows[0]->ky_hieu ?? '')), 'UTF-8') === self::BASELINE_ROLE_SYMBOL) {
            throw new RuntimeException('Role creation result is ambiguous.');
        }

        return (int) $rows[0]->ma_vt;
    }

    /** @return list<object> */
    private function rows(Connection $connection, string $sql, array $bindings = []): array
    {
        return $connection->select($sql, $bindings, false);
    }
}
