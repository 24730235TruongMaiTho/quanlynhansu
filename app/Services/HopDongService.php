<?php

namespace App\Services;

use App\Contracts\HopDongRepositoryContract;
use App\Contracts\HopDongServiceContract;
use App\Exceptions\HopDongDomainException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class HopDongService implements HopDongServiceContract
{
    public function __construct(private HopDongRepositoryContract $repository) {}
    public function paginate(array $filters): LengthAwarePaginator { return $this->repository->paginate($filters, (int) ($filters['per_page'] ?? 20), max(1, (int) config('hopdong.expiring_warning_days', 30))); }
    public function findOrFail(int $maHd): object { return $this->repository->find($maHd) ?? throw new HopDongDomainException('Không tìm thấy hợp đồng.', 'HD_NOT_FOUND'); }
    public function formOptions(): array { return ['employees' => $this->repository->employees(), 'types' => $this->repository->types()]; }
    public function create(array $data): int { return $this->repository->create($data); }
    public function update(int $maHd, array $data): void { $this->repository->update($maHd, $data); }
    public function delete(int $maHd): void { $this->repository->delete($maHd); }
}
