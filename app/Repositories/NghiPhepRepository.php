<?php

namespace App\Repositories;

use App\Models\NghiPhep;

class NghiPhepRepository
{
    public function all($filters = [])
    {
        $query = NghiPhep::query();

        if (isset($filters['ma_nv'])) {
            $query->where('ma_nv', $filters['ma_nv']);
        }

        if (isset($filters['trang_thai_duyet'])) {
            $query->where('trang_thai_duyet', $filters['trang_thai_duyet']);
        }

        if (isset($filters['tu_ngay'])) {
            $query->whereDate('tu_ngay', '>=', $filters['tu_ngay']);
        }

        if (isset($filters['den_ngay'])) {
            $query->whereDate('den_ngay', '<=', $filters['den_ngay']);
        }

        return $query->with(['nhanVien', 'loaiPhep'])->paginate(15);
    }

    public function find($id)
    {
        return NghiPhep::with(['nhanVien', 'loaiPhep'])->find($id);
    }

    public function create(array $data)
    {
        return NghiPhep::create($data);
    }

    public function update($id, array $data)
    {
        $record = NghiPhep::find($id);
        if ($record) {
            $record->update($data);
        }
        return $record;
    }

    public function delete($id)
    {
        return NghiPhep::destroy($id);
    }
}
