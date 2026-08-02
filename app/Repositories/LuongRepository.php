<?php

namespace App\Repositories;

use App\Models\Luong;

class LuongRepository
{
    public function all($filters = [])
    {
        $query = Luong::query();

        if (isset($filters['ma_nv'])) {
            $query->where('ma_nv', $filters['ma_nv']);
        }

        if (isset($filters['from_date'])) {
            $query->whereDate('ky_luong', '>=', $filters['from_date']);
        }

        if (isset($filters['to_date'])) {
            $query->whereDate('ky_luong', '<=', $filters['to_date']);
        }

        return $query->with('nhanVien')->paginate(15);
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
}
