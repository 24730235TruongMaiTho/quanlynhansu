<?php

namespace App\Repositories;

use App\Contracts\VaiTroRepositoryContract;
use App\Exceptions\VaiTroDomainException;
use Illuminate\Database\DatabaseManager;

final class VaiTroRepository implements VaiTroRepositoryContract
{
    public function __construct(private DatabaseManager $database) {}

    public function all(string $keyword = ''): array
    {
        return $this->database->connection()->table('vai_tro as vt')
            ->leftJoin('nhan_vien as nv', 'nv.ma_vt', '=', 'vt.ma_vt')
            ->when($keyword !== '', fn ($q) => $q->where('vt.ten_vt', 'like', '%'.$keyword.'%'))
            ->select(['vt.ma_vt', 'vt.ten_vt', 'vt.mo_ta'])->selectRaw('COUNT(nv.ma_nv) AS so_tai_khoan')
            ->groupBy('vt.ma_vt', 'vt.ten_vt', 'vt.mo_ta')->orderBy('vt.ma_vt')->get()->all();
    }

    public function find(int $maVt): ?object
    {
        return $this->database->connection()->table('vai_tro')->where('ma_vt', $maVt)->first(['ma_vt', 'ten_vt', 'mo_ta']);
    }

    public function create(array $data): int
    {
        return $this->database->connection()->transaction(function () use ($data): int {
            $maximum = (int) ($this->database->connection()->table('vai_tro')->lockForUpdate()->max('ma_vt') ?? 0);
            $nextId = max(5, $maximum) + 1;
            $this->database->connection()->table('vai_tro')->insert(['ma_vt' => $nextId] + $data);
            return $nextId;
        });
    }

    public function update(int $maVt, array $data): void
    {
        if ($this->database->connection()->table('vai_tro')->where('ma_vt', $maVt)->update($data) === 0 && $this->find($maVt) === null) {
            throw new VaiTroDomainException('Không tìm thấy vai trò.', 'ROLE_NOT_FOUND');
        }
    }

    public function delete(int $maVt): void
    {
        $connection = $this->database->connection();
        $connection->transaction(function () use ($connection, $maVt): void {
            if ($maVt <= 5) throw new VaiTroDomainException('Không thể xóa vai trò hệ thống.', 'ROLE_PROTECTED');
            if ($connection->table('nhan_vien')->where('ma_vt', $maVt)->exists()) throw new VaiTroDomainException('Vai trò đang được sử dụng.', 'ROLE_IN_USE');
            $connection->table('vai_tro_quyen')->where('ma_vt', $maVt)->delete();
            if ($connection->table('vai_tro')->where('ma_vt', $maVt)->delete() !== 1) throw new VaiTroDomainException('Không tìm thấy vai trò.', 'ROLE_NOT_FOUND');
        });
    }
}
