<?php

namespace App\Services;

use App\Contracts\VaiTroRepositoryContract;
use App\Contracts\VaiTroServiceContract;
use App\Exceptions\VaiTroDomainException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class VaiTroService implements VaiTroServiceContract
{
    public function __construct(private VaiTroRepositoryContract $repository) {}
    public function all(string $keyword = ''): array { return $this->repository->all(trim($keyword)); }
    public function paginate(array $filters): LengthAwarePaginator { return $this->repository->paginate($filters); }
    public function findOrFail(int $maVt): object { return $this->repository->find($maVt) ?? throw new VaiTroDomainException('Không tìm thấy vai trò.', 'ROLE_NOT_FOUND'); }
    public function create(array $data): int { $data['ten_vt'] = trim($data['ten_vt']); return $this->repository->create($data); }
    public function update(int $maVt, array $data): void { $data['ten_vt'] = trim($data['ten_vt']); $this->repository->update($maVt, $data); }
    public function delete(int $maVt): void { $this->repository->delete($maVt); }
}
