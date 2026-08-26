<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class NhanVien extends Model
{
    protected $table = 'nhan_vien';

    protected $primaryKey = 'ma_nv';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $hidden = ['mat_khau'];

}
