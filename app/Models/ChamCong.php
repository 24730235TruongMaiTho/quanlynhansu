<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChamCong extends Model
{
    protected $table = 'chamcong';
    protected $primaryKey = 'ma_cc';
    public $timestamps = false;

    protected $fillable = [
        'ma_nv',
        'ngay_ky',
        'so_gio_lam',
        'vao_muon',
        've_som',
    ];

    protected $casts = [
        'ngay_ky' => 'date',
        'so_gio_lam' => 'decimal:2',
        'vao_muon' => 'integer',
        've_som' => 'integer',
    ];

    public function nhanVien()
    {
        return $this->belongsTo(NhanVien::class, 'ma_nv', 'ma_nv');
    }
}
