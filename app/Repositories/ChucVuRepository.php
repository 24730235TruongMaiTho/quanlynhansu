<?php

namespace App\Repositories;

use App\Contracts\ChucVuRepositoryContract;
use App\Exceptions\ChucVuDomainException;
use App\Support\ChucVuExceptionMapper;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Pagination\LengthAwarePaginator;

final class ChucVuRepository implements ChucVuRepositoryContract
{
    public function __construct(
        private DatabaseManager $database,
        private ChucVuExceptionMapper $exceptions,
    ) {}

    public function all(): array
    {
        return $this->databaseOperation(
            fn (): array => $this->positionQuery()->get()->map(
                fn (object $row): object => $this->explicitRow($row),
            )->all(),
        );
    }

    public function paginate(array $filters): LengthAwarePaginator
    {
        return $this->databaseOperation(function () use ($filters): LengthAwarePaginator {
            $filters += ['ten_cv' => null, 'page' => 1, 'so_dong' => 20];
            $query = $this->positionQuery();

            if (filled($filters['ten_cv'])) {
                $query->where('cv.ten_cv', 'like', '%'.trim((string) $filters['ten_cv']).'%');
            }

            return $query->paginate(
                (int) $filters['so_dong'],
                $query->columns ?? ['*'],
                'page',
                (int) $filters['page'],
            );
        });
    }

    public function find(int $maCv): ?object
    {
        return $this->databaseOperation(function () use ($maCv): ?object {
            $row = $this->positionQuery()->where('cv.ma_cv', $maCv)->first();

            return $row === null ? null : $this->explicitRow($row);
        });
    }

    public function create(string $tenCv, string $heSoPhuCap): void
    {
        $name = $this->normalizeName($tenCv);
        $rate = $this->normalizeRate($heSoPhuCap);

        $this->databaseOperation(function () use ($name, $rate): void {
            $this->connection()->transaction(function () use ($name, $rate): void {
                $this->connection()->table('chuc_vu')->insert([
                    'ten_cv' => $name,
                    'he_so_phu_cap' => $rate,
                ]);
            });
        });
    }

    public function update(int $maCv, string $tenCv, string $heSoPhuCap): void
    {
        $name = $this->normalizeName($tenCv);
        $rate = $this->normalizeRate($heSoPhuCap);

        $this->databaseOperation(function () use ($maCv, $name, $rate): void {
            $this->connection()->transaction(function () use ($maCv, $name, $rate): void {
                $position = $this->lockedPosition($maCv);

                if ($position === null) {
                    throw $this->notFound();
                }

                $this->connection()->table('chuc_vu')->where('ma_cv', $maCv)->update([
                    'ten_cv' => $name,
                    'he_so_phu_cap' => $rate,
                ]);
            });
        });
    }

    public function delete(int $maCv): void
    {
        $this->databaseOperation(function () use ($maCv): void {
            $this->connection()->transaction(function () use ($maCv): void {
                if ($this->lockedPosition($maCv) === null) {
                    throw $this->notFound();
                }

                if ($this->connection()->table('nhan_vien')->where('ma_cv', $maCv)->exists()) {
                    throw new ChucVuDomainException(
                        'Không thể xóa chức vụ vì đang có nhân viên thuộc chức vụ này.',
                        'CV_IN_USE',
                        'chuc_vu',
                    );
                }

                $deleted = $this->connection()->table('chuc_vu')->where('ma_cv', $maCv)->delete();

                if ($deleted !== 1) {
                    throw $this->notFound();
                }
            });
        });
    }

    private function connection(): Connection
    {
        return $this->database->connection();
    }

    private function positionQuery(): Builder
    {
        return $this->connection()->table('chuc_vu as cv')
            ->leftJoin('nhan_vien as nv', 'nv.ma_cv', '=', 'cv.ma_cv')
            ->select(['cv.ma_cv', 'cv.ten_cv', 'cv.he_so_phu_cap'])
            ->selectRaw('COUNT(nv.ma_nv) AS so_nhan_vien')
            ->groupBy('cv.ma_cv', 'cv.ten_cv', 'cv.he_so_phu_cap')
            ->orderBy('cv.ma_cv', 'asc');
    }

    private function lockedPosition(int $maCv): ?object
    {
        if ($maCv < 1) {
            throw new ChucVuDomainException('Chức vụ không hợp lệ.', 'CV_ID_INVALID');
        }

        return $this->connection()->table('chuc_vu')
            ->where('ma_cv', $maCv)
            ->lockForUpdate()
            ->first(['ma_cv']);
    }

    private function explicitRow(object $row): object
    {
        return (object) [
            'ma_cv' => (int) $row->ma_cv,
            'ten_cv' => (string) $row->ten_cv,
            'he_so_phu_cap' => number_format((float) $row->he_so_phu_cap, 2, '.', ''),
            'so_nhan_vien' => (int) $row->so_nhan_vien,
        ];
    }

    private function normalizeName(string $name): string
    {
        $normalized = trim($name);

        if ($normalized === '') {
            throw new ChucVuDomainException('Tên chức vụ không được để trống.', 'CV_NAME_REQUIRED', 'ten_cv');
        }

        if (mb_strlen($normalized) > 100) {
            throw new ChucVuDomainException('Tên chức vụ không được dài quá 100 ký tự.', 'CV_NAME_TOO_LONG', 'ten_cv');
        }

        return $normalized;
    }

    private function normalizeRate(string $rate): string
    {
        $normalized = trim($rate);

        if ($normalized === '') {
            throw new ChucVuDomainException(
                'Hệ số phụ cấp không được để trống.',
                'CV_RATE_REQUIRED',
                'he_so_phu_cap',
            );
        }

        if (preg_match('/\A\d{1,2}(?:\.\d{1,2})?\z/', $normalized) !== 1) {
            throw new ChucVuDomainException(
                'Hệ số phụ cấp phải là số từ 0 đến 99.99, tối đa 2 chữ số thập phân.',
                'CV_RATE_INVALID',
                'he_so_phu_cap',
            );
        }

        return number_format((float) $normalized, 2, '.', '');
    }

    private function notFound(): ChucVuDomainException
    {
        return new ChucVuDomainException('Không tìm thấy chức vụ.', 'CV_NOT_FOUND');
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
