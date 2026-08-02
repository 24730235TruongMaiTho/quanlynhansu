<?php

namespace App\Repositories;

use App\Models\ChucVu;

class ChucVuRepository
{
    public function all($filters = [])
    {
        $query = ChucVu::query();

        if (isset($filters['ten_cv'])) {
            $query->where('ten_cv', 'like', '%' . $filters['ten_cv'] . '%');
        }

        return $query->paginate(15);
    }

    public function find($id)
    {
        return ChucVu::find($id);
    }

    public function create(array $data)
    {
        return ChucVu::create($data);
    }

    public function update($id, array $data)
    {
        $record = ChucVu::find($id);
        if ($record) {
            $record->update($data);
        }
        return $record;
    }

    public function delete($id)
    {
        return ChucVu::destroy($id);
    }
}
