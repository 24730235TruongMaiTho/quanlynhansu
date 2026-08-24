<?php

namespace App\Console\Commands;

use App\Contracts\NhanVienRepositoryContract;
use App\Contracts\NhanVienServiceContract;
use App\Enums\NhanVienPermission;
use App\Enums\NhanVienRole;
use App\Enums\NhanVienStatus;
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
        {--role= : Existing fixed role name (must have employee permissions)}
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
            $maNv = $connection->transaction(function () use ($connection, $input): string {
                $references = $this->preflight($connection, $input);

                return $this->mutate($connection, $input, $references);
            });

            $this->line('Ma nhan vien demo: '.$maNv);
            $this->line('Tai khoan dung quy uoc mat khau demo nhom3@{nam}.');

            return self::SUCCESS;
        } catch (Throwable) {
            $this->error('Khong the khoi tao du lieu demo.');

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

        $limits = [
            'department' => 100, 'position' => 100, 'role' => 100, 'admin-name' => 50,
            'education' => 50, 'ethnicity' => 50, 'cccd-place' => 50, 'address-line' => 255,
            'ward' => 100, 'district' => 100, 'province' => 100,
        ];
        $input = [];
        foreach ($limits as $option => $limit) {
            $input[$option] = $this->requiredText($option, $limit);
        }

        $input['admin-email'] = mb_strtolower($this->requiredText('admin-email', 100), 'UTF-8');
        if (filter_var($input['admin-email'], FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException('Invalid email.');
        }
        $input['admin-phone'] = $this->requiredText('admin-phone', 15);
        $input['admin-cccd'] = $this->requiredText('admin-cccd', 12);
        if (preg_match('/\A0[0-9]{9}\z/', $input['admin-phone']) !== 1
            || preg_match('/\A[0-9]{12}\z/', $input['admin-cccd']) !== 1) {
            throw new RuntimeException('Invalid identity data.');
        }
        $input['birth-date'] = $this->dateOption('birth-date');
        $input['start-date'] = $this->dateOption('start-date');
        if ($input['birth-date'] >= $input['start-date']
            || CarbonImmutable::parse($input['birth-date'])->diffInYears(CarbonImmutable::parse($input['start-date'])) < 18) {
            throw new RuntimeException('Invalid employee age or dates.');
        }
        $input['gender'] = (int) $this->requiredText('gender', 1);
        if (! in_array($input['gender'], [0, 1], true)) {
            throw new RuntimeException('Invalid gender.');
        }
        $allowance = $this->requiredText('position-allowance', 6);
        if (preg_match('/\A(?:[0-9]{1,3})(?:\.[0-9]{1,2})?\z/', $allowance) !== 1 || (float) $allowance > 999.99) {
            throw new RuntimeException('Invalid position allowance.');
        }
        $input['position-allowance'] = number_format((float) $allowance, 2, '.', '');

        return $input;
    }

    private function requiredText(string $option, int $maxLength): string
    {
        $value = $this->option($option);
        if (! is_string($value) || ($value = trim($value)) === '' || mb_strlen($value, 'UTF-8') > $maxLength) {
            throw new RuntimeException('A required option is invalid.');
        }

        return $value;
    }

    private function dateOption(string $option): string
    {
        $value = $this->requiredText($option, 10);
        $date = CarbonImmutable::createFromFormat('!Y-m-d', $value);
        $errors = DateTime::getLastErrors();
        if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) || $date->format('Y-m-d') !== $value) {
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
        $environment = DisposableMariaDbGuard::environment();
        $name = $this->database->getDefaultConnection();
        $configured = config('database.connections.'.$name);
        if (! is_array($configured) || ($configured['driver'] ?? null) !== 'mysql'
            || ($configured['database'] ?? null) !== $database
            || ($configured['url'] ?? '') !== ''
            || (getenv('DB_URL') ?: '') !== '') {
            throw new RuntimeException('The configured database is not guarded.');
        }
        if ((string) ($configured['host'] ?? '') !== $environment['host']
            || (string) ($configured['port'] ?? '') !== (string) $environment['port']
            || (string) ($configured['username'] ?? '') !== $environment['username']
            || (string) ($configured['password'] ?? '') !== $environment['password']) {
            throw new RuntimeException('The configured credentials are not guarded.');
        }
        $connection = $this->database->connection($name);
        $actual = $connection->selectOne('SELECT DATABASE() AS database_name', [], false);
        if ($connection->getDriverName() !== 'mysql' || ($actual->database_name ?? null) !== $database) {
            throw new RuntimeException('The actual database is not guarded.');
        }

        return $connection;
    }

    /** @return array{department: ?int, position: ?int, role: int, status: int, permissions: list<int>} */
    private function preflight(Connection $connection, array $input): array
    {
        $department = $connection->table('phong_ban')->whereRaw('LOWER(TRIM(ten_pb)) = LOWER(?)', [$input['department']])->first();
        $position = $connection->table('chuc_vu')->whereRaw('LOWER(TRIM(ten_cv)) = LOWER(?)', [$input['position']])->first();
        if ($position !== null && number_format((float) $position->he_so_phu_cap, 2, '.', '') !== $input['position-allowance']) {
            throw new RuntimeException('Position allowance does not match the reused position.');
        }
        $role = $connection->table('vai_tro')->whereRaw('LOWER(TRIM(ten_vt)) = LOWER(?)', [$input['role']])->first();
        if ($role === null || ! in_array((int) $role->ma_vt, [NhanVienRole::SuperAdmin->value, NhanVienRole::HumanResources->value], true)) {
            throw new RuntimeException('Demo role must be a fixed administrator role.');
        }
        $permissions = $connection->table('vai_tro_quyen')->where('ma_vt', $role->ma_vt)->pluck('ma_quyen')->map(fn ($id): int => (int) $id)->all();
        $required = array_map(fn (NhanVienPermission $permission): int => $permission->id(), NhanVienPermission::cases());
        sort($permissions);
        $expected = $required;
        sort($expected);
        if (! array_diff($expected, $permissions)) {
            // The role has the minimum employee contract.
        } else {
            throw new RuntimeException('Required employee permissions are missing.');
        }
        if ($connection->table('nhan_vien')->whereRaw('LOWER(TRIM(email)) = LOWER(?)', [$input['admin-email']])->exists()
            || $connection->table('nhan_vien')->where('cccd', $input['admin-cccd'])->exists()) {
            throw new RuntimeException('Duplicate employee identity.');
        }

        return [
            'department' => $department?->ma_pb === null ? null : (int) $department->ma_pb,
            'position' => $position?->ma_cv === null ? null : (int) $position->ma_cv,
            'role' => (int) $role->ma_vt,
            'status' => NhanVienStatus::Working->value,
            'permissions' => $required,
        ];
    }

    /** @param array{department: ?int, position: ?int, role: int, status: int, permissions: list<int>} $references */
    private function mutate(Connection $connection, array $input, array $references): string
    {
        $department = $references['department'] ?? (int) $connection->table('phong_ban')->insertGetId(['ten_pb' => $input['department']]);
        $position = $references['position'] ?? (int) $connection->table('chuc_vu')->insertGetId([
            'ten_cv' => $input['position'], 'he_so_phu_cap' => $input['position-allowance'],
        ]);

        $maNv = $this->service->create([
            'ho_ten' => $input['admin-name'], 'ngay_sinh' => $input['birth-date'], 'gioi_tinh' => $input['gender'],
            'sdt' => $input['admin-phone'], 'email' => $input['admin-email'], 'ngay_vao_lam' => $input['start-date'],
            'ma_pb' => $department, 'ma_cv' => $position, 'dan_toc' => $input['ethnicity'], 'cccd' => $input['admin-cccd'],
            'noi_cap_cccd' => $input['cccd-place'], 'hoc_van' => $input['education'], 'ma_tt' => $references['status'],
            'dia_chi_cu_the' => $input['address-line'], 'phuong_xa' => $input['ward'], 'quan_huyen' => $input['district'],
            'tinh_thanh' => $input['province'],
        ]);
        $this->repository->assignRoleForBootstrap($maNv, $references['role']);

        return $maNv;
    }
}
