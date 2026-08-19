<?php

namespace Tests\Integration\MariaDb;

use App\Contracts\NhanVienRepositoryContract;
use App\Exceptions\NhanVienDomainException;
use App\Repositories\NhanVienRepository;
use App\Support\NhanVienProcedureExceptionMapper;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\QueryException;
use Mockery;
use PDO;
use PDOException;

class NhanVienRepositoryReadTest extends MariaDbTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->runSql(base_path('tests/Fixtures/MariaDb/employee_legacy_schema.sql'));
        $this->runSql(base_path('database/sql/employee/2026_08_12_001_schema.sql'));

        $readScript = base_path('database/sql/employee/2026_08_12_002_read_routines.sql');
        if (is_file($readScript)) {
            $this->runSql($readScript);
        }

        $this->seedEmployee();
    }

    public function test_provider_binds_the_repository_contract_to_the_concrete_repository(): void
    {
        $this->assertInstanceOf(NhanVienRepository::class, $this->app->make(NhanVienRepositoryContract::class));
    }

    public function test_read_methods_use_the_write_pdo_for_set_call_and_out_and_return_contract_shapes(): void
    {
        $connection = $this->app->make(DatabaseManager::class)->connection();
        $readPdo = $this->newReadPdo();
        $connection->setReadPdo($readPdo);

        $this->assertNotSame(spl_object_id($connection->getPdo()), spl_object_id($connection->getReadPdo()));
        $connection->getPdo()->exec("SET @repository_session_probe = 'write'");
        $connection->getReadPdo()->exec("SET @repository_session_probe = 'read'");

        $repository = $this->app->make(NhanVienRepositoryContract::class);
        $filters = [
            'tu_khoa' => null,
            'ma_pb' => null,
            'ma_cv' => null,
            'ma_tt' => null,
            'page' => 1,
            'so_dong' => 20,
        ];
        $employees = $repository->paginate($filters);

        $this->assertSame(1, $employees->total());
        $this->assertSame('NV001', $employees->items()[0]->ma_nv);
        $this->assertSame('write', $connection->selectOne('SELECT @repository_session_probe AS probe', [], false)->probe);
        $this->assertSame('read', $connection->selectOne('SELECT @repository_session_probe AS probe', [], true)->probe);

        $attendance = $repository->paginateAttendance([
            'tu_khoa' => null,
            'ma_pb' => null,
            'thang' => 8,
            'nam' => 2026,
            'page' => 1,
            'so_dong' => 20,
        ]);
        $this->assertSame(1, $attendance->total());
        $this->assertSame(1.0, (float) $attendance->items()[0]->so_ngay_cham_cong);

        $this->assertSame('NV001', $repository->find('NV001')?->ma_nv);
        $this->assertNull($repository->find('NV999'));

        $lookups = $repository->lookups();
        $this->assertSame(['phong_ban', 'chuc_vu', 'trang_thai'], array_keys($lookups));
        $this->assertSame(['ma_pb', 'ten_pb', 'so_nhan_vien'], array_keys((array) $lookups['phong_ban'][0]));
        $this->assertSame(['ma_cv', 'ten_cv', 'he_so_phu_cap'], array_keys((array) $lookups['chuc_vu'][0]));
        $this->assertSame(['ma_tt', 'ky_hieu', 'ten_tt'], array_keys((array) $lookups['trang_thai'][0]));
    }

    public function test_query_exception_at_set_is_mapped_without_leaking_sql(): void
    {
        $this->assertMappedFailureAt('set');
    }

    public function test_query_exception_at_call_is_mapped_without_leaking_sql(): void
    {
        $this->assertMappedFailureAt('call');
    }

    public function test_query_exception_at_out_select_is_mapped_without_leaking_sql(): void
    {
        $this->assertMappedFailureAt('out');
    }

    private function seedEmployee(): void
    {
        $this->pdo()->exec("INSERT INTO phong_ban (ten_pb) VALUES ('Kỹ thuật')");
        $department = (int) $this->pdo()->lastInsertId();
        $this->pdo()->exec("INSERT INTO chuc_vu (ten_cv, he_so_phu_cap) VALUES ('Lập trình viên', 0.20)");
        $position = (int) $this->pdo()->lastInsertId();
        $status = (int) $this->pdo()->query(
            "SELECT ma_tt FROM trang_thai_lam_viec WHERE BINARY ky_hieu = BINARY 'DANG_LAM'"
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
            'NV001', 'Nguyễn An', '1990-01-01', 1, '0900000001', 'an@example.test', '2020-01-01',
            $department, $position, 'Kinh', '123456789001', 'TP HCM', 'Đại học', $status,
            str_repeat('a', 60), $role,
        ]);
        $this->pdo()->exec(
            "INSERT INTO cham_cong (ma_nv, ngay_lam, so_gio_lam, vao_muon, ve_som)
             VALUES ('NV001', '2026-08-01', 8, 0, 0)"
        );
    }

    private function newReadPdo(): PDO
    {
        $config = config('database.connections.employee_test');

        return new PDO(
            sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                $config['host'],
                $config['port'],
                $config['database'],
            ),
            $config['username'],
            $config['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );
    }

    private function assertMappedFailureAt(string $stage): void
    {
        $connection = Mockery::mock(Connection::class);
        $database = Mockery::mock(DatabaseManager::class);
        $database->shouldReceive('connection')->andReturn($connection);

        $exception = new QueryException(
            'mysql',
            "CALL secret_{$stage}('sensitive-sql')",
            [],
            new PDOException('SQLSTATE[HY000]: General error: 1234 internal database failure'),
        );

        if ($stage === 'set') {
            $connection->shouldReceive('statement')->once()->andThrow($exception);
        } else {
            $connection->shouldReceive('statement')->once()->andReturnTrue();
            if ($stage === 'call') {
                $connection->shouldReceive('selectResultSets')
                    ->once()
                    ->with(Mockery::type('string'), Mockery::type('array'), false)
                    ->andThrow($exception);
            } else {
                $connection->shouldReceive('selectResultSets')
                    ->once()
                    ->with(Mockery::type('string'), Mockery::type('array'), false)
                    ->andReturn([[(object) ['ma_nv' => 'NV001']]]);
                $connection->shouldReceive('selectOne')
                    ->once()
                    ->with('SELECT @nv_tong_so AS total', [], false)
                    ->andThrow($exception);
            }
        }

        $repository = new NhanVienRepository($database, new NhanVienProcedureExceptionMapper());

        try {
            $repository->paginate([
                'tu_khoa' => null,
                'ma_pb' => null,
                'ma_cv' => null,
                'ma_tt' => null,
                'page' => 1,
                'so_dong' => 20,
            ]);
            $this->fail("QueryException at {$stage} should be mapped.");
        } catch (NhanVienDomainException $mapped) {
            $this->assertSame('NV_DATABASE_ERROR', $mapped->domainCode);
            $this->assertSame('Không thể xử lý yêu cầu nhân viên. Vui lòng thử lại.', $mapped->getMessage());
            $this->assertStringNotContainsString('secret', strtolower($mapped->getMessage()));
            $this->assertStringNotContainsString('SQLSTATE', $mapped->getMessage());
        }
    }
}
