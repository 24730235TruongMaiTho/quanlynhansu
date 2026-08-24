<?php

namespace App\Services;

use App\Contracts\PhongBanRepositoryContract;
use App\Contracts\PhongBanServiceContract;
use App\Exceptions\PhongBanDomainException;

final class PhongBanService implements PhongBanServiceContract
{
    public function __construct(private PhongBanRepositoryContract $repository) {}

    public function all(): array
    {
        return $this->repository->all();
    }

    public function findOrFail(int $maPb): object
    {
        $department = $this->repository->find($maPb);

        if ($department === null) {
            throw new PhongBanDomainException('Không tìm thấy phòng ban.', 'PB_NOT_FOUND');
        }

        return $department;
    }

    public function create(string $tenPb): void
    {
        $this->repository->create($tenPb);
    }

    public function update(int $maPb, string $tenPb): void
    {
        $this->repository->update($maPb, $tenPb);
    }

    public function delete(int $maPb): void
    {
        $this->repository->delete($maPb);
    }
}
