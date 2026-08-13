<?php

namespace App\Services;

use App\Contracts\NhanVienRepositoryContract;
use App\Contracts\NhanVienServiceContract;
use Illuminate\Pagination\LengthAwarePaginator;

final class NhanVienService implements NhanVienServiceContract
{
    public function __construct(private NhanVienRepositoryContract $repository)
    {
    }

    public function paginate(array $filters): LengthAwarePaginator
    {
        return $this->repository->paginate($filters);
    }

    public function paginateForAttendance(array $filters): LengthAwarePaginator
    {
        return $this->repository->paginateAttendance($filters);
    }

    public function findOrFail(string $maNv): object
    {
        $employee = $this->repository->find($maNv);

        abort_if($employee === null, 404);

        return $employee;
    }

    public function lookups(): array
    {
        return $this->repository->lookups();
    }
}
