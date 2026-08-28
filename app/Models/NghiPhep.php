<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NghiPhep extends Model
{
    protected $table = 'nghi_phep';
    protected $primaryKey = 'ma_np';
    public $timestamps = false;

    protected $fillable = [
        'ma_nv',
        'tu_ngay',
        'den_ngay',
        'ma_lp',
        'ly_do',
        'trang_thai_duyet',
    ];

    protected $casts = [
        'trang_thai_duyet' => 'integer',
    ];

    public function nhanVien()
    {
        return $this->belongsTo(NhanVien::class, 'ma_nv', 'ma_nv');
    }

    public function loaiPhep()
    {
        return $this->belongsTo(LoaiPhep::class, 'ma_lp', 'ma_lp');
    }
}
