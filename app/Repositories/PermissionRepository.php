<?php

namespace App\Repositories;

use App\Contracts\PermissionRepositoryContract;
use Illuminate\Database\DatabaseManager;

final class PermissionRepository implements PermissionRepositoryContract
{
    public function __construct(private DatabaseManager $database) {}

    /** @return list<array{ma_quyen: int|string, ky_hieu_quyen: string, module: string}> */
    public function permissionsForActor(string $maNv): array
    {
        return $this->database->connection()->table('vai_tro_quyen as vtq')
            ->join('nhan_vien as nv', 'nv.ma_vt', '=', 'vtq.ma_vt')
            ->join('quyen as q', 'q.ma_quyen', '=', 'vtq.ma_quyen')
            ->where('nv.ma_nv', $maNv)
            ->select([
                'q.ma_quyen',
                'q.ky_hieu_quyen',
                'q.module',
            ])
            ->distinct()
            ->orderBy('q.ma_quyen')
            ->get()
            ->map(static fn (object $row): array => [
                'ma_quyen' => $row->ma_quyen,
                'ky_hieu_quyen' => $row->ky_hieu_quyen,
                'module' => $row->module,
            ])
            ->all();
    }
}
