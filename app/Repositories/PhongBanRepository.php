<?php

namespace App\Repositories;

use App\Contracts\PhongBanRepositoryContract;
use App\Exceptions\PhongBanDomainException;
use App\Support\PhongBanExceptionMapper;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\QueryException;

final class PhongBanRepository implements PhongBanRepositoryContract
{
    public function __construct(
        private DatabaseManager $database,
        private PhongBanExceptionMapper $exceptions,
    ) {}

    public function all(): array
    {
        return $this->databaseOperation(
            fn (): array => $this->departmentQuery()->get()->map(
                fn (object $row): object => $this->explicitRow($row),
            )->all(),
        );
    }

    public function find(int $maPb): ?object
    {
        return $this->databaseOperation(function () use ($maPb): ?object {
            $row = $this->departmentQuery()
                ->where('pb.ma_pb', $maPb)
                ->first();

            return $row === null ? null : $this->explicitRow($row);
        });
    }

    public function create(string $tenPb): void
    {
        $name = $this->normalizeName($tenPb);

        $this->databaseOperation(function () use ($name): void {
            $this->connection()->transaction(function () use ($name): void {
                $this->connection()->table('phong_ban')->insert(['ten_pb' => $name]);
            });
        });
    }

    public function update(int $maPb, string $tenPb): void
    {
        $name = $this->normalizeName($tenPb);

        $this->databaseOperation(function () use ($maPb, $name): void {
            $this->connection()->transaction(function () use ($maPb, $name): void {
                $department = $this->lockedDepartment($maPb);

                if ($department === null) {
                    throw $this->notFound();
                }

                $this->connection()->table('phong_ban')
                    ->where('ma_pb', $maPb)
                    ->update(['ten_pb' => $name]);
            });
        });
    }

    public function delete(int $maPb): void
    {
        $this->databaseOperation(function () use ($maPb): void {
            $this->connection()->transaction(function () use ($maPb): void {
                $department = $this->lockedDepartment($maPb);

                if ($department === null) {
                    throw $this->notFound();
                }

                if ($this->connection()->table('nhan_vien')->where('ma_pb', $maPb)->exists()) {
                    throw new PhongBanDomainException(
                        'Không thể xóa phòng ban vì đang có nhân viên thuộc phòng ban này.',
                        'PB_IN_USE',
                        'phong_ban',
                    );
                }

                $deleted = $this->connection()->table('phong_ban')
                    ->where('ma_pb', $maPb)
                    ->delete();

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

    private function departmentQuery(): Builder
    {
        return $this->connection()->table('phong_ban as pb')
            ->leftJoin('nhan_vien as nv', 'nv.ma_pb', '=', 'pb.ma_pb')
            ->select(['pb.ma_pb', 'pb.ten_pb'])
            ->selectRaw('COUNT(nv.ma_nv) AS so_nhan_vien')
            ->groupBy('pb.ma_pb', 'pb.ten_pb')
            ->orderBy('pb.ma_pb', 'asc');
    }

    private function lockedDepartment(int $maPb): ?object
    {
        if ($maPb < 1) {
            throw new PhongBanDomainException('Phòng ban không hợp lệ.', 'PB_ID_INVALID');
        }

        return $this->connection()->table('phong_ban')
            ->where('ma_pb', $maPb)
            ->lockForUpdate()
            ->first(['ma_pb']);
    }

    private function explicitRow(object $row): object
    {
        return (object) [
            'ma_pb' => (int) $row->ma_pb,
            'ten_pb' => (string) $row->ten_pb,
            'so_nhan_vien' => (int) $row->so_nhan_vien,
        ];
    }

    private function normalizeName(string $name): string
    {
        $normalized = trim($name);

        if ($normalized === '') {
            throw new PhongBanDomainException(
                'Tên phòng ban không được để trống.',
                'PB_NAME_REQUIRED',
                'ten_pb',
            );
        }

        if (mb_strlen($normalized) > 100) {
            throw new PhongBanDomainException(
                'Tên phòng ban không được dài quá 100 ký tự.',
                'PB_NAME_TOO_LONG',
                'ten_pb',
            );
        }

        return $normalized;
    }

    private function notFound(): PhongBanDomainException
    {
        return new PhongBanDomainException('Không tìm thấy phòng ban.', 'PB_NOT_FOUND');
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
