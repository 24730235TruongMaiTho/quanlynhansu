<?php

namespace App\Services;

use App\Contracts\ChucVuRepositoryContract;
use App\Contracts\ChucVuServiceContract;
use App\Exceptions\ChucVuDomainException;
use Illuminate\Pagination\LengthAwarePaginator;

final class ChucVuService implements ChucVuServiceContract
{
    public function __construct(private ChucVuRepositoryContract $repository) {}

    public function all(): array
    {
        return $this->repository->all();
    }

    public function paginate(array $filters): LengthAwarePaginator
    {
        return $this->repository->paginate($filters);
    }

    public function findOrFail(int $maCv): object
    {
        $position = $this->repository->find($maCv);

        if ($position === null) {
            throw new ChucVuDomainException('Không tìm thấy chức vụ.', 'CV_NOT_FOUND');
        }

        return $position;
    }

    public function create(string $tenCv, string $heSoPhuCap): void
    {
        $this->repository->create(trim($tenCv), trim($heSoPhuCap));
    }

    public function update(int $maCv, string $tenCv, string $heSoPhuCap): void
    {
        $this->repository->update($maCv, trim($tenCv), trim($heSoPhuCap));
    }

    public function delete(int $maCv): void
    {
        $this->repository->delete($maCv);
    }
}
