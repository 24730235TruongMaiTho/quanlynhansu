<?php

namespace App\Repositories;

use App\Contracts\NhanVienRepositoryContract;
use App\Enums\NhanVienRemovalAction;
use App\Enums\NhanVienRole;
use App\Enums\NhanVienStatus;
use App\Exceptions\NhanVienDomainException;
use App\Models\NhanVien;
use App\Support\NhanVienProcedureExceptionMapper;
use Carbon\CarbonImmutable;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Truy cập dữ liệu CRUD nhân viên theo hợp đồng 15 bảng.
 *
 * Repository dùng phép chiếu Query Builder tường minh. Lược đồ fresh không có
 * procedure, view hoặc bảng địa chỉ riêng cho nhân viên; bên gọi sở hữu
 * transaction bao quanh các lần ghi nhiều bước.
 */
final class NhanVienRepository implements NhanVienRepositoryContract
{
    private const MAX_EMPLOYEE_CODE = 99999;

    private const MAX_COUNTER_VALUE = 65535;

    private const PROFILE_COLUMNS = [
        'ho_ten', 'ngay_sinh', 'gioi_tinh', 'sdt', 'email', 'ngay_vao_lam',
        'ma_pb', 'ma_cv', 'dan_toc', 'cccd', 'noi_cap_cccd', 'hoc_van', 'ma_tt',
    ];

    private const ADDRESS_COLUMNS = [
        'dia_chi_cu_the', 'phuong_xa', 'quan_huyen', 'tinh_thanh',
    ];

    private const DEPENDENCY_TABLES = [
        'hop_dong', 'nghi_phep', 'cham_cong', 'lich_su_he_so_luong', 'luong',
    ];

    public function __construct(
        private DatabaseManager $database,
        private NhanVienProcedureExceptionMapper $exceptions,
    ) {}

    public function paginate(array $filters): LengthAwarePaginator
    {
        return $this->databaseOperation(function () use ($filters): LengthAwarePaginator {
            $filters += [
                'tu_khoa' => null,
                'ma_pb' => null,
                'ma_cv' => null,
                'ma_tt' => null,
                'page' => 1,
                'so_dong' => 15,
            ];

            $query = $this->employeeQuery();
            $this->applyEmployeeFilters($query, $filters);
            $columns = $query->columns ?? ['*'];

            return $query->orderBy('nv.ma_nv')->paginate(
                (int) $filters['so_dong'],
                $columns,
                'page',
                (int) $filters['page'],
            );
        });
    }

    public function paginateAttendance(array $filters): LengthAwarePaginator
    {
        return $this->databaseOperation(function () use ($filters): LengthAwarePaginator {
            $filters += [
                'tu_khoa' => null,
                'ma_pb' => null,
                'thang' => null,
                'nam' => null,
                'page' => 1,
                'so_dong' => 15,
            ];

            $query = $this->employeeQuery()
                ->leftJoin('cham_cong as cc', function ($join) use ($filters): void {
                    $join->on('cc.ma_nv', '=', 'nv.ma_nv');
                    if ($filters['thang'] !== null) {
                        $join->whereRaw('MONTH(cc.ngay_lam) = ?', [(int) $filters['thang']]);
                    }
                    if ($filters['nam'] !== null) {
                        $join->whereRaw('YEAR(cc.ngay_lam) = ?', [(int) $filters['nam']]);
                    }
                })
                ->addSelect([
                    DB::raw('COALESCE(SUM(CASE WHEN cc.so_gio_lam >= 8 THEN 1 WHEN cc.so_gio_lam >= 4 THEN 0.5 ELSE 0 END), 0) AS so_ngay_cham_cong'),
                    DB::raw('COALESCE(SUM(cc.so_gio_lam), 0) AS tong_gio_lam'),
                    DB::raw('COALESCE(SUM(CASE WHEN cc.vao_muon = 1 THEN 1 ELSE 0 END), 0) AS so_lan_vao_muon'),
                    DB::raw('COALESCE(SUM(CASE WHEN cc.ve_som = 1 THEN 1 ELSE 0 END), 0) AS so_lan_ve_som'),
                ])
                ->groupBy([
                    'nv.ma_nv', 'nv.ho_ten', 'nv.ngay_sinh', 'nv.gioi_tinh', 'nv.sdt', 'nv.email',
                    'nv.ngay_vao_lam', 'nv.ma_pb', 'nv.ma_cv', 'nv.dan_toc', 'nv.cccd',
                    'nv.noi_cap_cccd', 'nv.hoc_van', 'nv.ma_tt', 'nv.ma_vt', 'nv.anh_dai_dien',
                    'nv.ngay_nghi_viec', 'nv.dia_chi_cu_the', 'nv.phuong_xa', 'nv.quan_huyen', 'nv.tinh_thanh',
                    'pb.ten_pb', 'cv.ten_cv', 'cv.he_so_phu_cap', 'tt.ten_tt', 'vt.ten_vt',
                ]);

            $this->applyEmployeeFilters($query, $filters);
            $columns = $query->columns ?? ['*'];

            return $query->orderBy('nv.ma_nv')->paginate(
                (int) $filters['so_dong'],
                $columns,
                'page',
                (int) $filters['page'],
            );
        });
    }

    public function find(string $maNv): ?object
    {
        return $this->databaseOperation(fn (): ?object => $this->employeeQuery()
            ->where('nv.ma_nv', $maNv)
            ->first());
    }

    public function create(array $profile, string $passwordHash, ?string $avatarPath): string
    {
        return $this->databaseOperation(function () use ($profile, $passwordHash, $avatarPath): string {
            return $this->transactionIfNeeded(function () use ($profile, $passwordHash, $avatarPath): string {
                $connection = $this->connection();
                $counter = $connection->table('bo_dem_ma_nhan_vien')
                    ->where('ten_bo_dem', 'NHAN_VIEN')
                    ->lockForUpdate()
                    ->first();

                if ($counter === null) {
                    throw new NhanVienDomainException(
                        'Không thể cấp mã nhân viên. Vui lòng kiểm tra cấu hình dữ liệu.',
                        'NV_COUNTER_MISSING',
                    );
                }

                $issued = (int) ($counter->so_da_cap ?? -1);
                if ($issued < 0 || $issued >= self::MAX_COUNTER_VALUE) {
                    throw new NhanVienDomainException(
                        'Đã hết mã nhân viên khả dụng.',
                        'NV_COUNTER_EXHAUSTED',
                    );
                }

                $maxExisting = 0;
                foreach ($connection->table('nhan_vien')->pluck('ma_nv') as $existingCode) {
                    if (! is_string($existingCode) || preg_match('/\A[0-9]{5}\z/', $existingCode) !== 1) {
                        throw new NhanVienDomainException(
                            'Không thể cấp mã nhân viên do dữ liệu mã bị sai lệch.',
                            'NV_COUNTER_DRIFT',
                        );
                    }

                    $maxExisting = max($maxExisting, (int) $existingCode);
                }

                if ($issued < $maxExisting) {
                    throw new NhanVienDomainException(
                        'Không thể cấp mã nhân viên do bộ đếm bị sai lệch.',
                        'NV_COUNTER_DRIFT',
                    );
                }

                $next = $issued + 1;
                if ($next > self::MAX_EMPLOYEE_CODE) {
                    throw new NhanVienDomainException(
                        'Đã hết mã nhân viên khả dụng.',
                        'NV_COUNTER_EXHAUSTED',
                    );
                }

                $maNv = sprintf('%05d', $next);
                if ($connection->table('nhan_vien')->where('ma_nv', $maNv)->exists()) {
                    throw new NhanVienDomainException(
                        'Không thể cấp mã nhân viên do mã đã tồn tại.',
                        'NV_COUNTER_DRIFT',
                    );
                }

                $row = $this->profileRow($profile) + [
                    'ma_nv' => $maNv,
                    'ma_vt' => NhanVienRole::Employee->value,
                    'mat_khau' => $passwordHash,
                    'anh_dai_dien' => $avatarPath,
                    'ngay_nghi_viec' => null,
                ];
                $connection->table('nhan_vien')->insert($row);
                $connection->table('bo_dem_ma_nhan_vien')
                    ->where('ten_bo_dem', 'NHAN_VIEN')
                    ->update(['so_da_cap' => $next]);

                return $maNv;
            });
        });
    }

    public function update(string $maNv, array $profile): void
    {
        $this->databaseOperation(function () use ($maNv, $profile): void {
            $this->transactionIfNeeded(function () use ($maNv, $profile): void {
                $target = $this->connection()->table('nhan_vien')
                    ->where('ma_nv', $maNv)
                    ->lockForUpdate()
                    ->first(['ma_nv', 'ma_tt']);

                if ($target === null) {
                    throw new NhanVienDomainException('Không tìm thấy nhân viên.', 'NV_NOT_FOUND');
                }

                $profileRow = $this->profileRow($profile);
                $requestedStatus = $profileRow['ma_tt'] ?? null;
                $currentStatus = (int) $target->ma_tt;
                if ($requestedStatus !== null
                    && ! NhanVienStatus::canTransitionValue($currentStatus, (int) $requestedStatus)) {
                    throw new NhanVienDomainException(
                        'Không thể thay đổi trạng thái đã nghỉ qua thao tác cập nhật hồ sơ.',
                        'NV_STATUS_TRANSITION_FORBIDDEN',
                        'ma_tt',
                    );
                }

                if ($profileRow !== []) {
                    $this->connection()->table('nhan_vien')
                        ->where('ma_nv', $maNv)
                        ->update($profileRow);
                }
            });
        });
    }

    public function resetPassword(string $maNv, string $passwordHash): void
    {
        $this->databaseOperation(function () use ($maNv, $passwordHash): void {
            $this->transactionIfNeeded(function () use ($maNv, $passwordHash): void {
                $target = $this->connection()->table('nhan_vien')
                    ->select(['ma_nv', 'ma_tt'])
                    ->where('ma_nv', $maNv)
                    ->lockForUpdate()
                    ->first();

                if ($target === null || NhanVienStatus::isTerminalValue((int) $target->ma_tt)) {
                    throw new NhanVienDomainException('Không tìm thấy nhân viên.', 'NV_NOT_FOUND');
                }

                $this->connection()->table('nhan_vien')
                    ->where('ma_nv', $maNv)
                    ->update(['mat_khau' => $passwordHash]);
            });
        });
    }

    public function replaceAvatarPath(string $maNv, ?string $newPath): ?string
    {
        return $this->databaseOperation(function () use ($maNv, $newPath): ?string {
            return $this->transactionIfNeeded(function () use ($maNv, $newPath): ?string {
                $row = $this->connection()->table('nhan_vien')
                    ->select(['anh_dai_dien'])
                    ->where('ma_nv', $maNv)
                    ->lockForUpdate()
                    ->first();

                if ($row === null) {
                    throw new NhanVienDomainException('Không tìm thấy nhân viên.', 'NV_NOT_FOUND');
                }

                $this->connection()->table('nhan_vien')->where('ma_nv', $maNv)->update([
                    'anh_dai_dien' => $newPath,
                ]);

                return is_string($row->anh_dai_dien) ? $row->anh_dai_dien : null;
            });
        });
    }

    public function upsertAddress(string $maNv, array $address): void
    {
        $this->databaseOperation(function () use ($maNv, $address): void {
            $this->transactionIfNeeded(function () use ($maNv, $address): void {
                $target = $this->connection()->table('nhan_vien')
                    ->where('ma_nv', $maNv)
                    ->lockForUpdate()
                    ->first(['ma_nv']);

                if ($target === null) {
                    throw new NhanVienDomainException('Không tìm thấy nhân viên.', 'NV_NOT_FOUND');
                }

                $addressRow = array_intersect_key($address, array_flip(self::ADDRESS_COLUMNS));
                if ($addressRow !== []) {
                    $this->connection()->table('nhan_vien')
                        ->where('ma_nv', $maNv)
                        ->update($addressRow);
                }
            });
        });
    }

    /** @return array{action: NhanVienRemovalAction, avatar_path: ?string} */
    public function removeOrTerminate(string $maNv, CarbonImmutable $date): array
    {
        return $this->databaseOperation(function () use ($maNv, $date): array {
            return $this->transactionIfNeeded(function () use ($maNv, $date): array {
                $connection = $this->connection();
                $employee = $connection->table('nhan_vien')
                    ->select(['ma_nv', 'ma_tt', 'anh_dai_dien'])
                    ->where('ma_nv', $maNv)
                    ->lockForUpdate()
                    ->first();

                if ($employee === null) {
                    throw new NhanVienDomainException('Không tìm thấy nhân viên.', 'NV_NOT_FOUND');
                }
                $hasDependency = false;
                foreach (self::DEPENDENCY_TABLES as $table) {
                    if ($connection->table($table)->where('ma_nv', $maNv)->exists()) {
                        $hasDependency = true;
                        break;
                    }
                }

                if ($hasDependency) {
                    $connection->table('nhan_vien')->where('ma_nv', $maNv)->update([
                        'ma_tt' => NhanVienStatus::Terminated->value,
                        'ngay_nghi_viec' => $date->toDateString(),
                    ]);

                    return ['action' => NhanVienRemovalAction::Terminated, 'avatar_path' => null];
                }

                $connection->table('nhan_vien')->where('ma_nv', $maNv)->delete();

                return [
                    'action' => NhanVienRemovalAction::Deleted,
                    'avatar_path' => is_string($employee->anh_dai_dien) ? $employee->anh_dai_dien : null,
                ];
            });
        });
    }

    public function rehashAuthenticatedPassword(string $maNv, string $currentHash, string $newHash): void
    {
        $this->databaseOperation(function () use ($maNv, $currentHash, $newHash): void {
            $updated = $this->connection()->table('nhan_vien')
                ->where('ma_nv', $maNv)
                ->where('mat_khau', $currentHash)
                ->whereNotIn('ma_tt', NhanVienStatus::terminalValues())
                ->update(['mat_khau' => $newHash]);

            if ($updated === 0) {
                throw new NhanVienDomainException(
                    'Mật khẩu đã thay đổi, vui lòng đăng nhập lại.',
                    'NV_AUTH_HASH_STALE',
                );
            }
        });
    }

    public function findAccountByIdentifier(string $identifier): ?NhanVien
    {
        return $this->databaseOperation(function () use ($identifier): ?NhanVien {
            $row = $this->connection()->table('nhan_vien as nv')
                ->leftJoin('vai_tro as vt', 'vt.ma_vt', '=', 'nv.ma_vt')
                ->select([
                    'nv.ma_nv', 'nv.ho_ten', 'nv.email', 'nv.mat_khau', 'nv.ma_vt', 'nv.ma_pb', 'nv.ma_tt',
                    'vt.ten_vt',
                ])
                ->where(function (Builder $query) use ($identifier): void {
                    $query->where('nv.ma_nv', $identifier)
                        ->orWhereRaw('LOWER(TRIM(nv.email)) = ?', [strtolower(trim($identifier))]);
                })
                ->first();

            return $row === null ? null : NhanVien::fromAuthRow($row);
        });
    }

    /** @return array{phong_ban: array, chuc_vu: array, trang_thai: array} */
    public function lookups(): array
    {
        return $this->databaseOperation(fn (): array => [
            'phong_ban' => $this->connection()->table('phong_ban')
                ->select(['ma_pb', 'ten_pb'])
                ->orderBy('ma_pb')->get()->all(),
            'chuc_vu' => $this->connection()->table('chuc_vu')
                ->select(['ma_cv', 'ten_cv', 'he_so_phu_cap'])
                ->orderBy('ma_cv')->get()->all(),
            'trang_thai' => $this->connection()->table('trang_thai_lam_viec')
                ->select(['ma_tt', 'ten_tt'])
                ->orderBy('ma_tt')->get()->all(),
        ]);
    }

    private function connection(): Connection
    {
        return $this->database->connection();
    }

    private function employeeQuery(): Builder
    {
        return $this->connection()->table('nhan_vien as nv')
            ->join('phong_ban as pb', 'pb.ma_pb', '=', 'nv.ma_pb')
            ->join('chuc_vu as cv', 'cv.ma_cv', '=', 'nv.ma_cv')
            ->join('trang_thai_lam_viec as tt', 'tt.ma_tt', '=', 'nv.ma_tt')
            ->join('vai_tro as vt', 'vt.ma_vt', '=', 'nv.ma_vt')
            ->select([
                'nv.ma_nv', 'nv.ho_ten', 'nv.ngay_sinh', 'nv.gioi_tinh', 'nv.sdt', 'nv.email',
                'nv.ngay_vao_lam', 'nv.ma_pb', 'nv.ma_cv', 'nv.dan_toc', 'nv.cccd',
                'nv.noi_cap_cccd', 'nv.hoc_van', 'nv.ma_tt', 'nv.ma_vt', 'nv.anh_dai_dien',
                'nv.ngay_nghi_viec', 'nv.dia_chi_cu_the', 'nv.phuong_xa', 'nv.quan_huyen', 'nv.tinh_thanh',
                'pb.ten_pb', 'cv.ten_cv', 'cv.he_so_phu_cap', 'tt.ten_tt', 'vt.ten_vt',
            ]);
    }

    private function applyEmployeeFilters(Builder $query, array $filters): void
    {
        if (filled($filters['tu_khoa'] ?? null)) {
            $keyword = trim((string) $filters['tu_khoa']);
            $query->where(function (Builder $inner) use ($keyword): void {
                $inner->where('nv.ma_nv', 'like', "%{$keyword}%")
                    ->orWhere('nv.ho_ten', 'like', "%{$keyword}%")
                    ->orWhere('nv.email', 'like', "%{$keyword}%");
            });
        }
        foreach (['ma_pb', 'ma_cv', 'ma_tt'] as $column) {
            if (($filters[$column] ?? null) !== null && $filters[$column] !== '') {
                $query->where('nv.'.$column, (int) $filters[$column]);
            }
        }
    }

    /** @return array<string, mixed> */
    private function profileRow(array $profile): array
    {
        $row = [];
        foreach (self::PROFILE_COLUMNS as $column) {
            if (array_key_exists($column, $profile)) {
                $row[$column] = $profile[$column];
            }
        }

        return $row;
    }

    private function databaseOperation(callable $operation): mixed
    {
        try {
            return $operation();
        } catch (QueryException $exception) {
            throw $this->exceptions->map($exception);
        }
    }

    private function transactionIfNeeded(callable $operation): mixed
    {
        $connection = $this->connection();

        return $connection->transactionLevel() === 0
            ? $connection->transaction($operation)
            : $operation();
    }
}
