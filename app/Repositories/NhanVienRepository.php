<?php

namespace App\Repositories;

use App\Contracts\NhanVienRepositoryContract;
use App\Enums\NhanVienRemovalAction;
use App\Exceptions\NhanVienDomainException;
use App\Models\NhanVien;
use App\Support\NhanVienProcedureExceptionMapper;
use Carbon\CarbonImmutable;
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

    public function update(string $maNv, array $profile): void
    {
        $this->databaseOperation(function () use ($maNv, $profile): void {
            $this->call(
                $this->connection(),
                'CALL sp_nhan_vien_sua(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $maNv,
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
                ],
            );
        });
    }

    public function replaceAvatarPath(string $maNv, ?string $newPath): ?string
    {
        return $this->databaseOperation(function () use ($maNv, $newPath): ?string {
            $connection = $this->connection();
            $connection->statement('SET @nv_anh_cu = NULL');
            $this->call(
                $connection,
                'CALL sp_nhan_vien_cap_nhat_anh(?, ?, @nv_anh_cu)',
                [$maNv, $newPath],
            );
            $result = $connection->selectOne('SELECT @nv_anh_cu AS anh_cu', [], false);
            $oldPath = is_object($result) ? ($result->anh_cu ?? null) : null;

            return is_string($oldPath) ? $oldPath : null;
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

    /**
     * Keep SET, CALL and OUT reads on one write PDO so a procedure's OUT state
     * cannot be read from a different pooled session.
     *
     * @return array{action: NhanVienRemovalAction, avatar_path: ?string}
     */
    public function removeOrTerminate(string $maNv, CarbonImmutable $date): array
    {
        return $this->databaseOperation(function () use ($maNv, $date): array {
            $connection = $this->connection();
            $connection->statement('SET @nv_hanh_dong = NULL');
            $connection->statement('SET @nv_anh_cu = NULL');
            $this->call(
                $connection,
                'CALL sp_nhan_vien_xoa_hoac_nghi_viec(?, ?, @nv_hanh_dong, @nv_anh_cu)',
                [$maNv, $date->toDateString()],
            );

            $actionRow = $connection->selectOne(
                'SELECT @nv_hanh_dong AS hanh_dong',
                [],
                false,
            );
            $actionValue = is_object($actionRow) && property_exists($actionRow, 'hanh_dong')
                ? $actionRow->hanh_dong
                : null;
            $avatarRow = $connection->selectOne(
                'SELECT @nv_anh_cu AS anh_cu',
                [],
                false,
            );
            $avatarPath = is_object($avatarRow) && property_exists($avatarRow, 'anh_cu')
                ? $avatarRow->anh_cu
                : null;
            $action = is_string($actionValue) ? NhanVienRemovalAction::tryFrom($actionValue) : null;

            if ($action === null) {
                throw new NhanVienDomainException(
                    'Không thể xử lý yêu cầu nhân viên. Vui lòng thử lại.',
                    'NV_DATABASE_ERROR',
                );
            }

            return [
                'action' => $action,
                'avatar_path' => is_string($avatarPath) ? $avatarPath : null,
            ];
        });
    }

    public function resetPasswordHash(string $maNv, string $hash): void
    {
        $this->databaseOperation(function () use ($maNv, $hash): void {
            $this->call(
                $this->connection(),
                'CALL sp_nhan_vien_dat_lai_mat_khau(?, ?)',
                [$maNv, $hash],
            );
        });
    }

    public function rehashAuthenticatedPassword(string $maNv, string $currentHash, string $newHash): void
    {
        $this->databaseOperation(function () use ($maNv, $currentHash, $newHash): void {
            $this->call(
                $this->connection(),
                'CALL sp_nhan_vien_cap_nhat_hash_xac_thuc(?, ?, ?)',
                [$maNv, $currentHash, $newHash],
            );
        });
    }

    public function findAccountByIdentifier(string $identifier): ?NhanVien
    {
        return $this->databaseOperation(function () use ($identifier): ?NhanVien {
            $sets = $this->call(
                $this->connection(),
                'CALL sp_nhan_vien_lay_tai_khoan_dang_nhap(?)',
                [$identifier],
            );
            $row = $sets[0][0] ?? null;

            return is_object($row) ? NhanVien::fromAuthProcedureRow($row) : null;
        });
    }

    /** @return list<string> */
    public function permissionSymbols(string $maNv): array
    {
        return $this->databaseOperation(function () use ($maNv): array {
            $rows = $this->call(
                $this->connection(),
                'CALL sp_quyen_lay_theo_ma_nhan_vien(?)',
                [$maNv],
            )[0] ?? [];
            $symbols = [];

            foreach ($rows as $row) {
                $value = is_object($row)
                    ? ($row->ky_hieu_quyen ?? null)
                    : (is_array($row) ? ($row['ky_hieu_quyen'] ?? null) : null);
                if (! is_string($value)) {
                    throw new NhanVienDomainException(
                        'Dữ liệu quyền nhân viên không hợp lệ.',
                        'NV_PERMISSION_RESULT_INVALID',
                    );
                }

                $symbol = strtoupper(trim($value));
                if (preg_match('/\A[A-Z][A-Z0-9_]{0,99}\z/', $symbol) !== 1) {
                    throw new NhanVienDomainException(
                        'Dữ liệu quyền nhân viên không hợp lệ.',
                        'NV_PERMISSION_RESULT_INVALID',
                    );
                }

                $symbols[$symbol] = true;
            }

            $symbols = array_keys($symbols);
            sort($symbols, SORT_STRING);

            return $symbols;
        });
    }

    /**
     * @internal Bootstrap-only seam. Never call this from an HTTP/service flow.
     */
    public function assignRoleForBootstrap(string $maNv, int $maVt): void
    {
        $this->databaseOperation(function () use ($maNv, $maVt): void {
            // WHY: role assignment is one guarded CALL on the write PDO; the caller owns the outer transaction.
            $this->call(
                $this->connection(),
                'CALL sp_nhan_vien_gan_vai_tro_noi_bo(?, ?)',
                [$maNv, $maVt],
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
