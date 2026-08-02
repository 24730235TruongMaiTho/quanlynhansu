<?php

namespace App\Repositories;

use App\Models\ChamCong;

class ChamCongRepository
{
    public function all($filters = [])
    {
        $query = ChamCong::query();

        if (isset($filters['ma_nv'])) {
            $query->where('ma_nv', $filters['ma_nv']);
        }

        if (isset($filters['from_date'])) {
            $query->whereDate('ngay_ky', '>=', $filters['from_date']);
        }

        if (isset($filters['to_date'])) {
            $query->whereDate('ngay_ky', '<=', $filters['to_date']);
        }

        return $query->with('nhanVien')->paginate(15);
    }

    public function find($id)
    {
        return ChamCong::with('nhanVien')->find($id);
    }

    public function create(array $data)
    {
        return ChamCong::create($data);
    }

    public function update($id, array $data)
    {
        $record = ChamCong::find($id);
        if ($record) {
            $record->update($data);
        }
        return $record;
    }

    public function delete($id)
    {
        return ChamCong::destroy($id);
    }
}
