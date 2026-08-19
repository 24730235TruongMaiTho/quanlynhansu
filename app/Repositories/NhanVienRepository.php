<?php

namespace App\Repositories;

use App\Contracts\NhanVienRepositoryContract;
use App\Exceptions\NhanVienDomainException;
use App\Support\NhanVienProcedureExceptionMapper;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\QueryException;
use Illuminate\Pagination\LengthAwarePaginator;

final class NhanVienRepository implements NhanVienRepositoryContract
{
    public function __construct(
        private DatabaseManager $database,
        private NhanVienProcedureExceptionMapper $exceptions,
    ) {}

    public function paginate(array $filters): LengthAwarePaginator
    {
        return $this->databaseOperation(function () use ($filters): LengthAwarePaginator {
            $connection = $this->connection();
            $connection->statement('SET @nv_tong_so = 0');
            $sets = $this->call(
                $connection,
                'CALL sp_nhan_vien_danh_sach_phan_trang(?, ?, ?, ?, ?, ?, @nv_tong_so)',
                [
                    $filters['tu_khoa'],
                    $filters['ma_pb'],
                    $filters['ma_cv'],
                    $filters['ma_tt'],
                    $filters['page'],
                    $filters['so_dong'],
                ],
            );
            $total = (int) $connection
                ->selectOne('SELECT @nv_tong_so AS total', [], false)->total;

            return $this->paginator(
                $sets[0] ?? [],
                $total,
                $filters['so_dong'],
                $filters['page'],
            );
        });
    }

    public function paginateAttendance(array $filters): LengthAwarePaginator
    {
        return $this->databaseOperation(function () use ($filters): LengthAwarePaginator {
            $connection = $this->connection();
            $connection->statement('SET @nv_tong_so_cham_cong = 0');
            $sets = $this->call(
                $connection,
                'CALL sp_cham_cong_nhan_vien_phan_trang(?, ?, ?, ?, ?, ?, @nv_tong_so_cham_cong)',
                [
                    $filters['tu_khoa'],
                    $filters['ma_pb'],
                    $filters['thang'],
                    $filters['nam'],
                    $filters['page'],
                    $filters['so_dong'],
                ],
            );
            $total = (int) $connection
                ->selectOne('SELECT @nv_tong_so_cham_cong AS total', [], false)->total;

            return $this->paginator(
                $sets[0] ?? [],
                $total,
                $filters['so_dong'],
                $filters['page'],
            );
        });
    }

    public function find(string $maNv): ?object
    {
        return $this->databaseOperation(function () use ($maNv): ?object {
            $sets = $this->call(
                $this->connection(),
                'CALL sp_nhan_vien_chi_tiet(?)',
                [$maNv],
            );

            return $sets[0][0] ?? null;
        });
    }

    public function create(array $profile, string $passwordHash, ?string $avatarPath): string
    {
        return $this->databaseOperation(function () use ($profile, $passwordHash, $avatarPath): string {
            $connection = $this->connection();
            $connection->statement('SET @nv_ma = NULL');
            $this->call(
                $connection,
                'CALL sp_nhan_vien_them(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, @nv_ma)',
                [
                    $profile['ho_ten'],
                    $profile['ngay_sinh'],
                    $profile['gioi_tinh'],
                    $profile['sdt'],
                    $profile['email'],
                    $profile['ngay_vao_lam'],
                    $profile['ma_pb'],
                    $profile['ma_cv'],
                    $profile['dan_toc'],
                    $profile['cccd'],
                    $profile['noi_cap_cccd'],
                    $profile['hoc_van'],
                    $profile['ma_tt'],
                    $passwordHash,
                    $avatarPath,
                ],
            );
            $result = $connection->selectOne('SELECT @nv_ma AS ma_nv', [], false);
            $maNv = is_object($result) && is_string($result->ma_nv ?? null)
                ? $result->ma_nv
                : '';

            if (preg_match('/\ANV[0-9]{3}\z/', $maNv) !== 1) {
                throw new NhanVienDomainException(
                    'Không thể tạo nhân viên. Vui lòng thử lại.',
                    'NV_CREATE_RESULT_INVALID',
                );
            }

            return $maNv;
        });
    }

    public function upsertAddress(string $maNv, array $address): void
    {
        $this->databaseOperation(function () use ($maNv, $address): void {
            $this->call(
                $this->connection(),
                'CALL sp_dia_chi_nhan_vien_luu(?, ?, ?, ?, ?)',
                [
                    $maNv,
                    $address['dia_chi_cu_the'],
                    $address['phuong_xa'],
                    $address['quan_huyen'],
                    $address['tinh_thanh'],
                ],
            );
        });
    }

    public function lookups(): array
    {
        return $this->databaseOperation(function (): array {
            $connection = $this->connection();

            return [
                'phong_ban' => $this->call($connection, 'CALL sp_phong_ban_danh_sach()', [])[0] ?? [],
                'chuc_vu' => $this->call($connection, 'CALL sp_chuc_vu_danh_sach()', [])[0] ?? [],
                'trang_thai' => $this->call($connection, 'CALL sp_trang_thai_lam_viec_danh_sach()', [])[0] ?? [],
            ];
        });
    }

    private function connection(): Connection
    {
        return $this->database->connection();
    }

    private function call(Connection $connection, string $sql, array $bindings): array
    {
        return $connection->selectResultSets($sql, $bindings, false);
    }

    private function paginator(array $items, int $total, int $perPage, int $page): LengthAwarePaginator
    {
        return new LengthAwarePaginator(
            collect($items),
            $total,
            $perPage,
            $page,
            ['pageName' => 'page'],
        );
    }

    private function databaseOperation(callable $operation): mixed
    {
        try {
            return $operation();
        } catch (QueryException $exception) {
            throw $this->exceptions->map($exception);
        }
    }
}
