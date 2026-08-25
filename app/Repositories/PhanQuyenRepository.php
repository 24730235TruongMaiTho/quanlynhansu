<?php

namespace App\Repositories;

use App\Contracts\PhanQuyenRepositoryContract;
use App\Exceptions\PhanQuyenDomainException;
use Illuminate\Database\DatabaseManager;

final class PhanQuyenRepository implements PhanQuyenRepositoryContract
{
    public function __construct(private DatabaseManager $database) {}

    public function permissionsByModule(): array
    {
        return $this->database->connection()->table('quyen')->orderBy('module')->orderBy('ma_quyen')
            ->get(['ma_quyen', 'ky_hieu_quyen', 'ten_quyen', 'module'])->groupBy('module')->all();
    }

    public function permissionIdsForRole(int $maVt): array
    {
        return $this->database->connection()->table('vai_tro_quyen')->where('ma_vt', $maVt)
            ->orderBy('ma_quyen')->pluck('ma_quyen')->map(fn ($id) => (int) $id)->all();
    }

    public function syncRolePermissions(int $maVt, array $permissionIds): void
    {
        $connection = $this->database->connection();
        $connection->transaction(function () use ($connection, $maVt, $permissionIds): void {
            if ($maVt === 5) throw new PhanQuyenDomainException('Không thể cấp quyền cho vai trò nhân viên mặc định.', 'RBAC_DEFAULT_ROLE_PROTECTED');
            if (! $connection->table('vai_tro')->where('ma_vt', $maVt)->lockForUpdate()->exists()) throw new PhanQuyenDomainException('Không tìm thấy vai trò.', 'RBAC_ROLE_NOT_FOUND');
            $valid = $connection->table('quyen')->whereIn('ma_quyen', $permissionIds)->pluck('ma_quyen')->map(fn ($id) => (int) $id)->all();
            sort($valid); $expected = array_values(array_unique(array_map('intval', $permissionIds))); sort($expected);
            if ($valid !== $expected) throw new PhanQuyenDomainException('Danh sách quyền không hợp lệ.', 'RBAC_PERMISSION_INVALID');
            $connection->table('vai_tro_quyen')->where('ma_vt', $maVt)->delete();
            if ($valid !== []) $connection->table('vai_tro_quyen')->insert(array_map(fn ($id) => ['ma_vt' => $maVt, 'ma_quyen' => $id], $valid));
        });
    }

    public function accounts(string $keyword = ''): array
    {
        return $this->database->connection()->table('nhan_vien as nv')->join('vai_tro as vt', 'vt.ma_vt', '=', 'nv.ma_vt')
            ->when($keyword !== '', fn ($q) => $q->where(fn ($q) => $q->where('nv.ma_nv', 'like', '%'.$keyword.'%')->orWhere('nv.ho_ten', 'like', '%'.$keyword.'%')))
            ->orderBy('nv.ma_nv')->get(['nv.ma_nv', 'nv.ho_ten', 'nv.email', 'nv.ma_vt', 'vt.ten_vt'])->all();
    }

    public function assignRole(string $maNv, int $maVt, string $actorMaNv): void
    {
        if ($maNv === $actorMaNv) throw new PhanQuyenDomainException('Không thể tự thay đổi vai trò của chính mình.', 'RBAC_SELF_ASSIGNMENT');
        $connection = $this->database->connection();
        $connection->transaction(function () use ($connection, $maNv, $maVt): void {
            if (! $connection->table('vai_tro')->where('ma_vt', $maVt)->exists()) throw new PhanQuyenDomainException('Không tìm thấy vai trò.', 'RBAC_ROLE_NOT_FOUND');
            if ($connection->table('nhan_vien')->where('ma_nv', $maNv)->lockForUpdate()->update(['ma_vt' => $maVt]) !== 1) throw new PhanQuyenDomainException('Không tìm thấy tài khoản.', 'RBAC_ACCOUNT_NOT_FOUND');
        });
    }
}
