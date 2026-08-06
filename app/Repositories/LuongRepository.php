<?php

namespace App\Repositories;

use App\Models\Luong;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class LuongRepository
{
    public function all(array $filters = []): LengthAwarePaginator
    {
        $page = max(
            (int) ($filters['page'] ?? 1),
            1
        );

        $perPage = min(
            max((int) ($filters['per_page'] ?? 15), 1),
            100
        );

        $maNhanVien = $this->nullIfEmpty(
            $filters['ma_nv'] ?? null
        );

        $kyLuong = $this->nullIfEmpty(
            $filters['ky_luong'] ?? null
        );

        $maPhongBan = $this->nullableInteger(
            $filters['ma_pb'] ?? null
        );

        $maChucVu = $this->nullableInteger(
            $filters['ma_cv'] ?? null
        );

        $rows = collect(
            DB::select(
                'CALL sp_luong_tim_kiem_phan_trang(
                    ?, ?, ?, ?, ?, ?
                )',
                [
                    $maNhanVien,
                    $kyLuong,
                    $maPhongBan,
                    $maChucVu,
                    $page,
                    $perPage,
                ]
            )
        );

        $total = (int) (
            $rows->first()->total_count ?? 0
        );

        $items = $rows
            ->map(function (object $row): object {
                unset($row->total_count);

                return $row;
            })
            ->values();

        return new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
                'query' => request()->query(),
                'pageName' => 'page',
            ]
        );
    }

    public function find($id)
    {
        return Luong::with('nhanVien')->find($id);
    }

    public function create(array $data)
    {
        return Luong::create($data);
    }

    public function update($id, array $data)
    {
        $record = Luong::find($id);
        if ($record) {
            $record->update($data);
        }
        return $record;
    }

    public function delete($id)
    {
        return Luong::destroy($id);
    }

    private function nullIfEmpty(mixed $param)
    {
        return $param === '' ? null : $param;
    }

    private function nullableInteger(mixed $param)
    {
        return is_numeric($param) ? (int) $param : null;
    }
}
